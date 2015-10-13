<?php

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    motaword
 * @subpackage motaword/includes
 * @author     Oytun Tez <oytun@motaword.com>
 */
class MotaWord_i18n {

	/**
	 * @var Array           see ./languages/languages.php
	 */
	public static $languages;
	/**
	 * @var Array
	 */
	public static $currencies;

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'motaword',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

	public static function getLanguage( $code ) {
		if ( ! static::$languages ) {
			static::$languages = require( plugin_dir_path( __FILE__ ) . '../languages/languages.php' );
		}

		return static::$languages[ $code ];
	}

	public static function getCurrency( $code ) {
		if ( ! static::$currencies ) {
			static::$currencies = require( plugin_dir_path( __FILE__ ) . '../languages/currencies.php' );
		}

		return static::$currencies[ $code ];
	}

}
