<?php

/**
 * @wordpress-plugin
 * Plugin Name:       WP Simple Coupons
 * Plugin URI:        https://github.com/thomaslhotta/wp-simple-coupons
 * Description:       A simple coupon distribution plugin, no more, no less
 * Version:           1.0.1
 * Author:            Thomas Lhotta
 * Author URI:        https://github.com/thomaslhotta
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       WP_Simple_Coupons
 * Domain Path:       /languages
 */
class WP_Simple_Coupons {

	/**
	 * @var WP_Simple_Coupons
	 */
	protected static $instance;

	/**
	 * @var WP_Simple_Coupons_Helper
	 */
	protected $helper;

	/**
	 * @var WP_Simple_Coupons_Admin
	 */
	protected $admin;

	/**
	 * @var WP_Simple_Coupons_Shortcodes
	 */
	protected $shortcodes;

	/**
	 * Returns the singleton instance
	 *
	 * @return WP_Simple_Coupons
	 */
	public static function get_instance() {
		if ( ! self::$instance instanceof self ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	protected function __construct() {}

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );

		require_once __DIR__ . '/classes/class-shortcode.php';
		$this->shortcodes = new WP_Simple_Coupons_Shortcodes( $this->get_helper() );
		$this->shortcodes->register_hooks();

		require_once __DIR__ . '/classes/class-admin.php';
		$this->shortcodes = new WP_Simple_Coupons_Admin( $this->get_helper() );
		$this->shortcodes->register_hooks();
	}

	/**
	 * Registers the coupon post type
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'Coupons', 'wp-simple-coupons' ),
			'singular_name'      => __( 'Coupons', 'wp-simple-coupons' ),
			'add_new'            => __( 'Add Coupon', 'wp-simple-coupons' ),
			'new_item'           => __( 'New Coupon', 'wp-simple-coupons' ),
			'edit_item'          => __( 'Edit Coupon', 'wp-simple-coupons' ),
			'view_item'          => __( 'View Coupons', 'wp-simple-coupons' ),
			'all_items'          => __( 'All Coupons', 'wp-simple-coupons' ),
			'search_items'       => __( 'Search coupons', 'wp-simple-coupons' ),
			'not_found'          => __( 'No coupons found.', 'wp-simple-coupons' ),
			'not_found_in_trash' => __( 'No coupons found in trash.', 'wp-simple-coupons' ),
		);

		$args = array(
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'query_var'       => false,
			'capability_type' => 'post',
			'has_archive'     => false,
			'hierarchical'    => false,
			'menu_position'   => null,
			'supports'        => array( 'title' ),
		);

		register_post_type( 'coupon', $args );
	}

	/**
	 * Creates the global coupon table
	 */
	public static function setup_table() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = 'CREATE TABLE ' . self::get_instance()->get_helper()->get_coupon_codes_table() . ' (
				id bigint unsigned NOT NULL AUTO_INCREMENT,
				blog_id bigint NOT NULL,
				post_id bigint NOT NULL,
				association_id bigint,
				used bigint NOT NULL,
				code varchar(32) NOT NULL,
				PRIMARY KEY  (id),
				KEY blog_id (blog_id),
				KEY post_id (post_id),
				KEY association_id (association_id)
		) ' . $charset_collate . ';';

		dbDelta( $sql );
	}

	/**
	 * Uninstalls the plugin
	 */
	public static function uninstall() {
		global $wpdb;
		// @todo Also delete posts
		$wpdb->query( 'DROP TABLE ' . self::get_instance()->get_helper()->get_coupon_codes_table() );
	}

	/**
	 * Returns the helper object
	 *
	 * @return WP_Simple_Coupons_Helper
	 */
	public function get_helper() {
		if ( ! $this->helper instanceof WP_Simple_Coupons_Helper ) {
			require_once __DIR__ . '/classes/class-helper.php';
			$this->helper = new WP_Simple_Coupons_Helper();
		}

		return $this->helper;
	}
}

WP_Simple_Coupons::get_instance()->register_hooks();
do_action( 'wp_simple_coupons_load', WP_Simple_Coupons::get_instance() );

register_activation_hook( __FILE__, array( 'WP_Simple_Coupons', 'setup_table' ) );
register_uninstall_hook( __FILE__, array( 'WP_Simple_Coupons', 'uninstall' ) );
