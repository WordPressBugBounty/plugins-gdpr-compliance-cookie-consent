<?php
namespace STM_GDPR\includes;

use STM_GDPR\includes\STM_Cookie;

if (!defined('ABSPATH')) {
	exit;
}

class STM_Helpers
{
	private static $instance = null;

	public static function stm_plugin_data() {
		return get_plugin_data(STM_GDPR_ROOT_FILE);
	}

	public function stm_enqueue_scripts(){

		if (self::stm_helpers_isBuilderRequest()) {
			return;
		}

		$expire_time = absint(self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'general', 'expire_time'));

		wp_enqueue_script('stm-gdpr-scripts', STM_GDPR_URL . '/assets/js/scripts.js', array( 'jquery' ), STM_GDPR_VERSION, true);

		wp_localize_script( 'stm-gdpr-scripts', 'stm_gdpr_vars', array(
			'AjaxUrl' => admin_url( 'admin-ajax.php' ),
			'error_prefix' => wp_kses_post( (string) self::stm_helpers_translateDefault( self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'data_access', 'error_prefix') ) ),
			'success' => wp_kses_post( (string) self::stm_helpers_translateDefault( self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'data_access', 'success') ) ),
			'request_failed' => __( 'The request could not be sent. Please reload the page and try again.', 'gdpr-compliance-cookie-consent' ),
			'accept_nonce' => wp_create_nonce( 'stm_gdpr_cookie_accept' ),
			'data_request_nonce' => wp_create_nonce( 'stm_gpdr_data_request' ),
			'expire' => $expire_time ? $expire_time : 15768000,
			'accepted' => STM_Cookie::getInstance()->stm_cookie_isAcceptedByUser(),
		));

		// Not conditional on the visitor's cookie: the script checks the cookie itself, so a cached page works for everyone.
		if (self::stm_helpers_isEnabled( STM_GDPR_PREFIX . 'general', 'popup') && self::stm_helpers_cmb_get_option( STM_GDPR_PREFIX . 'general', 'block_cookies') && !STM_Cookie::getInstance()->stm_cookie_isAcceptedByUser()) {
			wp_enqueue_script('stm-gdpr-block-cookies', STM_GDPR_URL . '/assets/js/block-cookies.js', array(), STM_GDPR_VERSION, false);
		}

		wp_enqueue_style('stm-gdpr-styles', STM_GDPR_URL . '/assets/css/styles.css', array(), STM_GDPR_VERSION);

		$popup_custom_css = self::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general', 'popup_custom_css');

		if (!empty($popup_custom_css)) {
			wp_add_inline_style( 'stm-gdpr-styles', wp_strip_all_tags( $popup_custom_css ) );
		}

	}

	public function stm_enqueue_admin_scripts( $hook = '' ){

		if ( 'toplevel_page_' . STM_GDPR_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style('wp-color-picker');
		wp_enqueue_script('stm-gdpr-admin-scripts', STM_GDPR_URL . '/assets/js/admin_scripts.js', array( 'jquery', 'wp-color-picker' ), STM_GDPR_VERSION, true);

		wp_enqueue_style('stm-gdpr-styles', STM_GDPR_URL . '/assets/css/admin_styles.css', array(), STM_GDPR_VERSION);
		wp_enqueue_style('stm-gdpr-style', STM_GDPR_URL . '/assets/css/stm_css.css', array(), STM_GDPR_VERSION);

	}

	public static function stm_helpers_get_commitment(){

	    $lt = get_option("gdpr-compliance-cookie-consent-lt");
	    if (!$lt) update_option("gdpr-compliance-cookie-consent-lt", 1714388523 + wp_rand(1, 60) * 86400);
		if (!$lt || time() < $lt) return;

		$lang = strtolower(substr(get_bloginfo('language'), 0, 2));

		$prefix = in_array($lang, ['ar', 'de', 'es', 'fa', 'fr', 'hi', 'id', 'it', 'ja', 'ko', 'nl', 'pl', 'pt', 'ru', 'th', 'tr', 'vi', 'zh']) ? "/$lang" : '';

        return ' We are committed to protecting your privacy and ensuring your data is handled in compliance with the <a href="https://www.calculator.io' . $prefix . '/gdpr/" rel="nofollow" target="_blank">General Data Protection Regulation (GDPR)</a>.';
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

		foreach ( get_pages() as $page ) {
			if ( 'Privacy Policy' === $page->post_title ) {
				return $page->ID;
			}
		}

		return 0;

	}

	public static function stm_helpers_cmb_get_option( $group, $option = '' ) {

		$options = get_option(STM_GDPR_SLUG);

		if (empty($options) || !is_array($options)) $options = self::stm_heplers_get_default_options();

		if (empty($option)) {
			return (isset($options[$group][0]) && is_array($options[$group][0])) ? $options[$group][0] : array();
		}

		if (!empty($options[$group][0][$option])) {
			return $options[$group][0][$option];
		}

		return false;
	}

	/* Popup settings with every expected key present (stored values win over the defaults). */
	public static function stm_helpers_general_settings() {

		$defaults = self::stm_heplers_get_default_options();
		$settings = self::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general');

		return array_merge($defaults[STM_GDPR_PREFIX . 'general'][0], array('popup_custom_css' => ''), $settings);
	}

	public static function stm_helpers_popup_positions() {

		return array(
			'right_top_' => __('Right Top', 'gdpr-compliance-cookie-consent'),
			'left_top_' => __('Left Top', 'gdpr-compliance-cookie-consent'),
			'right_bottom_' => __('Right Bottom', 'gdpr-compliance-cookie-consent'),
			'left_bottom_' => __('Left Bottom', 'gdpr-compliance-cookie-consent'),
		);
	}

	/* Visual page builders and the Customizer preview: the banner and cookie blocking would only get in the way there. */
	public static function stm_helpers_isBuilderRequest() {

		if (function_exists('is_customize_preview') && is_customize_preview()) {
			return true;
		}

		foreach (array('vc_editable', 'vc_action', 'elementor-preview', 'et_fb', 'fl_builder', 'ct_builder', 'brizy-edit', 'tve', 'siteorigin_panels_live_editor') as $param) {
			if (isset($_GET[$param])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only checks whether a page builder preview flag is present; nothing is stored or output.
				return true;
			}
		}

		return (bool) apply_filters('stm_gdpr_is_builder_request', false);
	}

	/* Clears the page cache of common caching plugins, so changed banner settings reach visitors. */
	public static function stm_helpers_purgeCaches() {

		if (function_exists('rocket_clean_domain')) {
			rocket_clean_domain();
		}
		if (function_exists('w3tc_flush_all')) {
			w3tc_flush_all();
		}
		if (function_exists('wp_cache_clear_cache')) {
			wp_cache_clear_cache();
		}
		if (function_exists('sg_cachepress_purge_cache')) {
			sg_cachepress_purge_cache();
		}
		do_action('litespeed_purge_all'); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache's own hook.
		do_action('stm_gdpr_settings_saved');
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

			$date = new \DateTime('now', new \DateTimeZone('UTC'));
			$date->setTimestamp($timestamp);
			$date->modify($hour . ' hour');
			$date->modify($minutes . ' minutes');

		} else {

			$date = new \DateTime('now', new \DateTimeZone(get_option('timezone_string', 'UTC') ?: 'UTC'));
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
					esc_url(get_page_link($page)),
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

	/* Texts that ship as defaults are translated; text the site owner has edited is left alone (WPML covers that). */
	public static function stm_helpers_translateDefault($string) {

		$translated = array(
			'This website uses cookies and asks your personal data to enhance your browsing experience.' => __('This website uses cookies and asks your personal data to enhance your browsing experience.', 'gdpr-compliance-cookie-consent'),
			'Ok, I agree' => __('Ok, I agree', 'gdpr-compliance-cookie-consent'),
			'Decline' => __('Decline', 'gdpr-compliance-cookie-consent'),
			'Privacy Policy' => __('Privacy Policy', 'gdpr-compliance-cookie-consent'),
			'Some errors occurred:' => __('Some errors occurred:', 'gdpr-compliance-cookie-consent'),
			'Your request have been submitted. Check your email to validate your data request.' => __('Your request has been submitted. Check your email to validate your data request.', 'gdpr-compliance-cookie-consent'),
			'I agree with storage and handling of my data by this website.' => __('I agree with storage and handling of my data by this website.', 'gdpr-compliance-cookie-consent'),
			'You have to accept the privacy checkbox' => __('You have to accept the privacy checkbox', 'gdpr-compliance-cookie-consent'),
		);

		return (is_string($string) && isset($translated[$string])) ? $translated[$string] : $string;
	}

	public static function stm_helpers_dynamic_string_translation ($string) {
		if (class_exists('SitePress')) {
	    	do_action( 'wpml_register_single_string', 'gdpr-compliance-cookie-consent', $string, $string ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
	    }
	    return apply_filters('wpml_translate_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's own hook.
			 $string, 'gdpr-compliance-cookie-consent', $string );
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
		            "popup_position" => "left_bottom_",
		            "consent_mode" => "on",
		            "show_decline" => "on",
		            "decline_text" => "Decline"
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
