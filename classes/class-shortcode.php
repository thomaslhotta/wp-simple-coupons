<?php

/**
 * Handles shortcode
 */
class WP_Simple_Coupons_Shortcodes {

	/**
	 * @var WP_Simple_Coupons_Helper
	 */
	protected $helper;

	public function __construct( WP_Simple_Coupons_Helper $helper ) {
		$this->helper = $helper;
	}

	public function register_hooks() {
		add_shortcode( 'coupon', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Render the shortcode
	 *
	 * @param $atts
	 *
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'id'            => '',
				'error_message' => '',
				'display_only'  => '',
			),
			$atts,
			'simple_coupons_shortcode'
		);

		if ( empty( $atts['id'] ) ) {
			return strip_tags( $atts['error_message'] );
		}

		if ( 'most_recent' === $atts['id'] ) {
			return esc_attr( $this->helper->get_most_recent_code_for_association_id( get_current_user_id() ) );
		}

		if ( 'true' === $atts['display_only'] ) {
			return esc_attr( $this->helper->get_code_for_association_id( $atts['id'], get_current_user_id() ) );
		}

		return esc_attr( $this->helper->associate_code( $atts['id'], get_current_user_id() ) );
	}
}
