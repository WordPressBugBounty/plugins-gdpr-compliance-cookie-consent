<?php
namespace STM_GDPR\includes\plugins;

use STM_GDPR\includes\STM_Helpers;

if (!defined('ABSPATH')) {
	exit;
}

class STM_BuddyPress
{
    const SLUG = 'buddypress';

	private static $instance = null;

	public function stm_buddypress_addCheckbox(){

		echo '<input id="stm_gdpr" class="stm_gdpr" type="checkbox" name="stm_gdpr" required />
		<label for="stm_gdpr">
			' . wp_kses_post(STM_Helpers::stm_helpers_translateDefault(STM_Helpers::stm_helpers_checkboxText(self::SLUG)) . STM_Helpers::stm_helpers_get_commitment()) . '
		</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the dynamic part is passed through wp_kses_post.
	}

    public static function getInstance() {

		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}