<?php
/**
 * Importer for Gravity Forms and NationBuilder Gravity Forms Feed
 * @version 0.2.1
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
            if ( wp_remote_retrieve_response_code( $response ) === 200 ) {
                $note = __( 'Successfully updated this entry in NationBuilder.', 'gf-nb-importer');
            } elseif ( wp_remote_retrieve_response_code( $response ) === 201 ) {
                $note = __( 'Successfully created this entry in NationBuilder.', 'gf-nb-importer');
            }

            $this->add_note( $entry['id'], $note, 'success' );
        } elseif ( is_wp_error( $response ) ) {
            $error_code = $response->get_error_code();
            $error_message = $response->get_error_message( $error_code );

            $note = __( 'There was an error pushing this entry to NationBuilder: ', 'gf-nb-importer' );
            $note .= PHP_EOL;
            $note .= __( 'Error code: ', 'gf-nb-importer' ) . $error_code . PHP_EOL;
            $note .= __( 'Error message: ', 'gf-nb-importer' ) . $error_message;

            $this->add_note( $entry['id'], $note, 'error' );
        }
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

}
