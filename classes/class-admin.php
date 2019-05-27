<?php

/**
 * Handles admin interface
 */
class WP_Simple_Coupons_Admin {

	/**
	 * @var WP_Simple_Coupons_Helper
	 */
	protected $helper;

	public function __construct( WP_Simple_Coupons_Helper $helper ) {
		$this->helper = $helper;
	}

	public function register_hooks() {
		add_filter( 'manage_coupon_posts_columns', array( $this, 'manage_coupons_posts_columns' ) );
		add_filter( 'manage_coupon_posts_custom_column', array( $this, 'manage_coupon_posts_custom_column' ), 10, 2 );

		add_action( 'add_meta_boxes_coupon', array( $this, 'add_metaboxes' ) );
		add_action( 'save_post_coupon', array( $this, 'save' ) );
		add_action( 'wp_ajax_download_codes', array( $this, 'download_codes' ) );
	}

	public function manage_coupons_posts_columns( $columns ) {
		$columns['codes']    = __( 'Codes', 'wp-simple-coupons' );
		$columns['download'] = __( 'Download' );
		return $columns;
	}

	/**
	 * Renders the custom columns
	 *
	 * @param $name
	 * @param $coupon_id
	 */
	public function manage_coupon_posts_custom_column( $name, $coupon_id ) {
		switch ( $name ) {
			case 'codes':
				echo $this->render_stats_column( $coupon_id );
				break;
			case 'download':
				echo $this->render_download_link( $coupon_id );
				break;
		}
	}

	/**
	 * Renders the stats column
	 *
	 * @param $coupon_id
	 *
	 * @return string
	 */
	protected function render_stats_column( $coupon_id ) {
		$stats = $this->get_helper()->get_coupon_stats( $coupon_id );
		return sprintf(
			'<table>
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
			</table>',
			__( 'Total', 'wp-simple-coupons' ),
			$stats['total'],
			__( 'Used', 'wp-simple-coupons' ),
			$stats['used'],
			__( 'Unused', 'wp-simple-coupons' ),
			$stats['unused']
		);
	}

	/**
	 * Renders the download link
	 *
	 * @param $coupon_id
	 *
	 * @return string
	 */
	protected function render_download_link( $coupon_id ) {
		$ajax_url = add_query_arg(
			array(
				'action'  => 'download_codes',
				'post_id' => $coupon_id,
			),
			admin_url( 'admin-ajax.php' )
		);

		return sprintf(
			'<a href="%s">%s</a>',
			wp_nonce_url( $ajax_url, 'download_codes' ),
			__( 'Download' )
		);
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
					<td>%s</td>
				</tr>
			</table>',
			__( 'Total', 'wp-simple-coupons' ),
			$stats['total'],
			__( 'Used', 'wp-simple-coupons' ),
			$stats['used'],
			__( 'Unused', 'wp-simple-coupons' ),
			$stats['unused'],
			$this->render_download_link( $coupon->ID )
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

	protected function get_helper() {
		return $this->helper;
	}

}

