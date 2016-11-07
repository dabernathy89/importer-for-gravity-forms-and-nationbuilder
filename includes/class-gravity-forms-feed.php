<?php
/**
 * Importer for Gravity Forms and NationBuilder Gravity Forms Feed
 * @version 0.3.4
 * @package Importer for Gravity Forms and NationBuilder
 */

class GFNBI_Gravity_Forms_Feed extends GFFeedAddOn {

    protected $plugin;
    protected $main;
    protected $nb_api;
    protected $_version;
    protected $_min_gravityforms_version = '1.9.16';
    protected $_slug;
    protected $_path;
    protected $_full_path = __FILE__;
    protected $_title = 'NationBuilder Importer Feeds';
    protected $_short_title = 'NationBuilder';

    private static $_instance = null;

    public function __construct( $plugin, $main, $nb_api ) {
        parent::__construct();

        $this->plugin = $plugin;
        $this->main = $main;
        $this->nb_api = $nb_api;
        $this->_version = $this->plugin->version;
        $this->_slug = $this->plugin->slug . '_feeds';
        $this->_path = plugin_basename( __FILE__ );
        self::$_instance = $this;
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
     * Process the feed
     *
     * @param array $feed The feed object to be processed.
     * @param array $entry The entry object currently being processed.
     * @param array $form The form object currently being processed.
     *
     * @return bool|void
     */
    public function process_feed( $feed, $entry, $form ) {
        $nb_fields_raw = $this->get_dynamic_field_map_fields( $feed, 'nb_person_fields' );
        $nb_fields_formatted = array();
        $tags = GFCommon::replace_variables($feed['meta']['nb_tags'], $form, $entry);
        $tags = array_filter( array_map( 'trim', explode( ',', $tags ) ) );

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

        $response = $this->nb_api->push_person( array('person' => $nb_fields_formatted) );

        if ( ! is_wp_error( $response ) ) {
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code === 200 ) {
                $note = __( 'Successfully updated this entry in NationBuilder.', 'gf-nb-importer');
            } elseif ( $code === 201 ) {
                $note = __( 'Successfully created this entry in NationBuilder.', 'gf-nb-importer');
            }

            $this->add_note( $entry['id'], $note, 'success' );
            $this->add_tags_to_person( $response, $tags, $entry );
        } elseif ( is_wp_error( $response ) ) {
            $this->add_note_from_wp_error(
                $response,
                $entry['id'],
                __( 'There was an error pushing this entry to NationBuilder:', 'gf-nb-importer' )
            );
        }
    }

    /**
     * Add tags to the person that was just created or updated
     *
     * @since  0.3.0
     * @param  $response
     * @param  $tags
     * @param  $entry
     * @return void
     */
    protected function add_tags_to_person( $person_response, $tags, $entry ) {
        if ( ! empty( $tags ) ) {
            $person = json_decode( wp_remote_retrieve_body( $person_response ), true );
            $person_id = $person['person']['id'];
            $response = $this->nb_api->add_tags_to_person( $person_id, $tags );

            if ( ! is_wp_error( $response ) ) {
                $code = wp_remote_retrieve_response_code( $response );
                if ( $code >= 200 && $code < 300 ) {
                    $note = __( 'Successfully added tags to person:', 'gf-nb-importer') . PHP_EOL;
                    $note .= implode( ', ', $tags );
                }

                $this->add_note( $entry['id'], $note, 'success' );
            } elseif ( is_wp_error( $response ) ) {
                $this->add_note_from_wp_error(
                    $response,
                    $entry['id'],
                    __( 'There was an error adding the tags:', 'gf-nb-importer' )
                );
            }
        }
    }

    /**
     * Add a note to the entry from a WP_Error object
     *
     * @since  0.3.0
     * @param  $wp_error
     * @param  $entry_id
     * @param  $note
     * @return void
     */
    protected function add_note_from_wp_error( $wp_error, $entry_id, $note = '' ) {
        $error_code = $wp_error->get_error_code();
        $error_message = $wp_error->get_error_message( $error_code );

        $note = empty( $note ) ? '' : $note . PHP_EOL;
        $note .= __( 'Error code: ', 'gf-nb-importer' ) . $error_code . PHP_EOL;
        $note .= __( 'Error message: ', 'gf-nb-importer' ) . $error_message;

        $this->add_note( $entry_id, $note, 'error' );
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
                        'name'                  => 'nb_tags',
                        'label'                 => __( 'Tags (comma separated)', 'gf-nb-importer' ),
                        'tooltip'               => __( 'You can use merge tags to insert values from the form.', 'gf-nb-importer' ),
                        'type'                  => 'textarea',
                        'class'                 => 'nb_tags large merge-tag-support'
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
        $access_token = $this->main->get_plugin_setting( 'oauth_access_token' );

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

    /**
     * Enqueue the stylesheet for the feed settings page
     *
     * @since  0.3.0
     * @return array
     */
    public function styles() {
        $styles = array(
            array(
                'handle'  => 'gf_nb_feed_css',
                'src'     => plugins_url( '../assets/css/feed_settings.css', __FILE__ ),
                'version' => $this->_version,
                'enqueue' => array(
                    array(
                        'admin_page' => array( 'form_settings' ),
                        'tab'        => $this->_slug
                    )
                )
            )
        );

        return array_merge( parent::styles(), $styles );
    }

}
