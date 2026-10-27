/**
 * @var object stm_gdpr_vars
 */
jQuery(document).ready(function ($) {

    var COOKIE = 'stm_gdpr_cookie';
    var consent = window.stmGdprConsent || null;
    var $popup = $('#stm_gdpr_popup-main');

    function hasChoice() {
        return document.cookie.indexOf(COOKIE + '=') !== -1 || !!stm_gdpr_vars.accepted;
    }

    // The banner is always printed hidden; the browser decides, so a cached page never shows a stale state.
    if ($popup.length && !hasChoice()) {
        $popup.show();
    }

    function storeChoice(value) {
        var cookie = COOKIE + '=' + encodeURIComponent(value) + '; path=/; max-age=' + parseInt(stm_gdpr_vars.expire, 10) + '; SameSite=Lax';
        if (window.location.protocol === 'https:') {
            cookie += '; Secure';
        }
        // The cookie blocker lets this one name through.
        document.cookie = cookie;
    }

    function token() {
        return Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    }

    /**
     * Records the visitor's choice: cookie, Consent Mode update, server mirror and event.
     * categories: {analytics: bool, marketing: bool}. Also available to themes as window.stmGdprSetConsent().
     */
    function setConsent(categories, choice) {
        var all = categories.analytics && categories.marketing;
        var none = !categories.analytics && !categories.marketing;
        var value;
        if (none) {
            value = 'declined';
        } else if (all) {
            value = token();
        } else {
            value = token() + '.a' + (categories.analytics ? 1 : 0) + 'm' + (categories.marketing ? 1 : 0);
        }
        storeChoice(value);
        if (consent) {
            consent.update(consent.parse(value));
        }
        $.post(stm_gdpr_vars.AjaxUrl, {
            "action": "stm_gdpr_cookie_accept",
            "choice": none ? 'decline' : 'accept',
            "nonce": stm_gdpr_vars.accept_nonce
        });
        $popup.fadeOut();
    }

    window.stmGdprSetConsent = function (categories) {
        setConsent({analytics: !!(categories && categories.analytics), marketing: !!(categories && categories.marketing)});
    };

    $('#stm_gdpr_popup_accept').on('click', function (e) {
        e.preventDefault();
        setConsent({analytics: true, marketing: true});
    });

    $('#stm_gdpr_popup_decline').on('click', function (e) {
        e.preventDefault();
        setConsent({analytics: false, marketing: false});
    });

    $('#stm-gpdr-form').on('submit', function(event) {
        event.preventDefault();

        var	data = {
                'action': 'stm_gpdr_data_request',
                'stm_gpdr_data_type' : $( 'input[name=stm_gpdr_data_type]:checked', '#stm-gpdr-form').val(),
                'stm_gpdr_data_email': $( '#stm_gpdr_data_email' ).val(),
                'nonce': stm_gdpr_vars.data_request_nonce
            };

        $( '.stm-gdpr-errors' ).remove();
        $( '.stm-gdpr-success' ).remove();

        $.ajax({
            url: stm_gdpr_vars.AjaxUrl,
            type: 'post',
            data: data,
            error: function() {
                $('#stm-gpdr-form').append( '<div class="stm-gdpr-errors" style="display:none;">' + stm_gdpr_vars.error_prefix + '<br />' + stm_gdpr_vars.request_failed + '</div>' );
                $('.stm-gdpr-errors').slideDown();
            },
            success: function( response ) {
                if ( 'success' !== response.data ) {
                    $('#stm-gpdr-form').append( '<div class="stm-gdpr-errors" style="display:none;">' + stm_gdpr_vars.error_prefix + '<br />' + response.data + '</div>' );
                    $('.stm-gdpr-errors').slideDown();
                } else {
                    $('#stm-gpdr-form').append( '<div class="stm-gdpr-success" style="display:none;">' + stm_gdpr_vars.success + '</div>' );
                    $('.stm-gdpr-success').slideDown();
                }
            }
        });
    });

});
