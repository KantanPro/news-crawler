(function ($) {
    'use strict';

    $(function () {
        $('.nc-x-auth-method').on('change', function () {
            var method = $('.nc-x-auth-method:checked').val();
            $('.nc-x-auth-panel').hide();
            if (method === 'oauth1') {
                $('.nc-x-auth-oauth1').show();
            } else {
                $('.nc-x-auth-oauth2').show();
            }
        });
    });
})(jQuery);
