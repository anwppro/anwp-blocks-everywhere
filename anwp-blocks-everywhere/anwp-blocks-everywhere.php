<?php
/**
 * Plugin Name: AnWP Blocks Everywhere
 * Description: Put Custom Blocks on any action hook.
 * Version:     1.0.0
 * Author:      Andrei Strekozov <anwp.pro>
 * Author URI:  https://anwp.pro
 * License:     GPLv2+
 * Requires PHP: 7.4
 * Text Domain: anwp-blocks-everywhere
 * Domain Path: /languages
 *
 * @link    https://anwp.pro
 *
 * @package AnWP_Blocks_Everywhere
 * @version 1.0.0
 *
 * Built using generator-plugin-wp (https://github.com/WebDevStudios/generator-plugin-wp)
 */

/**
 * Copyright (c) 2025 Andrei Strekozov <anwp.pro> (email : anwp.pro@gmail.com)
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

if ( ! defined( 'ABSPATH' ) ) {
	die;
}

if ( ! class_exists( 'AnWP_', false ) ) {

	/**
	 * Main initiation class.
	 */
	final class AnWP_Blocks_Everywhere {

		/**
		 * Current version.
		 */
		const VERSION = '1.0.0';

		/**
		 * URL of plugin directory.
		 */
		protected $url = '';

		/**
		 * Path of plugin directory.
		 */
		protected $path = '';

		/**
		 * Plugin basename.
		 */
		protected $basename = '';

		/**
		 * Singleton instance of plugin.
		 *
		 * @var    AnWP_Blocks_Everywhere
		 */
		protected static $single_instance = null;

		/**
		 * Creates or returns an instance of this class.
		 *
		 * @return  AnWP_Blocks_Everywhere A single instance of this class.
		 */
		public static function get_instance() {

			if ( null === self::$single_instance ) {
				self::$single_instance = new self();
			}

			return self::$single_instance;
		}

		/**
		 * Sets up our plugin.
		 */
		protected function __construct() {
			$this->basename = plugin_basename( __FILE__ );
			$this->url      = plugin_dir_url( __FILE__ );
			$this->path     = plugin_dir_path( __FILE__ );
		}

		/**
		 * Add hooks and filters.
		 * Priority needs to be
		 * < 10 for CPT_Core,
		 * < 5 for Taxonomy_Core,
		 * and 0 for Widgets because widgets_init runs at init priority 1.
		 */
		public function hooks() {
			add_action( 'init', [ $this, 'init' ] );

			add_action( 'init', [ $this, 'register_post_type' ] );
			add_action( 'init', [ $this, 'register_meta' ] );

			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_assets' ] );

			add_action( 'manage_anwp_be_posts_custom_column', [ $this, 'columns_display' ], 10, 2 );
			add_filter( 'manage_edit-anwp_be_columns', [ $this, 'columns' ] );
		}

		/**
		 * Registers admin columns to display.
		 *
		 * @param array $columns Array of registered column names/labels.
		 *
		 * @return array          Modified array.
		 */
		public function columns( $columns ) {

			// Add new columns
			$new_columns = [
				'anwp_be_hook' => esc_html__( 'Hook', 'anwp-blocks-everywhere' ),
			];

			return array_merge( $columns, $new_columns );
		}

		/**
		 * Handles admin column display.
		 *
		 * @param array   $column  Column currently being rendered.
		 * @param integer $post_id ID of post to display column for.
		 */
		public function columns_display( $column, $post_id ) {
			if ( 'anwp_be_hook' === $column ) {
				echo esc_html( get_post_meta( $post_id, '_anwp_be_hook', true ) );
			}
		}

		/**
		 * Register Custom Post Type
		 */
		public function register_post_type() {

			// Register this CPT.
			$labels = [
				'name'          => _x( 'Blocks Everywhere', 'Post type general name', 'anwp-blocks-everywhere' ),
				'singular_name' => _x( 'Blocks Everywhere', 'Post type singular name', 'anwp-blocks-everywhere' ),
				'edit_item'     => __( 'Edit Blocks Everywhere', 'anwp-blocks-everywhere' ),
				'view_item'     => __( 'View Blocks Everywhere', 'anwp-blocks-everywhere' ),
				'all_items'     => __( 'Blocks Everywhere', 'anwp-blocks-everywhere' ),
			];

			$args = [
				'labels'              => $labels,
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'query_var'           => false,
				'show_in_rest'        => true,
				'capability_type'     => 'post',
				'capabilities'        => [
					'edit_post'         => 'manage_options',
					'read_post'         => 'manage_options',
					'delete_post'       => 'manage_options',
					'edit_posts'        => 'manage_options',
					'edit_others_posts' => 'manage_options',
					'delete_posts'      => 'manage_options',
					'publish_posts'     => 'manage_options',
				],
				'menu_icon'           => 'dashicons-menu',
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => [ 'title', 'editor', 'custom-fields' ],
			];

			register_post_type( 'anwp_be', $args );
		}

		/**
		 * Register meta field
		 *
		 * @return void
		 */
		public function register_meta() {
			register_meta(
				'post',
				'_anwp_be_hook',
				[
					'object_subtype'    => 'anwp_be',
					'single'            => true,
					'type'              => 'string',
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_text_field',
					'auth_callback'     => function () {
						return current_user_can( 'edit_posts' );
					},
				]
			);
		}

		/**
		 * Enqueue block scripts
		 *
		 * @return void
		 */
		public function enqueue_block_assets() {
			if ( ! empty( get_current_screen() ) && 'anwp_be' === get_current_screen()->id ) {
				$asset_file = self::include_file( 'build/sidebar/index.asset' );

				wp_enqueue_script( 'anwp-be-block-scripts', self::url( 'build/sidebar/index.js' ), $asset_file['dependencies'], $asset_file['version'], false );
			}
		}

		/**
		 * Init hooks
		 */
		public function init() {

			// Load translated strings for plugin.
			load_plugin_textdomain( 'anwp-blocks-everywhere', false, dirname( $this->basename ) . '/languages/' );
		}

		/**
		 * Magic getter for our object.
		 *
		 * @param string $field Field to get.
		 *
		 * @return mixed         Value of the field.
		 * @throws Exception     Throws an exception if the field is invalid.
		 */
		public function __get( $field ) {
			switch ( $field ) {
				case 'version':
					return self::VERSION;
				case 'basename':
				case 'url':
				case 'path':
					return $this->$field;
				default:
					throw new Exception( 'Invalid ' . __CLASS__ . ' property: ' . $field );
			}
		}

		/**
		 * Include a file from the includes directory.
		 *
		 * @param string $filename Name of the file to be included.
		 *
		 * @return boolean          Result of include call.
		 */
		public static function include_file( $filename ) {
			$file = self::dir( $filename . '.php' );
			if ( file_exists( $file ) ) {
				return include_once $file;
			}

			return false;
		}

		/**
		 * This plugin's directory.
		 *
		 * @param string $path (optional) appended path.
		 *
		 * @return string       Directory and path.
		 */
		public static function dir( $path = '' ) {
			static $dir;
			$dir = $dir ?: trailingslashit( dirname( __FILE__ ) );

			return $dir . $path;
		}

		/**
		 * This plugin's url.
		 *
		 * @param string $path (optional) appended path.
		 *
		 * @return string       URL and path.
		 */
		public static function url( $path = '' ) {
			static $url;
			$url = $url ?: trailingslashit( plugin_dir_url( __FILE__ ) );

			return $url . $path;
		}
	}

	/**
	 * Grab the AnWP_Blocks_Everywhere object and return it.
	 * Wrapper for AnWP_Blocks_Everywhere::get_instance().
	 *
	 * @return AnWP_Blocks_Everywhere Singleton instance of plugin class.
	 */
	function anwp_blocks_everywhere() {
		return AnWP_Blocks_Everywhere::get_instance();
	}

	// Kick it off.
	add_action( 'plugins_loaded', [ anwp_blocks_everywhere(), 'hooks' ] );
}
