<?php

/**
 * Contains helper functions for dealing with coupon codes
 */
class WP_Simple_Coupons_Helper {
	/**
	 * Returns all coupon post
	 *
	 * @return WP_Post[]
	 */
	public function get_all_coupons() {
		return get_posts(
			array(
				'post_type' => 'coupon',
				'posts_per_page' => -1,
			)
		);
	}

	/**
	 * Returns the coupon stats
	 *
	 * @param $coupon_id
	 *
	 * @return array
	 */

	public function get_coupon_stats( $coupon_id ) {
		global $wpdb;

		$table = $this->get_coupon_codes_table();

		$unused = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( code ) FROM $table WHERE blog_id = %d AND post_id = %d AND association_id IS NULL",
				get_current_blog_id(),
				$coupon_id
			)
		);

		$used = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT( code ) FROM $table WHERE blog_id = %d AND post_id = %d AND association_id IS NOT NULL",
				get_current_blog_id(),
				$coupon_id
			)
		);

		return array(
			'used'   => $used,
			'unused' => $unused,
			'total'  => $used + $unused,
		);
	}

	/**
	 * Returns an array containing the existing codes
	 *
	 * @param $post_id
	 *
	 * @return string[]
	 */
	public function get_existing_coupon_codes( $post_id ) {
		global $wpdb;

		$table = $this->get_coupon_codes_table();

		$codes = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT code FROM $table WHERE blog_id = %d AND post_id = %d",
				get_current_blog_id(),
				$post_id
			)
		);

		if ( ! is_array( $codes ) ) {
			return array();
		}

		return $codes;
	}

	/**
	 * Returns an array of coupons codes
	 *
	 * @param int $number The number of codes to generate
	 * @param int $length The length the codes should have
	 * @param array $existing An array of existing codes to prevent collisions
	 *
	 * @return string[]
	 */
	public function generate_coupons( $number, $length = 6, $existing = array() ) {
		$coupons = array();

		do {
			$gen = $this->generate_code( $length );
			if ( ! in_array( $gen, $coupons ) && ! in_array( $gen, $existing ) ) {
				$coupons[] = $gen;
			}
		} while ( count( $coupons ) < $number );

		return $coupons;
	}

	/**
	 * Generates a code with the given length. Codes ar all upper case, ambiguous characters are removed.
	 *
	 * @param int $length
	 *
	 * @return string
	 */
	public function generate_code( $length = 6 ) {
		$generated = '';

		do {
			$chars = wp_generate_password( $length, false, false );
			$chars = strtoupper( $chars );
			$chars = str_replace( array( '0', 'O', 'I', '1' ), '', $chars );
			$generated .= $chars;

		} while ( strlen( $generated ) < $length );

		return substr( $generated, 0, $length );
	}

	/**
	 * Parses a string or array of coupon codes
	 *
	 * @param array|string $input
	 * @param array $ignore
	 *
	 * @return string[]
	 */
	public function parse_coupons( $input, $ignore = array() ) {
		if ( is_string( $input ) ) {
			$input = preg_split( '/[\s,]+/', $input );
		}

		foreach ( $input as $key => $val ) {
			if ( is_array( $val ) ) {
				$val = reset( $val );
				$input[ $key ] = $val;
			}

			if ( in_array( $val, $ignore ) ) {
				unset( $input[ $key ] );
			}
		}

		return array_filter( $input );
	}

	/**
	 * Adds an array of coupon codes to the database
	 *
	 * @param array $coupons
	 * @param $post_id
	 *
	 * @return int
	 */
	public function add_coupons( array $coupons, $post_id ) {
		global $wpdb;

		$created = 0;

		foreach ( $coupons as $coupon ) {
			$created += $wpdb->insert(
				$this->get_coupon_codes_table(),
				array(
					'blog_id' => get_current_blog_id(),
					'post_id' => $post_id,
					'code'    => $coupon,
					'used'    => 0,
				),
				array( '%d', '%d', '%s', '%d' )
			);
		}

		return $created;
	}

	/**
	 * Deletes the given coupon codes from the database
	 *
	 * @param array $coupons
	 * @param $post_id
	 *
	 * @return int
	 */
	public function delete_coupons( array $coupons, $post_id ) {
		global $wpdb;

		$deleted = 0;

		foreach ( $coupons as $coupon ) {
			$deleted += $wpdb->delete(
				$this->get_coupon_codes_table(),
				array(
					'blog_id' => get_current_blog_id(),
					'post_id' => $post_id,
					'code'    => $coupon,
				),
				array( '%d', '%d', '%s' )
			);
		}

		return $deleted;
	}

	/**
	 * Returns the coupon code for a given association id
	 *
	 * @param $coupon_id
	 * @param $id
	 *
	 * @return null|string
	 */
	public function get_code_for_association_id( $coupon_id, $id ) {
		global $wpdb;

		$table = $this->get_coupon_codes_table();

		$code = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT code FROM $table WHERE blog_id = %d AND post_id = %d AND association_id = %d",
				get_current_blog_id(),
				$coupon_id,
				$id
			)
		);

		if ( empty( $code ) ) {
			return null;
		};

		return $code;
	}

	/**
	 * Associates a code with the given id.
	 *
	 * @param $coupon_id
	 * @param $id
	 *
	 * @return string|null
	 */
	public function associate_code( $coupon_id, $id ) {
		// Check if the id is already associated with a code
		$code = $this->get_code_for_association_id( $coupon_id, $id );
		if ( ! empty( $code ) ) {
			return $code;
		}

		// No existing code found
		global $wpdb;

		$table = $this->get_coupon_codes_table();

		$code = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, code FROM $table WHERE blog_id = %d AND post_id = %d AND association_id IS NULL",
				get_current_blog_id(),
				$coupon_id
			)
		);

		if ( empty( $code ) ) {
			// No more codes found
			return null;
		}

		// Associate the code
		$wpdb->update(
			$table,
			array( 'association_id' => $id ),
			array( 'id' => $code->id ),
			array( '%d' ),
			array( '%d' )
		);

		return $code->code;
	}

	/**
	 * Returns the coupon code table
	 *
	 * @return string
	 */
	public function get_coupon_codes_table() {
		global $wpdb;

		return $wpdb->base_prefix . 'coupons';
	}
}
