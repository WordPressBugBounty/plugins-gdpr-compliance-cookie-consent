<?php

namespace STM_GDPR\includes;

use STM_GDPR\includes\plugins\STM_ContactForm7;
use STM_GDPR\includes\plugins\STM_GravityForms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings page. The option keeps the layout the plugin has always stored:
 * get_option('gdpr-compliance-cookie-consent')[<group>][0][<field>].
 */
class STM_PluginOptions
{
    private static $instance = null;

    const SETTINGS_GROUP = 'stmgdpr_settings';

    public function stm_pluginOptions_addMenu()
    {
        add_menu_page(
            STM_Helpers::stm_plugin_data()['Name'],
            'GDPR',
            'manage_options',
            STM_GDPR_SLUG,
            array($this, 'stm_pluginOptions_renderPage'),
            'dashicons-unlock'
        );
    }

    public function stm_pluginOptions_registerSetting()
    {
        register_setting(self::SETTINGS_GROUP, STM_GDPR_SLUG, array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'stm_pluginOptions_sanitize'),
        ));
    }

    /* Runs after the options were saved: re-sync the consent field of Contact Form 7 / Gravity Forms. */
    public function stm_pluginOptions_updated()
    {
        STM_Helpers::stm_helpers_purgeCaches();
        STM_ContactForm7::getInstance()->stm_contactform7_updateForms();
        STM_GravityForms::getInstance()->stm_gravityforms_updateForms();
    }

    /**
     * Field definitions per group: id => array(type, label, ...).
     * Types: checkbox, number, text, html (limited HTML), editor, color, select, pages, css, class.
     */
    private function stm_pluginOptions_fields()
    {
        $fields = array(
            STM_GDPR_PREFIX . 'general' => array(
                'popup' => array('checkbox', __('Enable', 'gdpr-compliance-cookie-consent'), __('Display Privacy and Cookie popup box', 'gdpr-compliance-cookie-consent')),
                'block_cookies' => array('checkbox', __('Block Cookies', 'gdpr-compliance-cookie-consent'), __('Block Cookies until accepting the Cookie Consent', 'gdpr-compliance-cookie-consent')),
                'expire_time' => array('number', __('Expire time (seconds)', 'gdpr-compliance-cookie-consent'), __('Cookie consent expire time. Default 6 months', 'gdpr-compliance-cookie-consent')),
                'button_text' => array('text', __('Button text', 'gdpr-compliance-cookie-consent')),
                'show_decline' => array('checkbox', __('Decline button', 'gdpr-compliance-cookie-consent'), __('Show a Decline button next to the accept button', 'gdpr-compliance-cookie-consent')),
                'decline_text' => array('text', __('Decline button text', 'gdpr-compliance-cookie-consent')),
                'consent_mode' => array('toggle', __('Google Consent Mode v2', 'gdpr-compliance-cookie-consent'), __('Tell Google tags (Google Analytics, Google Ads, Tag Manager) to wait for consent: storage is denied until the visitor accepts', 'gdpr-compliance-cookie-consent')),
                'popup_content' => array('editor', __('Popup content', 'gdpr-compliance-cookie-consent')),
                'popup_bg_color' => array('color', __('Popup background color', 'gdpr-compliance-cookie-consent')),
                'popup_text_color' => array('color', __('Popup text color', 'gdpr-compliance-cookie-consent')),
                'popup_position' => array('select', __('Popup position', 'gdpr-compliance-cookie-consent'), '', STM_Helpers::stm_helpers_popup_positions()),
                'popup_custom_css' => array('css', __('Custom CSS', 'gdpr-compliance-cookie-consent')),
            ),
            STM_GDPR_PREFIX . 'privacy' => array(
                'privacy_page' => array('pages', __('Privacy Policy page', 'gdpr-compliance-cookie-consent'), __('Select Privacy Policy page and link text for the Popup', 'gdpr-compliance-cookie-consent')),
                'link_text' => array('text', __('Custom link text', 'gdpr-compliance-cookie-consent')),
            ),
            STM_GDPR_PREFIX . 'plugins' => array(),
            STM_GDPR_PREFIX . 'data_access' => array(
                'error_prefix' => array('text', __('Error message', 'gdpr-compliance-cookie-consent')),
                'success' => array('editor', __('Success message', 'gdpr-compliance-cookie-consent')),
                'input-class' => array('class', __('Input field classes', 'gdpr-compliance-cookie-consent')),
                'button-class' => array('class', __('Submit button classes', 'gdpr-compliance-cookie-consent')),
            ),
        );

        foreach (STM_Helpers::stm_helpers_pluginsList() as $plugin) {
            $fields[STM_GDPR_PREFIX . 'plugins'][$plugin['slug']] = array('checkbox', $plugin['name'], __('Enable', 'gdpr-compliance-cookie-consent'));
            $fields[STM_GDPR_PREFIX . 'plugins'][$plugin['slug'] . '_label'] = array('html', __('Checkbox label', 'gdpr-compliance-cookie-consent'));
            $fields[STM_GDPR_PREFIX . 'plugins'][$plugin['slug'] . '_error'] = array('text', __('Error notification', 'gdpr-compliance-cookie-consent'));
        }

        return $fields;
    }

    /* Sanitizes the whole option (options.php has already unslashed the POST data); values of fields that were not on the form (e.g. a deactivated integration) and groups the page does not define are kept. */
    public function stm_pluginOptions_sanitize($input)
    {
        $old = get_option(STM_GDPR_SLUG);
        $old = is_array($old) ? $old : array();
        $input = is_array($input) ? $input : array();
        // Groups this page does not define (e.g. an add-on's) are kept as stored; only known groups are rebuilt.
        $output = $old;

        foreach ($this->stm_pluginOptions_fields() as $group => $fields) {

            $current = (isset($old[$group][0]) && is_array($old[$group][0])) ? $old[$group][0] : array();
            $posted = (isset($input[$group][0]) && is_array($input[$group][0])) ? $input[$group][0] : array();

            foreach ($fields as $id => $field) {

                if (!array_key_exists($id, $posted)) {
                    if ('toggle' === $field[0]) {
                        if (isset($input[$group])) {
                            $current[$id] = 'off';
                        }
                    } elseif ('checkbox' === $field[0]) {
                        unset($current[$id]);
                    }
                    continue;
                }

                $value = is_scalar($posted[$id]) ? (string) $posted[$id] : '';
                $clean = $this->stm_pluginOptions_sanitizeValue($field, $value);

                if ('' === $clean || false === $clean) {
                    unset($current[$id]);
                } else {
                    $current[$id] = $clean;
                }
            }

            $output[$group] = array($current);
        }

        return $output;
    }

    private function stm_pluginOptions_sanitizeValue($field, $value)
    {
        switch ($field[0]) {

            case 'checkbox':
                return $value ? 'on' : '';

            case 'toggle':
                return ('off' === $value || '' === $value) ? 'off' : 'on';

            case 'number':
                return (string) absint($value);

            case 'text':
                return sanitize_text_field($value);

            case 'html':
            case 'editor':
                return wp_kses_post($value);

            case 'color':
                $color = sanitize_hex_color($value);
                return $color ? $color : '';

            case 'select':
                return isset($field[3][$value]) ? $value : '';

            case 'pages':
                return (string) absint($value);

            case 'css':
                return trim(wp_strip_all_tags($value));

            case 'class':
                return trim(preg_replace('/[^A-Za-z0-9_\- ]/', '', $value));
        }

        return '';
    }

    public function stm_pluginOptions_renderPage()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $tabs = array(
            STM_GDPR_PREFIX . 'general' => __('General', 'gdpr-compliance-cookie-consent'),
            STM_GDPR_PREFIX . 'privacy' => __('Privacy Policy', 'gdpr-compliance-cookie-consent'),
            STM_GDPR_PREFIX . 'plugins' => __('Integrated plugins', 'gdpr-compliance-cookie-consent'),
            STM_GDPR_PREFIX . 'data_access' => __('Data Access & To Be Forgotten', 'gdpr-compliance-cookie-consent'),
        );
        $fields = $this->stm_pluginOptions_fields();
        $stored = get_option(STM_GDPR_SLUG);
        $stored = is_array($stored) ? $stored : array();
        $defaults = STM_Helpers::stm_heplers_get_default_options();

        ?>
        <div class="wrap stm-gdpr-settings">
            <h1><?php echo esc_html(STM_Helpers::stm_plugin_data()['Name']); ?></h1>
            <?php settings_errors(); ?>
            <form method="post" action="options.php">
                <?php settings_fields(self::SETTINGS_GROUP); ?>
                <nav class="nav-tab-wrapper stm-gdpr-tabs">
                    <?php foreach ($tabs as $group => $title) : ?>
                        <a href="#<?php echo esc_attr($group); ?>" class="nav-tab" data-href="<?php echo esc_attr($group); ?>"><?php echo esc_html($title); ?></a>
                    <?php endforeach; ?>
                </nav>
                <?php foreach (array_keys($tabs) as $group) : ?>
                    <div class="stm-gdpr-tab-content" id="<?php echo esc_attr($group); ?>">
                        <?php
                        $values = (isset($stored[$group][0]) && is_array($stored[$group][0])) ? $stored[$group][0] : array();
                        if (empty($stored)) {
                            $values = $defaults[$group][0];
                            unset($values['privacy_page']);
                        }
                        $this->stm_pluginOptions_renderGroupIntro($group);
                        ?>
                        <table class="form-table" role="presentation">
                            <?php foreach ($fields[$group] as $id => $field) : ?>
                                <?php $this->stm_pluginOptions_renderField($group, $id, $field, isset($values[$id]) ? $values[$id] : ''); ?>
                            <?php endforeach; ?>
                        </table>
                    </div>
                <?php endforeach; ?>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function stm_pluginOptions_renderGroupIntro($group)
    {
        if (STM_GDPR_PREFIX . 'general' === $group) {
            echo '<p>' . esc_html__('Ask visitors to accept using Cookie and data.', 'gdpr-compliance-cookie-consent') . '</p>';
        } elseif (STM_GDPR_PREFIX . 'plugins' === $group) {
            echo '<p>' . wp_kses(
                __('Our plugin currently supports <b>Contact Form 7</b>, <b>Gravity Forms</b>, <b>WooCommerce</b>, <b>WordPress Comments</b>, <b>MailChimp Wp</b>, <b>BuddyPress</b>. Integrated plugins options will be displayed automatically after installing and activating supported plugins. You can use HTML tags and <span>%privacy_policy%</span> shortcode link in the checkbox labels (HTML tags are NOT allowed for Gravity Forms due to plugin limitations).', 'gdpr-compliance-cookie-consent'),
                array('b' => array(), 'span' => array())
            ) . '</p>';
        } elseif (STM_GDPR_PREFIX . 'data_access' === $group) {
            echo '<p>' . wp_kses(
                __('You have to create a page with a shortcode: <span>[stm-gpdr-data-access]</span> or use <b>GDPR Compliance</b> widget.', 'gdpr-compliance-cookie-consent'),
                array('b' => array(), 'span' => array())
            ) . ' ';
            printf(
                /* translators: 1: link to the data export requests screen, 2: link to the data erasure requests screen */
                esc_html__('Also, here you can see %1$s and %2$s. This feature works only for WordPress 4.9.6 or higher versions.', 'gdpr-compliance-cookie-consent'),
                '<a href="' . esc_url(add_query_arg(array('page' => 'export_personal_data'), admin_url('tools.php'))) . '">' . esc_html__('Data Export Requests', 'gdpr-compliance-cookie-consent') . '</a>',
                '<a href="' . esc_url(add_query_arg(array('page' => 'remove_personal_data'), admin_url('tools.php'))) . '">' . esc_html__('Data Erasure Requests', 'gdpr-compliance-cookie-consent') . '</a>'
            );
            echo '</p>';
        }
    }

    private function stm_pluginOptions_renderField($group, $id, $field, $value)
    {
        $type = $field[0];
        $label = $field[1];
        $desc = isset($field[2]) ? $field[2] : '';
        $name = STM_GDPR_SLUG . '[' . $group . '][0][' . $id . ']';
        $dom_id = $group . '_' . $id;

        echo '<tr><th scope="row"><label for="' . esc_attr($dom_id) . '">' . esc_html($label) . '</label></th><td>';

        switch ($type) {

            case 'checkbox':
                echo '<label><input type="checkbox" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '" value="on"' . checked(!empty($value), true, false) . ' /> ' . esc_html($desc) . '</label>';
                $desc = '';
                break;

            case 'toggle':
                echo '<label><input type="checkbox" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '" value="on"' . checked('off' !== $value, true, false) . ' /> ' . esc_html($desc) . '</label>';
                $desc = '';
                break;

            case 'number':
                echo '<input type="number" min="0" class="regular-text" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;

            case 'text':
            case 'html':
            case 'class':
                echo '<input type="text" class="large-text" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;

            case 'color':
                echo '<input type="text" class="stm-gdpr-color" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
                break;

            case 'css':
                echo '<textarea class="large-text code" rows="8" id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '">' . esc_textarea($value) . '</textarea>';
                break;

            case 'select':
                echo '<select id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '">';
                foreach ($field[3] as $key => $title) {
                    echo '<option value="' . esc_attr($key) . '"' . selected((string) $value, (string) $key, false) . '>' . esc_html($title) . '</option>';
                }
                echo '</select>';
                break;

            case 'pages':
                if ('' === $value) {
                    $value = STM_Helpers::stm_helpers_get_privacy_page();
                }
                echo '<select id="' . esc_attr($dom_id) . '" name="' . esc_attr($name) . '">';
                foreach (STM_Helpers::stm_helpers_cmb_pages_array() as $key => $title) {
                    echo '<option value="' . esc_attr($key) . '"' . selected((string) $value, (string) $key, false) . '>' . esc_html($title) . '</option>';
                }
                echo '</select>';
                break;

            case 'editor':
                wp_editor((string) $value, $dom_id, array(
                    'textarea_name' => $name,
                    'textarea_rows' => 10,
                    'media_buttons' => false,
                ));
                break;
        }

        if ('' !== $desc) {
            echo '<p class="description">' . esc_html($desc) . '</p>';
        }

        echo '</td></tr>';
    }

    public static function getInstance()
    {

        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

}
