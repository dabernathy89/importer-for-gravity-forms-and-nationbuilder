<?php
/**
 * Plugin Name: Importer for Gravity Forms and NationBuilder
 * Plugin URI:  https://www.danielabernathy.com
 * Description: Automatically import entries from Gravity Forms into NationBuilder.
 * Version:     0.3.6
 * Author:      dabernathy89
 * Author URI:  https://www.danielabernathy.com
 * Donate link: https://www.danielabernathy.com
 * License:     GPLv2
 * Text Domain: gf-nb-importer
 * Domain Path: /languages
 */

/**
 * Copyright (c) 2016 dabernathy89 (email : daniel@danielabernathy.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * Built using generator-plugin-wp
 */

require('includes/vendor/OAuth2/Client.php');
require('includes/vendor/OAuth2/GrantType/IGrantType.php');
require('includes/vendor/OAuth2/GrantType/AuthorizationCode.php');

/**
 * Autoloads files with classes when needed
 *
 * @since  0.1.0
 * @param  string $class_name Name of the class being requested.
 * @return void
 */
function gf_nb_importer_autoload_classes( $class_name ) {
	if ( 0 !== strpos( $class_name, 'GFNBI_' ) ) {
		return;
	}

	$filename = strtolower( str_replace(
		'_', '-',
		substr( $class_name, strlen( 'GFNBI_' ) )
	) );

	GF_NB_Importer::include_file( $filename );
}
spl_autoload_register( 'gf_nb_importer_autoload_classes' );


/**
 * Main initiation class
 *
 * @since  0.1.0
 * @var  string $version  Plugin version
 * @var  string $basename Plugin basename
 * @var  string $url      Plugin URL
 * @var  string $path     Plugin Path
 */
class GF_NB_Importer {

	/**
	 * Current version
	 *
	 * @var  string
	 * @since  0.1.0
	 */
	const VERSION = '0.3.6';

	/**
	 * URL of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $url = '';

	/**
	 * Path of plugin directory
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $path = '';

	/**
	 * Plugin basename
	 *
	 * @var string
	 * @since  0.1.0
	 */
	protected $basename = '';

	/**
	 * Plugin slug
	 *
	 * @var string
	 * @since 0.1.0
	 */
	protected $slug = 'gf_nb_importer';

	/**
	 * Singleton instance of plugin
	 *
	 * @var GF_NB_Importer
	 * @since  0.1.0
	 */
	protected static $single_instance = null;

	/**
	 * Instance of GFNBI_Gravity_Forms_Feed
	 *
	 * @since 0.1.0
	 * @var GFNBI_Gravity_Forms_Feed
	 */
	protected $gravity_forms_feed;

	/**
	 * Instance of GFNBI_Gravity_Forms_Main
	 *
	 * @since 0.2.0
	 * @var GFNBI_Gravity_Forms_Main
	 */
	protected $gravity_forms_main;

	/**
	 * Instance of GFNBI_Nb_Api
	 *
	 * @since 0.2.0
	 * @var GFNBI_Nb_Api
	 */
	protected $nb_api;

	/**
	 * Creates or returns an instance of this class.
	 *
	 * @since  0.1.0
	 * @return GF_NB_Importer A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$single_instance ) {
			self::$single_instance = new self();
		}

		return self::$single_instance;
	}

	/**
	 * Sets up our plugin
	 *
	 * @since  0.1.0
	 */
	protected function __construct() {
		$this->basename = plugin_basename( __FILE__ );
		$this->url      = plugin_dir_url( __FILE__ );
		$this->path     = plugin_dir_path( __FILE__ );
	}

	/**
	 * Attach other plugin classes to the base plugin class.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function plugin_classes() {
		// Attach other plugin classes to the base plugin class.
		if (class_exists('GFForms')) {
			GFForms::include_feed_addon_framework();

			$this->gravity_forms_main = new GFNBI_Gravity_Forms_Main( $this );
			$this->nb_api = new GFNBI_Nb_Api( $this, $this->gravity_forms_main );
			$this->gravity_forms_feed = new GFNBI_Gravity_Forms_Feed( $this, $this->gravity_forms_main, $this->nb_api );

			GFAddOn::register( 'GFNBI_Gravity_Forms_Main' );
			GFAddOn::register( 'GFNBI_Gravity_Forms_Feed' );
		}
	} // END OF PLUGIN CLASSES FUNCTION

	/**
	 * Add hooks and filters
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'init' ), 5 );
	}

	/**
	 * Init hooks
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public function init() {
		load_plugin_textdomain( 'gf-nb-importer', false, dirname( $this->basename ) . '/languages/' );
		$this->plugin_classes();
	}

	/**
	 * Magic getter for our object.
	 *
	 * @since  0.1.0
	 * @param string $field Field to get.
	 * @throws Exception Throws an exception if the field is invalid.
	 * @return mixed
	 */
	public function __get( $field ) {
		switch ( $field ) {
			case 'version':
				return self::VERSION;
			case 'slug':
			case 'basename':
			case 'url':
			case 'path':
			case 'gravity_forms_feed':
			case 'gravity_forms_main':
			case 'nb_api':
				return $this->$field;
			default:
				throw new Exception( 'Invalid '. __CLASS__ .' property: ' . $field );
		}
	}

	/**
	 * Include a file from the includes directory
	 *
	 * @since  0.1.0
	 * @param  string $filename Name of the file to be included.
	 * @return bool   Result of include call.
	 */
	public static function include_file( $filename ) {
		$file = self::dir( 'includes/class-'. $filename .'.php' );
		if ( file_exists( $file ) ) {
			return include_once( $file );
		}
		return false;
	}

	/**
	 * This plugin's directory
	 *
	 * @since  0.1.0
	 * @param  string $path (optional) appended path.
	 * @return string       Directory and path
	 */
	public static function dir( $path = '' ) {
		static $dir;
		$dir = $dir ? $dir : trailingslashit( dirname( __FILE__ ) );
		return $dir . $path;
	}

	/**
	 * This plugin's url
	 *
	 * @since  0.1.0
	 * @param  string $path (optional) appended path.
	 * @return string       URL and path
	 */
	public static function url( $path = '' ) {
		static $url;
		$url = $url ? $url : trailingslashit( plugin_dir_url( __FILE__ ) );
		return $url . $path;
	}
}

/**
 * Grab the GF_NB_Importer object and return it.
 * Wrapper for GF_NB_Importer::get_instance()
 *
 * @since  0.1.0
 * @return GF_NB_Importer  Singleton instance of plugin class.
 */
function gf_nb_importer() {
	return GF_NB_Importer::get_instance();
}

// Kick it off.
add_action( 'plugins_loaded', array( gf_nb_importer(), 'hooks' ), 5 );
