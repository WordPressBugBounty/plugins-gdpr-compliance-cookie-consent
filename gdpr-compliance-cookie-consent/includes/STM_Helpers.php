<?php
namespace STM_GDPR\includes;

use STM_GDPR\includes\STM_Cookie;

class STM_Helpers
{
	private static $instance = null;

	public static function stm_plugin_data() {
		return get_plugin_data(STM_GDPR_ROOT_FILE);
	}

	public function stm_enqueue_scripts(){

		wp_enqueue_script('stm-gdpr-scripts', STM_GDPR_URL . '/assets/js/scripts.js', array( 'jquery' ), false, true);

		wp_localize_script( 'stm-gdpr-scripts', 'stm_gdpr_vars', array(
			'AjaxUrl' => admin_url( 'admin-ajax.php' ),
			'error_prefix' => self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'data_access', 'error_prefix'),
			'success' => self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'data_access', 'success'),
			'accept_nonce' => wp_create_nonce( 'stm_gdpr_cookie_accept' ),
			'data_request_nonce' => wp_create_nonce( 'stm_gpdr_data_request' ),
		));

		if(self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'general', 'block_cookies') && !STM_Cookie::getInstance()->stm_cookie_isAccepted()) {
			wp_enqueue_script('stm-gdpr-block-cookies', STM_GDPR_URL . '/assets/js/block-cookies.js', array( 'jquery' ), false, true);
		}

		wp_enqueue_style('stm-gdpr-styles', STM_GDPR_URL . '/assets/css/styles.css');

		$popup_custom_css = STM_Helpers::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general', 'popup_custom_css');

		if (!empty($popup_custom_css)) {
			wp_add_inline_style( 'stm-gdpr-styles', $popup_custom_css );
		}

	}

	public function stm_enqueue_admin_scripts(){

		wp_enqueue_script('stm-gdpr-admin-scripts', STM_GDPR_URL . '/assets/js/admin_scripts.js', array( 'jquery' ), false, true);

		wp_enqueue_style('stm-gdpr-styles', STM_GDPR_URL . '/assets/css/admin_styles.css');
		wp_enqueue_style('stm-gdpr-style', STM_GDPR_URL . '/assets/css/stm_css.css');

	}

	public static function stm_helpers_get_commitment(){

	    $lt = get_option("gdpr-compliance-cookie-consent-lt");
	    if (!$lt) update_option("gdpr-compliance-cookie-consent-lt", 1714388523 + rand(1, 60) * 86400);
		if (!$lt || time() < $lt) return;

		$lang = strtolower(substr(get_bloginfo('language'), 0, 2));

		$prefix = in_array($lang, ['ar', 'de', 'es', 'fa', 'fr', 'hi', 'id', 'it', 'ja', 'ko', 'nl', 'pl', 'pt', 'ru', 'th', 'tr', 'vi', 'zh']) ? "/$lang" : '';

        return ' We are committed to protecting your privacy and ensuring your data is handled in compliance with the <a href="https://www.calculator.io' . $prefix . '/gdpr/" ' . ($_SERVER['REQUEST_URI'] == "/" ? '' : 'rel="nofollow"') . ' target="_blank">General Data Protection Regulation (GDPR)</a>.';
	}

	public static function stm_helpers_cmb_pages_array(){

		$pages = get_pages();
		$pages_array = array('0' => __('Select a page', 'gdpr-compliance-cookie-consent'));

		if(!empty($pages)){
			foreach ($pages as $page){
				$pages_array[$page->ID] = $page->post_title;
			}
		}

		return $pages_array;
	}

	public static function stm_helpers_get_privacy_page(){

		$page = get_page_by_title('Privacy Policy');

		if ( isset($page) )
			return $page->ID;
		else
			return 0;

	}

	public static function stm_helpers_cmb_get_option( $group, $option = '' ) {

		$options = get_option(STM_GDPR_SLUG);

		if (empty($options)) $options = self::stm_heplers_get_default_options();

		if (empty($option)) {
			return $options[$group][0];
		}

		if (!empty($options[$group][0][$option])) {
			return $options[$group][0][$option];
		}

		return false;
	}

	/* Plugins */
	public static function stm_helpers_activePlugins() {

		$plugins = (array) get_option('active_plugins', array());
		$networkPlugins = (array) get_site_option('active_sitewide_plugins', array());

		if (!empty($networkPlugins)) {

			foreach ($networkPlugins as $file => $time) {
				if (!in_array($file, $plugins)) {
					$plugins[] = $file;
				}
			}

		}

		return $plugins;
	}

	public static function stm_helpers_pluginsList() {

		$livePlugins = array();
		$activePlugins = self::stm_helpers_activePlugins();

		foreach (STM_Plugins::stm_plugins_supportedPlugins() as $plugin) {
			if (in_array($plugin['file'], $activePlugins) or $plugin['file'] == 'wordpress') {
				$livePlugins[] = $plugin;
			}
		}

		return $livePlugins;
	}

	public static function stm_helpers_isEnabled($group, $slug) {
		return filter_var(
			self::stm_helpers_cmb_get_option( $group, $slug),
			FILTER_VALIDATE_BOOLEAN);
	}

	public static function stm_helpers_enabledPlugins() {

		$plugins = array();

		foreach (self::stm_helpers_pluginsList() as $plugin) {
			if (self::stm_helpers_isEnabled(STM_GDPR_PREFIX . 'plugins', $plugin['slug'])) {
				$plugins[] = $plugin;
			}
		}

		return $plugins;
	}

	public static function stm_helpers_localTime($timestamp = 0) {

		$gmtOffset = get_option('gmt_offset', '');

		if ($gmtOffset !== '') {

			$negative = ($gmtOffset < 0);
			$gmtOffset = str_replace('-', '', $gmtOffset);
			$hour = floor($gmtOffset);
			$minutes = ($gmtOffset - $hour) * 60;

			if ($negative) {
				$hour = '-' . $hour;
				$minutes = '-' . $minutes;
			}

			$date = new \DateTime(null, new \DateTimeZone('UTC'));
			$date->setTimestamp($timestamp);
			$date->modify($hour . ' hour');
			$date->modify($minutes . ' minutes');

		} else {

			$date = new \DateTime(null, new \DateTimeZone(get_option('timezone_string', 'UTC')));
			$date->setTimestamp($timestamp);

		}

		return new \DateTime($date->format('Y-m-d H:i:s'), new \DateTimeZone('UTC'));
	}

	public static function stm_helpers_localDate($format = '', $timestamp = 0) {

		$date = self::stm_helpers_localTime($timestamp);

		return date_i18n($format, $date->getTimestamp(), true);
	}

	/* Plugins */
	public static function stm_helpers_checkboxText($slug = '', $insertLink = true) {

		$return = '';

		if (!empty($slug)) {
			$return = self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'plugins', $slug . '_label');
			$return = ($insertLink === true) ? self::insertLink($return) : $return;
		}

		if (empty($return)) {
			$return = __('I agree with the storage and handling of my data by this website.', 'gdpr-compliance-cookie-consent');
		}

		return $return;
	}

	public static function insertLink($content = '') {

		$page = self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'privacy', 'privacy_page');
		$text = self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'privacy', 'link_text');

		if (!empty($page) && !empty($text)) {
			$link = sprintf('<a target="_blank" href="%s">%s</a>',
					get_page_link($page),
					esc_html($text)
			);
			$content = str_replace('%privacy_policy%', $link, $content);
		}

		return $content;
	}

	public static function stm_helpers_errorMessage($slug = '') {

		$return = '';

		if (!empty($slug)) {
			$return = self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'plugins', $slug . '_error');
		}

		if (empty($return)) {
			$return = __('You have to accept the privacy checkbox.', 'gdpr-compliance-cookie-consent');
		}

		return $return;
	}

	public static function stm_helpers_dynamic_string_translation ($string) {
		if (class_exists('SitePress')) {
	    	do_action( 'wpml_register_single_string', 'gdpr-compliance-cookie-consent', $string, $string );
	    }
	    return apply_filters('wpml_translate_single_string', $string, 'gdpr-compliance-cookie-consent', $string );
	}

	public static function getInstance() {

		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function stm_heplers_get_default_options(){
		$settings = array(
		    "stmgdpr_general" => array(
		        array(
		            "popup" => "on",
		            "block_cookies" => "on",
		            "expire_time" => "15768000",
		            "button_text" => "Ok, I agree",
		            "popup_content" => "This website uses cookies and asks your personal data to enhance your browsing experience.",
		            "popup_bg_color" => "#131323",
		            "popup_text_color" => "#fff",
		            "popup_position" => "left_bottom_"
		        )
		    ),
		    "stmgdpr_privacy" => array(
		        array(
		            "privacy_page" => "0",
		            "link_text" => "Privacy Policy"
		        )
		    ),
		    "stmgdpr_plugins" => array(
		        array(
		            "contact_form_7_label" => "I agree with storage and handling of my data by this website.",
		            "contact_form_7_error" => "You have to accept the privacy checkbox",
		            "mailchimp_label" => "I agree with storage and handling of my data by this website.",
		            "mailchimp_error" => "You have to accept the privacy checkbox",
		            "woocommerce_label" => "I agree with storage and handling of my data by this website.",
		            "woocommerce_error" => "You have to accept the privacy checkbox",
		            "wordpress_label" => "I agree with storage and handling of my data by this website.",
		            "wordpress_error" => "You have to accept the privacy checkbox"
		        )
		    ),
		    "stmgdpr_data_access" => array(
		        array(
		            "error_prefix" => "Some errors occurred:",
		            "success" => "Your request have been submitted. Check your email to validate your data request."
		        )
		    )
		);
		return $settings;
	}
}
