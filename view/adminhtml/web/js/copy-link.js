define(['jquery', 'mage/translate'], function ($) {
    'use strict';

    return function (config) {
        var buttonSelector = config.buttonSelector || '[data-bp-copy-feed-url]';

        function fallbackCopy(value) {
            var textarea = document.createElement('textarea');

            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.top = '-1000px';
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        }

        function copy(value) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                return navigator.clipboard.writeText(value);
            }

            fallbackCopy(value);

            return $.Deferred().resolve().promise();
        }

        $(document).on('click', buttonSelector, function () {
            var button = this,
                $button = $(button),
                value = $button.data('bp-copy-feed-url'),
                originalText = $button.text();

            if (!value) {
                return;
            }

            copy(value).then(function () {
                $button.text($.mage.__('Copied'));
                window.setTimeout(function () {
                    $button.text(originalText);
                }, 1600);
            });
        });
    };
});
