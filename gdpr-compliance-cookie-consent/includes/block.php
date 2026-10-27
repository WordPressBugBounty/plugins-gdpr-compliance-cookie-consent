<?php
/**
 * Gutenberg block for the data access / erasure request form. Renders through the same callback as the
 * [stm-gpdr-data-access] shortcode.
 */

defined( 'ABSPATH' ) || exit;

function stmgdpr_register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
		return; // WordPress < 5.0: the shortcode and the widget still work.
	}
	register_block_type(
		dirname( __DIR__ ) . '/blocks/data-request',
		array(
			'render_callback' => function ( $attributes ) {
				// Drop empty attributes so the shortcode defaults apply.
				return \STM_GDPR\includes\STM_DataAccess::getInstance()->stm_gdpr_shortcode( array_filter( (array) $attributes, 'strlen' ) );
			},
		)
	);
	wp_set_script_translations( 'gdpr-compliance-cookie-consent-data-request-editor-script', 'gdpr-compliance-cookie-consent' );
}
add_action( 'init', 'stmgdpr_register_block' );
