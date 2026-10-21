<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('gdpr-compliance-cookie-consent');
delete_option('gdpr-compliance-cookie-consent-lt');
delete_metadata('user', 0, 'stm_gdpr_cookie', '', true);
