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

	protected function __construct() {
	}

	public function register_hooks() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'add_meta_boxes_coupon', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_coupon', array( $this, 'save' ) );
		add_action( 'wp_ajax_download_codes', array( $this, 'download_codes' ) );

		require_once __DIR__ . '/classes/class-shortcode.php';
		$this->shortcodes = new WP_Simple_Coupons_Shortcodes( $this->get_helper() );
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

		$sql  = 'CREATE TABLE IF NOT EXISTS ' . self::get_instance()->get_helper()->get_coupon_codes_table() . ' (';
		$sql .= 'id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,';
		$sql .= 'blog_id bigint(20) NOT NULL,';
		$sql .= 'post_id bigint(20) NOT NULL,';
		$sql .= 'association_id bigint(20),';
		$sql .= 'used bigint(20) NOT NULL,';
		$sql .= 'code varchar(32) NOT NULL,';
		$sql .= 'UNIQUE KEY id (id),';
		$sql .= 'KEY blog_id (blog_id),';
		$sql .= 'KEY post_id (post_id),';
		$sql .= 'KEY association_id (association_id)';
		$sql .= ') ' . $charset_collate . ';';

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
	 * Adds meta boxes to coupons editing screen
	 */
	public function add_metaboxes() {
		add_meta_box(
			'codes',
			__( 'Codes' ),
			array( $this, 'render_codes_metabox' )
		);

		add_meta_box(
			'edit-codes',
			__( 'Edit codes' ),
			array( $this, 'render_edit_metabox' )
		);
	}

	/**
	 * Renders the codes metabox
	 *
	 * @param WP_Post $coupon
	 */
	public function render_codes_metabox( WP_Post $coupon ) {
		$stats = $this->get_helper()->get_coupon_stats( $coupon->ID );

		$ajax_url = add_query_arg(
			array(
				'action'  => 'download_codes',
				'post_id' => $coupon->ID,
			),
			admin_url( 'admin-ajax.php' )
		);

		printf(
			'<table class="form-table">
				<tr>
					<th>%s</th>
					<td>%s</td>
				</tr>
				<tr>
					<th>%s</th>
					<td>%s</td>
				</tr>
				<tr>
					<th>%s</th>
					<td>%s</td>
				</tr>
				<tr>
					<th></th>
					<td><a href="%s">%s</a></td>
				</tr>
			</table>',
			__( 'Total', 'wp-simple-coupons' ),
			$stats['total'],
			__( 'Used', 'wp-simple-coupons' ),
			$stats['used'],
			__( 'Unused', 'wp-simple-coupons' ),
			$stats['unused'],
			wp_nonce_url( $ajax_url, 'download_codes' ),
			__( 'Download', 'wp-simple-coupons' )
		);
	}

	/**
	 * Creates a CSV download
	 */
	public function download_codes() {
		check_ajax_referer( 'download_codes' );

		$post = get_post( $_REQUEST['post_id'] );
		if ( ! $post instanceof WP_Post ) {
			http_response_code( 404 );
			exit;
		}

		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			http_response_code( 403 );
			exit;
		}

		// Set headers
		header( 'Content-Description: File Transfer' );
		header( 'Content-type: text/csv' );
		header( 'Content-Disposition: attachment; filename=' . $post->post_name . '.csv' );
		header( 'Expires: 0' );
		header( 'Pragma: public' );

		// Begin CSV output
		$fh = fopen( 'php://output', 'w' );
		fputcsv(
			$fh,
			array(
				__( 'Code', 'wp-simple-coupons' ),
				__( 'Associated ID', 'wp-simple-coupons' ),
			)
		);

		global $wpdb;

		$table = $this->get_helper()->get_coupon_codes_table();

		$codes = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT code, association_id FROM $table WHERE blog_id = %d AND post_id = %d",
				get_current_blog_id(),
				$post->ID
			),
			ARRAY_A
		);

		foreach ( $codes as $code ) {
			fputcsv( $fh, $code );
		}

		fclose( $fh );
		exit();
	}

	/**
	 * Renders the editing metabox
	 *
	 * @param WP_Post $coupon
	 */
	public function render_edit_metabox( WP_Post $coupon ) {
		printf(
			'<table class="form-table">
				<tr>
					<th>%s</th>
					<td>
						<label>
							<input type="number" id="generate_number" name="generate_number" min="0" max="5000" step="1">
							%s	
						</label>
						<label>
							%s
							<input type="number" id="generate_number" name="generate_length" min="3" max="32" step="1">
						</label><br><br>
								
						<input type="submit" class="btn-secondary" name="generate" value="%s">
				</tr>
				<tr>
					<th>%s</th>
					<td>
						<textarea name="upload_text" class="widefat" placeholder="%s"></textarea>
						<input type="file" name="upload_file"><br><br>
						<input type="submit" class="btn-secondary" name="upload" value="%s">
					</td>
				</tr>
				<tr>
					<th>%s</th>
					<td>
						<textarea name="delete_text" class="widefat" placeholder="%s"></textarea>
						<input type="submit" class="btn-secondary" name="delete" value="%s">
					</td>
				</tr>
			</table>',
			esc_html__( 'Generate codes', 'wp-simple-coupons' ),
			esc_html__( 'Codes', 'wp-simple-coupons' ),
			esc_html__( 'with a length of ', 'wp-simple-coupons' ),
			esc_attr__( 'Generate', 'wp-simple-coupons' ),
			esc_html__( 'Upload codes', 'wp-simple-coupons' ),
			esc_attr__( 'Paste codes here or chose a file.', 'wp-simple-coupons' ),
			esc_attr__( 'Upload', 'wp-simple-coupons' ),
			esc_html__( 'Delete codes', 'wp-simple-coupons' ),
			esc_html__( 'Paste codes here to delete them', 'wp-simple-coupons' ),
			esc_attr__( 'Delete', 'wp-simple-coupons' )
		);
	}

	public function save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			 return;
		}

		if ( ! empty( $_POST['generate'] ) ) {
			if ( empty( $_POST['generate_number'] ) ) {
				return;
			}

			$coupons = $this->get_helper()->generate_coupons(
				intval( $_POST['generate_number'] ),
				empty( $_POST['generate_length'] ) ? 6 : intval( $_POST['generate_length'] ),
				$this->get_helper()->get_existing_coupon_codes( $post_id )
			);

			$this->get_helper()->add_coupons( $coupons, $post_id );
		} else if ( ! empty( $_POST['upload'] ) ) {
			if ( ! empty( $_POST['upload_text'] ) ) {
				$this->get_helper()->add_coupons(
					$this->get_helper()->parse_coupons(
						$_POST['upload_text'],
						$this->get_helper()->get_existing_coupon_codes( $post_id )
					),
					$post_id
				);
			} else if ( isset( $_FILES['upload_file'] ) ) {
				$this->get_helper()->add_coupons(
					$this->get_helper()->parse_coupons(
						array_map( 'str_getcsv', file( $_FILES['upload_file']['tmp_name'] ) ),
						$this->get_helper()->get_existing_coupon_codes( $post_id )
					),
					$post_id
				);
			}
		} else if ( ! empty( $_POST['delete'] ) && ! empty( $_POST['delete_text'] ) ) {
			$this->get_helper()->delete_coupons(
				$this->get_helper()->parse_coupons( $_POST['delete_text'] ),
				$post_id
			);
		}
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
