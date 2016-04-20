<?php
/**
 * Importer for Gravity Forms and NationBuilder Gravity Forms Feed
 * @version 0.0.0
 * @package Importer for Gravity Forms and NationBuilder
 */

class GFNBI_Gravity_Forms_Feed extends GFFeedAddOn {

    protected $plugin;
    protected $_version;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug;
    protected $_path;
    protected $_full_path = __FILE__;
    protected $_title = 'NationBuilder Importer Add-On';
    protected $_short_title = 'NationBuilder';

    private static $_instance = null;

    public function __construct( $plugin ) {
        parent::__construct();

        $this->plugin = $plugin;
        $this->_version = $this->plugin->version;
        $this->_slug = $this->plugin->slug;
        $this->_path = plugin_basename( __FILE__ );
        $this->callback_url = site_url( 'wp-json/' . $this->_slug . '/v1/oauth_callback/' );
        $this->callback_nonce =  '?_wpnonce=' . wp_create_nonce( 'wp_rest' );
        self::$_instance = $this;

        add_action( 'rest_api_init', function () {
            register_rest_route( $this->_slug . '/v1/' , '/oauth_callback/', array(
                'methods' => 'GET',
                'callback' => array( $this, 'handle_oauth_callback' ),
            ) );
        } );
    }

    /**
     * Get an instance of this class.
     *
     * @return GFSimpleFeedAddOn
     */
    public static function get_instance() {
        return self::$_instance;
    }

    /**
     * Get the authorization code from the Oauth callback and redirect back to the GF administration page.
     */
    public function handle_oauth_callback( WP_REST_Request $request ) {
        if ( ! isset( $request['code'] ) || ! $this->current_user_can_any( $this->_capabilities_settings_page ) ) {
            wp_redirect( site_url( 'wp-admin/admin.php?page=gf_settings&subview=gf_nb_importer' ) );
        }

        $nation_slug = $this->get_plugin_setting( 'nation_slug' );
        $client_id = $this->get_plugin_setting( 'oauth_client_id' );
        $client_secret = $this->get_plugin_setting( 'oauth_client_secret' );
        $code = sanitize_text_field( $request['code'] );

        if ( $code ) {
            $client = new OAuth2\Client( $client_id, $client_secret );
            $settings = $this->get_plugin_settings();
            $settings['oauth_authorization_code'] = $code;

            $access_token_url = 'https://' . $nation_slug . '.nationbuilder.com/oauth/token';
            $params = array( 'code' => $code, 'redirect_uri' => $this->callback_url . $this->callback_nonce );
            $response = $client->getAccessToken( $access_token_url, 'authorization_code', $params );

            if ( isset( $response['result']['access_token'] ) ) {
                $settings['oauth_authorization_code'] = $code;
                $settings['oauth_access_token'] = $response['result']['access_token'];
            }
        }

        $sections = $this->plugin_settings_fields();
        $is_valid = $this->validate_settings( $sections, $settings );

        if ( $is_valid ) {
            $settings = $this->filter_settings( $sections, $settings );
            $this->update_plugin_settings( $settings );
        }

        wp_redirect( site_url( 'wp-admin/admin.php?page=gf_settings&subview=gf_nb_importer' ) );
        exit();
    }

    /**
     * Process the feed
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed( $feed, $entry, $form ) {
        $access_token = $this->get_plugin_setting( 'oauth_access_token' );
        $nation_slug = $this->get_plugin_setting( 'nation_slug' );
        $nb_fields_raw = $this->get_dynamic_field_map_fields( $feed, 'nb_person_fields' );
        $nb_fields_formatted = array();

        if ( ! $access_token || ! $nation_slug ) {
            return;
        }

        // Loop through the custom fields and format them into a nested array that we'll send to NB.
        foreach ($nb_fields_raw as $key => $value) {
            $entry_value = array_key_exists($value, $entry) ? $entry[$value] : '';
            $key = str_replace( ']', '', $key );
            $key = explode('[', $key);

            if ( ! array_key_exists($key[0], $nb_fields_formatted) ) {
                $nb_fields_formatted[$key[0]] = array();
            }

            if ( count( $key ) === 1 && $entry_value ) {
                $nb_fields_formatted[$key[0]] = $entry_value;
            } elseif ( count( $key ) === 2 && $entry_value ) {
                $nb_fields_formatted[$key[0]][$key[1]] = $entry_value;
            }
        }

        if ( empty( $nb_fields_formatted ) ) {
            return;
        }

        $response = wp_remote_request(
            "https://{$nation_slug}.nationbuilder.com/api/v1/people/push",
            array(
                'method' => 'PUT',
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ),
                'body' => json_encode( array('person' => $nb_fields_formatted) )
            )
        );

        if ( ! is_wp_error( $response ) ) {
            if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                $this->add_note(
                    $entry['id'],
                    __( 'Successfully updated this entry in NationBuilder.', 'gf-nb-importer'),
                    'success'
                );
            } elseif ( wp_remote_retrieve_response_code( $response ) === 201 ) {
                $this->add_note(
                    $entry['id'],
                    __( 'Successfully created this entry in NationBuilder.', 'gf-nb-importer'),
                    'success'
                );
            } else {
                $this->add_note(
                    $entry['id'],
                    __( 'NationBuilder did not return a successful response when pushing this entry.', 'gf-nb-importer'),
                    'error'
                );
            }
        } elseif ( is_wp_error( $response ) ) {
            $this->add_note(
                $entry['id'],
                __( 'There was an error pushing this entry to NationBuilder: ', 'gf-nb-importer') . $response->get_error_message(),
                'error'
            );
        }
    }

    /**
     * Configures the settings which should be rendered on the add-on settings tab.
     *
     * @return array
     */
    public function plugin_settings_fields() {
        $nation_slug = $this->get_plugin_setting( 'nation_slug' );
        $client_id = $this->get_plugin_setting( 'oauth_client_id' );
        $client_secret = $this->get_plugin_setting( 'oauth_client_secret' );
        $authorization_code = $this->get_plugin_setting( 'oauth_authorization_code' );
        $access_token = $this->get_plugin_setting( 'oauth_access_token' );

        $instructions_part_1 = __('
            In order to set up the integration between Gravity Forms and NationBuilder, you will need to create an "app"
            in your nation. You can set up an app here (using your nation\'s slug): https://YOUR_NATION_SLUG_HERE.nationbuilder.com/admin/apps/new
        ', 'gf-nb-importer' );

        $instructions_part_2 = __( 'The callback URL for your app is:', 'gf-nb-importer' );

        $instructions = sprintf(
            '<p>%s</p><p>%s<br>%s</p>',
            $instructions_part_1,
            $instructions_part_2,
            $this->callback_url
        );

        $settings = array(
            array(
                'title'  => esc_html__( 'NationBuilder Importer Add-On Settings', 'gf-nb-importer' ),
                'fields' => array(
                    array(
                        'type'      => 'message',
                        'name'      => 'instructions',
                        'value'     => $instructions
                    ),
                    array(
                        'name'      => 'nation_slug',
                        'label'     => esc_html__( 'Nation Slug', 'gf-nb-importer' ),
                        'type'      => 'text',
                        'class'     => 'small',
                    ),
                    array(
                        'name'      => 'oauth_client_id',
                        'label'     => esc_html__( 'OAuth Client ID', 'gf-nb-importer' ),
                        'type'      => 'text',
                        'class'     => 'small',
                    ),
                    array(
                        'name'      => 'oauth_client_secret',
                        'label'     => esc_html__( 'OAuth Client Secret', 'gf-nb-importer' ),
                        'type'      => 'text',
                        'class'     => 'small',
                    ),
                    array(
                        'name'      => 'oauth_authorization_code',
                        'label'     => esc_html__( 'OAuth Authorization Code', 'gf-nb-importer' ),
                        'type'      => 'hidden',
                        'class'     => 'small',
                    ),
                    array(
                        'name'      => 'oauth_access_token',
                        'label'     => esc_html__( 'OAuth Client Secret', 'gf-nb-importer' ),
                        'type'      => 'hidden',
                        'class'     => 'small',
                    ),
                ),
            ),
        );

        if ( ( $client_id && $client_secret && $nation_slug ) ) {
            $client = new OAuth2\Client( $client_id, $client_secret );
            $authorize_url = 'https://' . esc_attr( $nation_slug ) . '.nationbuilder.com/oauth/authorize';
            $auth_url = $client->getAuthenticationUrl( $authorize_url, $this->callback_url . $this->callback_nonce );

            $message_no_token = __( 'Click here to complete the OAuth process.', 'gf-nb-importer' );
            $message_token = __( 'You already have an access token stored, but you may click here to generate and save a new one.', 'gf-nb-importer' );

            $message = sprintf(
                '<a href="%s">%s</a>',
                esc_attr( $auth_url ),
                empty($access_token) ? $message_no_token : $message_token
            );

            $settings[0]['fields'][] = array(
                'type'      => 'message',
                'name'      => 'oauth_process_message',
                'value'     => $message
            );
        }

        return $settings;
    }

    /**
     * Render a 'message' setting
     *
     * @return string
     */
    protected function settings_message( $field, $echo = true ) {
        $html = '<div class="message">';
        $html .= $field['value'];
        $html .= '</div>';

        if ( $echo ) {
            echo $html;
        }

        return $html;
    }

    /**
     * Configures the settings which should be rendered on the feed edit page in the Form Settings > Simple Feed Add-On area.
     *
     * @return array
     */
    public function feed_settings_fields() {
        return array(
            array(
                'title'  => esc_html__( 'NationBuilder Feed Settings', 'gf-nb-importer' ),
                'fields' => array(
                    array(
                        'label'   => esc_html__( 'Feed name', 'gf-nb-importer' ),
                        'type'    => 'text',
                        'name'    => 'feedName',
                        'class'   => 'small',
                    ),
                    array(
                        'type' => 'message',
                        'label' => esc_html__( 'NationBuilder Person Fields', 'gf-nb-importer' ),
                        'name' => 'nb_field_instructions',
                        'value' => esc_html__( 'You can view the available custom field keys from the NationBuilder API documentation (http://nationbuilder.com/people_api). Nested fields should use the following syntax: registered_address[state]', 'gf-nb-importer' )
                    ),
                    array(
                        'name'                  => 'nb_person_fields',
                        'label'                 => '',
                        'type'                  => 'dynamic_field_map',
                        'limit'                 => 200,
                        'exclude_field_types'   => 'creditcard'
                    ),
                    array(
                        'name'           => 'condition',
                        'label'          => esc_html__( 'Condition', 'gf-nb-importer' ),
                        'type'           => 'feed_condition',
                        'checkbox_label' => esc_html__( 'Enable Condition', 'gf-nb-importer' ),
                        'instructions'   => esc_html__( 'Process this simple feed if', 'gf-nb-importer' ),
                    ),
                ),
            ),
        );
    }

    /**
     * Configures which columns should be displayed on the feed list page.
     *
     * @return array
     */
    public function feed_list_columns() {
        return array(
            'feedName'  => esc_html__( 'Name', 'gf-nb-importer' ),
        );
    }

    /**
     * Prevent feeds being listed or created if the access token has not been set.
     *
     * @return bool
     */
    public function can_create_feed() {
        $access_token = $this->get_plugin_setting( 'oauth_access_token' );

        if ( ! $access_token ) {
            return false;
        }

        return true;
    }

    /**
     * The message to display on the feed edit screen if the access token hasn't been set.
     *
     * @return bool
     */
    public function configure_addon_message() {
        return sprintf(
            '<a href="%s">%s</a>',
            site_url( 'wp-admin/admin.php?page=gf_settings&subview=gf_nb_importer' ),
            __('Please click here to complete the OAuth process before setting up a feed.', 'gf-nb-importer')
        );
    }

}
