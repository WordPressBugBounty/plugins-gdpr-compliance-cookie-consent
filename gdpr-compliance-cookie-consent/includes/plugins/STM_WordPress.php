<?php
namespace STM_GDPR\includes\plugins;

use STM_GDPR\includes\STM_Helpers;

if (!defined('ABSPATH')) {
	exit;
}

class STM_WordPress
{
    const SLUG = 'wordpress';

    private static $instance = null;

	public function stm_wordpress_addCheckbox( $submit = '' ) {

		$checkbox = apply_filters(
			STM_GDPR_PREFIX . 'wordpress_checkbox',
			'<p class="' . STM_GDPR_SLUG . '-checkbox"><label><input type="checkbox" name="' . STM_GDPR_SLUG . '" id="' . STM_GDPR_SLUG . '" value="1" />' . wp_kses_post(STM_Helpers::stm_helpers_checkboxText(self::SLUG) . STM_Helpers::stm_helpers_get_commitment()) . ' <abbr class="required" title="' . esc_attr__('required', 'gdpr-compliance-cookie-consent') . '">*</abbr></label></p>',
			$submit
		);

		return $checkbox . $submit;
	}

	public function stm_wordpress_displayError() {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- runs before core processes the comment form; only checks that the consent box was ticked.
		if (!isset($_POST[STM_GDPR_SLUG])) {

			wp_die(
				'<p>' . sprintf(
					/* translators: %s: error message configured for the consent checkbox */
					esc_html__('ERROR: %s', 'gdpr-compliance-cookie-consent'),
					esc_html(STM_Helpers::stm_helpers_errorMessage(self::SLUG))
				) . '</p>',
				esc_html__('Comment Submission Failure', 'gdpr-compliance-cookie-consent'),
				array('back_link' => true)
			);

		}

	}

	public function stm_wordpress_addCommentMeta($commentId = 0) {

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- fires inside core's comment submission, which has already validated the request.
		if (isset($_POST[STM_GDPR_SLUG]) && !empty($commentId)) {

			add_comment_meta($commentId, STM_GDPR_SLUG, time());

		}

	}

	public function stm_wordpress_displayMetaColumn($columns = array()) {

		$columns[STM_GDPR_SLUG] = esc_html__('GDPR Accepted On', 'gdpr-compliance-cookie-consent');

		return $columns;
	}

	public function stm_wordpress_displayCommentOverview($column = '', $commentId = 0) {

		if ($column === STM_GDPR_SLUG) {

			$date = get_comment_meta($commentId, STM_GDPR_SLUG, true);
			$value = (!empty($date)) ? STM_Helpers::stm_helpers_localDate(get_option('date_format') . ' ' . get_option('time_format'), $date) : esc_html__('Not accepted.', 'gdpr-compliance-cookie-consent');
			echo esc_html($value);

		}

		return $column;
	}

    public static function getInstance() {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

}