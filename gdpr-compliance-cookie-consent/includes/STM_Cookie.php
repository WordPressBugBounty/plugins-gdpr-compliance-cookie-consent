<?php

namespace STM_GDPR\includes;

use STM_GDPR\includes\STM_Helpers;

if (!defined('ABSPATH')) {
    exit;
}

class STM_Cookie
{
    const cookieName = 'stm_gdpr_cookie';
    const declinedValue = 'declined';

    private static $instance = null;

    private $userID;

    private function __construct()
    {
        $this->userID = get_current_user_id();
    }

    public function stm_cookie_displayPopup()
    {
        $settings = STM_Helpers::stm_helpers_general_settings();
        $privacy = STM_Helpers::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'privacy');
        $privacy_page = (!empty($privacy['privacy_page'])) ? absint($privacy['privacy_page']) : 0;
        $privacy_link = ($privacy_page) ? get_page_link($privacy_page) : '#';
        $link_text = (isset($privacy['link_text'])) ? $privacy['link_text'] : '';
        $button_text = (!empty($settings['button_text'])) ? STM_Helpers::stm_helpers_dynamic_string_translation( STM_Helpers::stm_helpers_translateDefault($settings['button_text']) ) : __('Ok, I agree', 'gdpr-compliance-cookie-consent');

        $defaults = STM_Helpers::stm_heplers_get_default_options();
        $bg_color = sanitize_hex_color($settings['popup_bg_color']);
        $text_color = sanitize_hex_color($settings['popup_text_color']);
        $bg_color = ($bg_color) ? $bg_color : $defaults[STM_GDPR_PREFIX . 'general'][0]['popup_bg_color'];
        $text_color = ($text_color) ? $text_color : $defaults[STM_GDPR_PREFIX . 'general'][0]['popup_text_color'];
        $positions = STM_Helpers::stm_helpers_popup_positions();
        $position = (isset($positions[$settings['popup_position']])) ? $settings['popup_position'] : 'left_bottom_';

        $show_decline = STM_Helpers::stm_helpers_isEnabled(STM_GDPR_PREFIX . 'general', 'show_decline');
        $decline_text = (!empty($settings['decline_text'])) ? STM_Helpers::stm_helpers_dynamic_string_translation(STM_Helpers::stm_helpers_translateDefault($settings['decline_text'])) : __('Decline', 'gdpr-compliance-cookie-consent');

        // Always rendered hidden: whether to show it is decided in the browser from the consent cookie, so a page cached by a
        // caching plugin or CDN behaves the same for every visitor.
        $popup = '<div id="stm_gdpr_popup-main" class="stm_gdpr_popup-main" style="display: none;background-color: ' . esc_attr($bg_color) . '; color: ' . esc_attr($text_color) . ';
		' . esc_attr(str_replace('_', ': 20px; ', $position)) . '">
            <div class="stm_gdpr_popup-content">' . wp_kses_post(STM_Helpers::stm_helpers_dynamic_string_translation( STM_Helpers::stm_helpers_translateDefault($settings['popup_content']) ) . STM_Helpers::stm_helpers_get_commitment()) . '</div>
			<div class="stm_gdpr_popup-links' . ($show_decline ? ' stm_gdpr_has-decline' : '') . '">
				<a href="#" id="stm_gdpr_popup_accept" class="stm_gdpr_popup-accept">' . esc_html($button_text) . '</a>
				' . ($show_decline ? '<a href="#" id="stm_gdpr_popup_decline" class="stm_gdpr_popup-decline">' . esc_html($decline_text) . '</a>' : '') . '
				<a href="' . esc_url($privacy_link) . '" class="stm_gdpr_popup-privacy">' . esc_html(STM_Helpers::stm_helpers_dynamic_string_translation( STM_Helpers::stm_helpers_translateDefault($link_text) )) . '</a>
			</div>
		</div>';

        echo $popup; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every dynamic part is escaped or passed through wp_kses_post above.
    }

    /**
     * Stores the visitor's choice. The browser already wrote the consent cookie itself (so this works on cached pages);
     * this request mirrors it on the server: the cookie for visitors whose JavaScript could not, and the user meta for
     * logged-in users so the choice follows them across devices. Anonymous visitors need no nonce here: a cached page
     * would carry an expired one and the choice would silently be lost.
     */
    public function stm_cookie_cookieAccept()
    {
        if (is_user_logged_in()) {
            check_ajax_referer('stm_gdpr_cookie_accept', 'nonce');
        }

        $expire_time = absint(STM_Helpers::stm_helpers_cmb_get_option(STM_GDPR_PREFIX . 'general', 'expire_time'));
        if (!$expire_time) {
            $expire_time = 15768000;
        }

        $choice = isset($_POST['choice']) ? sanitize_key(wp_unslash($_POST['choice'])) : 'accept'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- the choice is public data, and logged-in users are verified above.
        $value = ('decline' === $choice) ? self::declinedValue : wp_generate_password(32, false);

        // The browser normally wrote the cookie before sending this request (it may carry a category suffix the server must
        // not overwrite); set it here only when it is missing or contradicts the choice.
        $sent = isset($_COOKIE[self::cookieName]) ? sanitize_text_field(wp_unslash($_COOKIE[self::cookieName])) : '';
        $consistent = '' !== $sent && (('decline' === $choice) === (self::declinedValue === $sent));

        if (!$consistent && !headers_sent()) {
            setcookie(self::cookieName, $value, array(
                'expires' => time() + $expire_time,
                'path' => '/',
                'secure' => is_ssl(),
                'samesite' => 'Lax',
            ));
        }

        if ($this->userID) {
            if ('decline' === $choice) {
                delete_user_meta($this->userID, self::cookieName);
            } else {
                update_user_meta($this->userID, self::cookieName, time() + $expire_time);
            }
        }

        wp_send_json_success();
    }

    public function stm_cookie_isAccepted()
    {
        if (!empty($_COOKIE[self::cookieName])) {
            return self::declinedValue !== sanitize_text_field(wp_unslash($_COOKIE[self::cookieName]));
        }

        if ($this->userID) {
            $isAcceptedUser = get_user_meta($this->userID, self::cookieName, true);
            if (!empty($isAcceptedUser) && is_numeric($isAcceptedUser) && $isAcceptedUser > time()) {
                return true;
            }
        }

        return false;
    }

    /* Whether the server already knows the logged-in user accepted (the browser has no cookie on a new device). */
    public function stm_cookie_isAcceptedByUser()
    {
        if (!$this->userID || !empty($_COOKIE[self::cookieName])) {
            return false;
        }

        $isAcceptedUser = get_user_meta($this->userID, self::cookieName, true);

        return !empty($isAcceptedUser) && is_numeric($isAcceptedUser) && $isAcceptedUser > time();
    }

    public function stm_cookie_block_cookies()
    {

        $user_from_eu = false;
        $user_from_eu = apply_filters('stm_gdpr_return_false', $user_from_eu);

        if($user_from_eu){
            return true;
        }

        if (in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php')) || (!empty($_POST['action']) && 'stm_gdpr_cookie_accept' === sanitize_key(wp_unslash($_POST['action']))) // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only compares the action name; the AJAX handler verifies its own nonce.
        ) {
            return;
        }

        @header_remove('Set-Cookie');

        if (isset($_COOKIE)) {

            $time = time() - 100;
            foreach (array_keys($_COOKIE) as $cookie_name) {
                if ($cookie_name && $cookie_name != self::cookieName) {
                    @setcookie($cookie_name, '', $time, '/');
                }
            }
        }

    }

    public static function getInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

}
