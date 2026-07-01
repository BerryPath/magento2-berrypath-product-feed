define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        var selector = config.feedTypeSelector || '#feed_type',
            tabsId = config.tabsId || 'berrypath_productfeed_profile_tabs',
            generalTabId = config.generalTabId || 'berrypath_productfeed_profile_tabs_general',
            typeTabs = config.typeTabs || {};

        function refreshTabs($tabs) {
            try {
                $tabs.tabs('refresh');
            } catch (e) {
            }
        }

        function showGeneralTab($hiddenActiveTab) {
            var $generalLink = $('li#' + generalTabId + ' > a');

            if (($hiddenActiveTab.hasClass('ui-tabs-active') || $hiddenActiveTab.hasClass('_active')) && $generalLink.length) {
                $generalLink.trigger('click');
            }
        }

        function normalizeAllowedTypes(value) {
            if (Array.isArray(value)) {
                return value;
            }

            if (typeof value === 'string') {
                return value.split(',').map(function (item) {
                    return item.trim();
                });
            }

            return [];
        }

        function toggleTypeTabs() {
            var $select = $(selector),
                $tabs = $('#' + tabsId),
                selectedType = $select.val();

            if (!$select.length) {
                return;
            }

            $.each(typeTabs, function (tabId, allowedTypes) {
                var $tab = $('li#' + tabId),
                    allowed = normalizeAllowedTypes(allowedTypes),
                    shouldShow = allowed.indexOf(selectedType) !== -1;

                if (!$tab.length) {
                    return;
                }

                if (shouldShow) {
                    $tab.show().removeClass('no-display');
                } else {
                    showGeneralTab($tab);
                    $tab.hide();
                }
            });

            refreshTabs($tabs);
        }

        $(toggleTypeTabs);
        setTimeout(toggleTypeTabs, 250);
        $(document).on('change', selector, toggleTypeTabs);
    };
});
