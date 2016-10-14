<?php

/**
 * Handles the integration with kjero functions
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
		add_action( 'media_buttons', array( $this, 'add_shortcode_selector' ), 30 );
	}

	/**
	 * Render the shortcode
	 *
	 * @todo Add "no more coupons available" text option
	 * @param $atts
	 *
	 * @return string|void
	 */
	public function render_shortcode( $atts ) {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		if ( empty( $atts['id'] ) ) {
			return '';
		}

		return esc_attr( $this->helper->associate_code( $atts['id'], get_current_user_id() ) );
	}

	/**
	 * Adds a shortcode selector to the editor
	 */
	public function add_shortcode_selector() {
		// Allow selector to be hidden
		if ( false === apply_filters( 'wp_simple_coupons_show_media_button', true ) ) {
			return;
		}

		$options = '';
		foreach ( $this->helper->get_all_coupons() as $c ) {
			//name= is only set as a visual hint and not actually used
			$options .= sprintf(
				'<option value="[coupon id=%d name=%s]">%s</option>',
				$c->ID,
				esc_attr( $c->post_title ),
				esc_html( $c->post_title )
			);
		}

		if ( empty( $options ) ) {
			return;
		}

		$options = sprintf( '<option>%s</option>', __( 'Coupons' ) ) . $options;

		printf(
			'<select onchange="window.send_to_editor(this.options[this.selectedIndex].value);this.selectedIndex=0">%s</select>',
			$options
		);
	}
}
