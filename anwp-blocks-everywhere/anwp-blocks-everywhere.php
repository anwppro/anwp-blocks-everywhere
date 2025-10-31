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

if ( ! class_exists( 'AnWP_Blocks_Everywhere', false ) ) {

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
		 */
		public function hooks() {
			add_action( 'init', [ $this, 'init' ] );

			add_action( 'init', [ $this, 'register_post_type' ] );
			add_action( 'init', [ $this, 'register_meta' ] );

			add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_assets' ] );

			add_action( 'manage_anwp_be_posts_custom_column', [ $this, 'columns_display' ], 10, 2 );
			add_filter( 'manage_edit-anwp_be_columns', [ $this, 'columns' ] );
			add_filter( 'manage_edit-anwp_be_sortable_columns', [ $this, 'sortable_columns' ] );

			// Register dynamic hooks for rendering blocks
			add_action( 'wp', [ $this, 'register_dynamic_hooks' ] );

			// Cache invalidation
			add_action( 'save_post_anwp_be', [ $this, 'clear_blocks_cache' ] );
			add_action( 'before_delete_post', [ $this, 'clear_blocks_cache_on_delete' ] );
			add_action( 'wp_trash_post', [ $this, 'clear_blocks_cache_on_delete' ] );

			// Publish validation
			add_action( 'transition_post_status', [ $this, 'validate_on_publish' ], 10, 3 );
			add_action( 'admin_notices', [ $this, 'show_validation_errors' ] );
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
				'anwp_be_hook'     => esc_html__( 'Hook', 'anwp-blocks-everywhere' ),
				'anwp_be_priority' => esc_html__( 'Priority', 'anwp-blocks-everywhere' ),
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
			} elseif ( 'anwp_be_priority' === $column ) {
				$priority = get_post_meta( $post_id, '_anwp_be_priority', true );
				echo esc_html( $priority ?: '10' );
			}
		}

		/**
		 * Define sortable columns.
		 *
		 * @param array $columns Array of registered column names.
		 *
		 * @return array Modified array.
		 */
		public function sortable_columns( $columns ) {
			$columns['anwp_be_hook']     = 'anwp_be_hook';
			$columns['anwp_be_priority'] = 'anwp_be_priority';
			return $columns;
		}

		/**
		 * Validate post on publish to ensure hook is set
		 *
		 * @param string  $new_status New post status.
		 * @param string  $old_status Old post status.
		 * @param WP_Post $post       Post object.
		 *
		 * @return void
		 */
		public function validate_on_publish( $new_status, $old_status, $post ) {
			if ( 'anwp_be' !== $post->post_type ) {
				return;
			}

			if ( 'publish' === $new_status && 'publish' !== $old_status ) {
				$hook = get_post_meta( $post->ID, '_anwp_be_hook', true );

				if ( empty( $hook ) ) {
					// Prevent publishing
					wp_update_post( [
						'ID'          => $post->ID,
						'post_status' => 'draft',
					] );

					// Show admin notice
					set_transient( 'anwp_be_validation_error_' . $post->ID, __( 'Cannot publish: Please specify an action hook.', 'anwp-blocks-everywhere' ), 30 );
				}
			}
		}

		/**
		 * Show validation error notices in admin
		 *
		 * @return void
		 */
		public function show_validation_errors() {
			$post_id = get_the_ID();
			if ( $error = get_transient( 'anwp_be_validation_error_' . $post_id ) ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $error ) . '</p></div>';
				delete_transient( 'anwp_be_validation_error_' . $post_id );
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
				'menu_position'       => 20,
				'has_archive'         => false,
				'hierarchical'        => false,
				'supports'            => [ 'title', 'editor', 'custom-fields', 'page-attributes' ],
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
						return current_user_can( 'manage_options' );
					},
				]
			);

			register_meta(
				'post',
				'_anwp_be_priority',
				[
					'object_subtype'    => 'anwp_be',
					'single'            => true,
					'type'              => 'integer',
					'show_in_rest'      => true,
					'default'           => 10,
					'sanitize_callback' => 'absint',
					'auth_callback'     => function () {
						return current_user_can( 'manage_options' );
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

				if ( ! $asset_file || ! is_array( $asset_file ) ) {
					return;
				}

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
		 * Get all blocks data from database with caching
		 *
		 * @return array Array of objects with post_id, hook_name, priority, content
		 */
		public function get_blocks_data() {
			// Try to get cached data
			$cached = get_transient( 'anwp_be_blocks_data' );
			if ( false !== $cached ) {
				return $cached;
			}

			// Query all published posts
			$args = [
				'post_type'      => 'anwp_be',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'meta_query'     => [
					[
						'key'     => '_anwp_be_hook',
						'value'   => '',
						'compare' => '!=',
					],
				],
			];

			$posts = get_posts( $args );
			$blocks_data = [];

			foreach ( $posts as $post ) {
				$hook_name = get_post_meta( $post->ID, '_anwp_be_hook', true );
				$priority  = (int) get_post_meta( $post->ID, '_anwp_be_priority', true ) ?: 10;

				if ( ! empty( $hook_name ) ) {
					$blocks_data[] = [
						'post_id'  => $post->ID,
						'hook'     => sanitize_text_field( $hook_name ),
						'priority' => $priority,
						'content'  => $post->post_content,
					];
				}
			}

			// Cache for 12 hours
			set_transient( 'anwp_be_blocks_data', $blocks_data, 12 * HOUR_IN_SECONDS );

			return $blocks_data;
		}

		/**
		 * Register dynamic action hooks for block rendering
		 *
		 * @return void
		 */
		public function register_dynamic_hooks() {
			// Only run on frontend template requests
			if ( is_admin() || wp_doing_ajax() || wp_is_json_request() ) {
				return;
			}

			$blocks_data = $this->get_blocks_data();

			foreach ( $blocks_data as $block_data ) {
				add_action(
					$block_data['hook'],
					function () use ( $block_data ) {
						$this->render_blocks_content( $block_data );
					},
					$block_data['priority']
				);
			}
		}

		/**
		 * Render blocks content for a specific post
		 *
		 * @param array $block_data Block data array with post_id, content, etc.
		 *
		 * @return void
		 */
		public function render_blocks_content( $block_data ) {
			if ( empty( $block_data['content'] ) ) {
				return;
			}

			// Apply content filters and render blocks
			$content = apply_filters( 'the_content', $block_data['content'] );

			// Allow filtering before output
			$content = apply_filters( 'anwp_be_render_content', $content, $block_data );

			echo $content;
		}

		/**
		 * Clear blocks data cache
		 *
		 * @return void
		 */
		public function clear_blocks_cache() {
			delete_transient( 'anwp_be_blocks_data' );
		}

		/**
		 * Clear blocks data cache only for anwp_be post type
		 *
		 * @param int $post_id Post ID being deleted/trashed.
		 *
		 * @return void
		 */
		public function clear_blocks_cache_on_delete( $post_id ) {
			if ( 'anwp_be' === get_post_type( $post_id ) ) {
				$this->clear_blocks_cache();
			}
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
