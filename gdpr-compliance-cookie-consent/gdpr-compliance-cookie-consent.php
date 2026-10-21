<?php
/**
 * Plugin Name: GDPR Compliance & Cookie Consent
 * Plugin URI:  https://www.calculator.io/gdpr/
 * Description: This plugin adds GDPR-compliant cookie management to websites, ensuring legal compliance and enhancing user privacy.
 * Author:      GDPR Compliance & Cookie Consent
 * Author URI:  https://www.calculator.io/gdpr/
 * License:     GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version:     1.6.3
 * Text Domain: gdpr-compliance-cookie-consent
 * Domain Path: /languages
 */

namespace STM_GDPR;

use STM_GDPR\includes\STM_Cookie;
use STM_GDPR\includes\STM_DataAccess;
use STM_GDPR\includes\STM_PluginOptions;
use STM_GDPR\includes\STM_Helpers;
use STM_GDPR\includes\STM_Plugins;

if (!defined('ABSPATH')) {
    exit;
}

define('STM_GDPR_VERSION', '1.6.3');
define('STM_GDPR_SLUG', 'gdpr-compliance-cookie-consent');
define('STM_GDPR_PREFIX', 'stmgdpr_');
define('STM_GDPR_ROOT_FILE', __FILE__);
define('STM_GDPR_PATH', dirname(__FILE__));
define('STM_GDPR_URL', plugins_url('', __FILE__));

spl_autoload_register(__NAMESPACE__ . '\\stm_autoload');
add_action('plugins_loaded', array(STM_GDPR::getInstance(), 'init'));

class STM_GDPR
{

    private static $instance = null;

    public function init()
    {

        add_action('init', array('STM_GDPR\\includes\\STM_Plugins', 'getInstance'), 5);
        add_action('widgets_init', array($this, 'stm_registerWidget'));

        if (is_admin()) {
            add_action('admin_menu', array(STM_PluginOptions::getInstance(), 'stm_pluginOptions_addMenu'));
            add_action('admin_init', array(STM_PluginOptions::getInstance(), 'stm_pluginOptions_registerSetting'));
            add_action('add_option_' . STM_GDPR_SLUG, array(STM_PluginOptions::getInstance(), 'stm_pluginOptions_updated'));
            add_action('update_option_' . STM_GDPR_SLUG, array(STM_PluginOptions::getInstance(), 'stm_pluginOptions_updated'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'stm_pluginSettingsLink'));
            add_action('admin_enqueue_scripts', array(STM_Helpers::getInstance(), 'stm_enqueue_admin_scripts'));
        }

        if (STM_Helpers::stm_helpers_isEnabled(STM_GDPR_PREFIX . 'general', 'popup')) {
            if (!STM_Cookie::getInstance()->stm_cookie_isAccepted() && !is_admin() && STM_Helpers::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general', 'block_cookies')) {
                add_action('template_redirect', array(STM_Cookie::getInstance(), 'stm_cookie_block_cookies'), 0);
                add_action('shutdown', array(STM_Cookie::getInstance(), 'stm_cookie_block_cookies'), 0);
            }
            add_action('wp_footer', array(STM_Cookie::getInstance(), 'stm_cookie_displayPopup'));
            add_action('wp_ajax_stm_gdpr_cookie_accept', array(STM_Cookie::getInstance(), 'stm_cookie_cookieAccept'));
            add_action('wp_ajax_nopriv_stm_gdpr_cookie_accept', array(STM_Cookie::getInstance(), 'stm_cookie_cookieAccept'));
        }

        /* Enqueue front scripts and Ajax requests */
        add_action('wp_enqueue_scripts', array(STM_Helpers::getInstance(), 'stm_enqueue_scripts'));
        add_action('wp_ajax_stm_gpdr_data_request', array(STM_DataAccess::getInstance(), 'stm_gpdr_data_request'));
        add_action('wp_ajax_nopriv_stm_gpdr_data_request', array(STM_DataAccess::getInstance(), 'stm_gpdr_data_request'));

        /* GDPR Shortcode */
        add_shortcode('stm-gpdr-data-access', array(STM_DataAccess::getInstance(), 'stm_gdpr_shortcode'));

    }

    public function stm_registerWidget()
    {
        require_once(STM_GDPR_PATH . '/includes/STM_DataAccessWidget.php');
        register_widget('STM_DataAccessWidget');
    }

    public function stm_pluginSettingsLink($links = array())
    {

        $actionLinks = array(
            'settings' => '<a href="' . esc_url(add_query_arg(array('page' => STM_GDPR_SLUG), admin_url('admin.php'))) . '" aria-label="' . esc_attr__('STM GDPR settings', 'gdpr-compliance-cookie-consent') . '">' . esc_html__('Settings', 'gdpr-compliance-cookie-consent') . '</a>',
        );

        return array_merge($actionLinks, $links);
    }

    public static function getInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }


}

function stm_autoload($class = '')
{

    if (strpos($class, 'STM_GDPR\\') !== 0 || !preg_match('/^[A-Za-z0-9_\\\\]+$/', $class)) {
        return;
    }

    $return = str_replace('STM_GDPR\\', '', $class);
    $return = str_replace('\\', '/', $return);
    $file = STM_GDPR_PATH . '/' . $return . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
}
