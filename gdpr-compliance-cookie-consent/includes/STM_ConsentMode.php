<?php

namespace STM_GDPR\includes;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Google Consent Mode v2.
 *
 * The default (everything denied) is printed as early as possible in <head>. The visitor's stored choice is read from the
 * consent cookie in the browser and applied as an update, so the output is identical for every visitor and survives page
 * caching. Accept/decline/category changes call gtag('consent', 'update', ...) from assets/js/scripts.js.
 */
class STM_ConsentMode
{
    private static $instance = null;

    public static function stm_consentmode_isEnabled()
    {
        // Default on: only an explicit "off" (stored by the settings page) disables it. Without the banner nobody can
        // grant consent, so the "denied" default would switch Google tags off for good: then stay out of the way.
        return 'off' !== STM_Helpers::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general', 'consent_mode')
            && STM_Helpers::stm_helpers_isEnabled(STM_GDPR_PREFIX . 'general', 'popup');
    }

    /* Consent types per plugin category. */
    public static function stm_consentmode_map()
    {
        return array(
            'analytics' => array('analytics_storage'),
            'marketing' => array('ad_storage', 'ad_user_data', 'ad_personalization'),
        );
    }

    public static function stm_consentmode_denied()
    {
        $state = array();
        foreach (self::stm_consentmode_map() as $types) {
            foreach ($types as $type) {
                $state[$type] = 'denied';
            }
        }

        return $state;
    }

    public function stm_consentmode_printDefault()
    {
        if (!self::stm_consentmode_isEnabled() || STM_Helpers::stm_helpers_isBuilderRequest()) {
            return;
        }

        $default = self::stm_consentmode_denied();
        $default['wait_for_update'] = 500;

        $script = 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}'
            . "gtag('consent','default'," . wp_json_encode($default) . ');'
            . 'window.stmGdprConsentMap=' . wp_json_encode(self::stm_consentmode_map()) . ';'
            . file_get_contents(STM_GDPR_PATH . '/assets/js/consent-mode.js');

        wp_print_inline_script_tag($script, array('id' => 'stm-gdpr-consent-mode'));
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
