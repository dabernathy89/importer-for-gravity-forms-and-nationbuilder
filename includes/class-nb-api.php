<?php
/**
 * Importer for Gravity Forms and NationBuilder Nb Api
 * @version 0.3.2
 * @package Importer for Gravity Forms and NationBuilder
 */

class GFNBI_Nb_Api {
	/**
	 * Parent plugin class
	 *
	 * @var   class
	 * @since 0.2.0
	 */
	protected $plugin = null;

	/**
	 * Main Gravity Forms add-on class
	 *
	 * @var   class
	 * @since 0.2.0
	 */
	protected $gf_main = null;

	/**
	 * Constructor
	 *
	 * @since  0.2.0
	 * @param  object $plugin Main plugin object.
	 * @return void
	 */
	public function __construct( $plugin, $gf_main ) {
		$this->plugin = $plugin;
		$this->gf_main = $gf_main;
	}

    /**
     * Create or update a person
     *
     * @since  0.2.0
     * @param  $data
     * @return array|WP_Error
     */
	public function push_person( $data ) {
		return $this->make_request( 'people/push', 'PUT', $data );
	}

    /**
     * Add tags to a person
     *
     * @since  0.3.0
     * @param  $person_id
     * @param  $tags
     * @return array|WP_Error
     */
    public function add_tags_to_person( $person_id, $tags ) {
        $data = array(
            'tagging' => array(
                'tag' => $tags
            )
        );

        return $this->make_request( "people/{$person_id}/taggings", 'PUT', $data );
    }

	/**
	 * Make a request to NationBuilder
	 *
	 * @since  0.2.0
	 * @param  $endpoint
     * @param  $method
     * @param  $data
	 * @return array|WP_Error
	 */
	protected function make_request( $endpoint = '', $method = 'GET', $data = null ) {
		$access_token = $this->gf_main->get_plugin_setting( 'oauth_access_token' );
		$nation_slug = $this->gf_main->get_plugin_setting( 'nation_slug' );

		if ( ! $access_token ) {
			return new WP_Error( 'no_access_token', __( 'No access token exists for the NationBuilder API.', 'gf-nb-importer' ) );
		}

		if ( ! $nation_slug ) {
			return new WP_Error( 'no_nation_slug', __( 'No slug was defined for the nation.', 'gf-nb-importer' ) );
		}

        $response = wp_remote_request(
            "https://{$nation_slug}.nationbuilder.com/api/v1/" . $endpoint,
            array(
                'method' => $method,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'User-Agent' => 'NB_Gravity'
                ),
                'body' => $data ? json_encode( $data ) : null
            )
        );

        if ( ! is_wp_error( $response ) ) {
        	$code = wp_remote_retrieve_response_code( $response );

            if ( $code >= 200 && $code < 300 ) {
            	return $response;
            } else {
                $body = json_decode( $response['body'], true );
                $error_code = isset( $body['code'] ) ? $body['code'] : 'unknown_error';
                $error_message = isset( $body['message'] ) ? $body['message'] : __( 'An unknown error occurred when accessing the NationBuilder API.', 'gf-nb-importer' );

                return new WP_Error( $error_code, $error_message );
            }
        } elseif ( is_wp_error( $response ) ) {
            return $response;
        }
	}
}
