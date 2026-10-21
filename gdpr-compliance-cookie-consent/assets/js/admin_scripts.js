jQuery(document).ready(function ($) {

    var $tabs = $('.stm-gdpr-tabs .nav-tab');
    var $panels = $('.stm-gdpr-tab-content');
    var first = $tabs.first().data('href');

    // Only ids that belong to a tab are accepted from the URL hash.
    function isTab(id) {
        return $tabs.filter(function () {
            return $(this).data('href') === id;
        }).length > 0;
    }

    function showTab(id) {
        $tabs.removeClass('nav-tab-active').filter(function () {
            return $(this).data('href') === id;
        }).addClass('nav-tab-active');
        $panels.hide().filter(function () {
            return this.id === id;
        }).show();
        try {
            window.sessionStorage.setItem('stm_gdpr_tab', id);
        } catch (e) { /* storage may be unavailable */ }
    }

    var start = window.location.hash.replace('#', '');
    if (!isTab(start)) {
        try {
            start = window.sessionStorage.getItem('stm_gdpr_tab') || '';
        } catch (e) {
            start = '';
        }
    }
    showTab(isTab(start) ? start : first);

    $tabs.on('click', function (event) {
        event.preventDefault();
        showTab($(this).data('href'));
    });

    $('.stm-gdpr-color').wpColorPicker();

});
