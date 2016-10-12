<?php
if ( ! class_exists( 'Composer\Script\Event', false ) ) {
	return;
}

/**
 * Installs translations into the WordPress languages folder on installation
 */
class WP_Simple_Coupons_Composer_Installer {
	public static function install_translations() {
		$plugin_lang_dir = realpath( __DIR__ . '/../language' );
		$wp_lang_dir = realpath( __DIR__ . '/../../../languages/plugins' );

		if ( ! $wp_lang_dir ) {
			return;
		}

		$iterator = new DirectoryIterator( $plugin_lang_dir );
		foreach ( $iterator as $fileinfo ) {
		    if ( $fileinfo->isDot() || ! in_array( $fileinfo->getExtension(), array( 'mo', 'po' ) ) ) {
		    	continue;
		    }
		    copy( $fileinfo->getRealPath(), $wp_lang_dir . '/' . $fileinfo->getBasename() );
		}
	}
}
