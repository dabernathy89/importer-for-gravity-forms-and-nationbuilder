<?php
/**
 * Importer for Gravity Forms and NationBuilder Gravity Forms Main
 * @version 0.3.3
 * @package Importer for Gravity Forms and NationBuilder
 */

class GFNBI_Gravity_Forms_Main extends GFAddOn {
    protected $plugin;
    protected $_version;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug;
    protected $_path;
    protected $_full_path = __FILE__;
    protected $_title = 'NationBuilder Importer Settings';
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

        add_action( 'rest_api_init', array( $this, 'register_oauth_route' ) );
    }

    /**
     * Register the REST API route for the oauth callback
     *
     * @return void
     */
    public function register_oauth_route() {
        register_rest_route( $this->_slug . '/v1/' , '/oauth_callback/', array(
            'methods' => 'GET',
            'callback' => array( $this, 'handle_oauth_callback' ),
        ) );
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
            wp_redirect( site_url( 'wp-admin/admin.php?page=gf_settings&subview=' . $this->_slug ) );
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

        wp_redirect( site_url( 'wp-admin/admin.php?page=gf_settings&subview=' . $this->_slug ) );
        exit();
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

}
