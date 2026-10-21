<?php
namespace STM_GDPR\includes\plugins;

use STM_GDPR\includes\STM_Helpers;

if (!defined('ABSPATH')) {
	exit;
}

class STM_WooCommerce
{
    const SLUG = 'woocommerce';

    private static $instance = null;

	public function stm_woocommerce_displayCheckbox() {

		$args = array(
			'type' => 'checkbox',
			'class' => array('stmgdpr-checkbox'),
			'label' => wp_kses_post(STM_Helpers::stm_helpers_checkboxText(self::SLUG) . STM_Helpers::stm_helpers_get_commitment()),
			'required' => true,
		);
		woocommerce_form_field('stmgdpr', $args);

	}

	public function stm_woocommerce_displayError() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before firing woocommerce_checkout_process.
		if (!isset($_POST['stmgdpr'])) {
			wc_add_notice(STM_Helpers::stm_helpers_errorMessage(self::SLUG), 'error');
		}

	}

	public function stm_woocommerce_updateOrderMeta($orderID = 0) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies the checkout nonce before the order is created.
		if (isset($_POST['stmgdpr']) && !empty($orderID)) {
			$order = wc_get_order($orderID);
			if ($order) {
				$order->update_meta_data('_stmgdpr', time());
				$order->save_meta_data();
			}
		}

	}

	public function stm_woocommerce_displayOrderData(\WC_Order $order) {

		$label = __('GDPR accepted on:', 'gdpr-compliance-cookie-consent');
		$date = $order->get_meta('_stmgdpr', true);
		$value = (!empty($date)) ? STM_Helpers::stm_helpers_localDate(get_option('date_format') . ' ' . get_option('time_format'), $date) : __('Not accepted.', 'gdpr-compliance-cookie-consent');

		printf('<p class="form-field form-field-wide stm-gdpr-date"><strong>%s</strong><br />%s</p>', esc_html($label), esc_html($value));
	}

	public static function getInstance() {

		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
