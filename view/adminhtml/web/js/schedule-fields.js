define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        var modeSelector = config.modeSelector || '#schedule_enabled',
            fieldSelectors = config.fieldSelectors || [config.cronFieldSelector || '#schedule_cron_expression'];

        function getFieldRow($field) {
            var $row = $('#row_' + $field.attr('id'));

            if ($row.length) {
                return $row;
            }

            return $field.closest('.admin__field, tr');
        }

        function toggleCronField() {
            var $mode = $(modeSelector),
                isScheduled = $mode.val() === '1';

            if (!$mode.length) {
                return;
            }

            $.each(fieldSelectors, function (index, fieldSelector) {
                var $field = $(fieldSelector),
                    $row;

                if (!$field.length) {
                    return;
                }

                $row = getFieldRow($field);
                if (isScheduled) {
                    $row.show().removeClass('no-display');
                } else {
                    $row.hide();
                }
            });
        }

        $(toggleCronField);
        setTimeout(toggleCronField, 250);
        $(document).on('change', modeSelector, toggleCronField);
    };
});
