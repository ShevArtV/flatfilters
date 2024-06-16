export default function returnConfigs() {
    return {
        RangeSlider: {
            pathToScripts: './modules/rangeslider.js?v=55736345876787845',
            jsPath: 'assets/components/flatfilters/js/web/libs/nouislider/nouislider.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/nouislider/nouislider.css',
            formSelector: '[data-ff-form]',
            rangeSelector: '[data-ff-range]',
            rangeSelectorAlt: '[data-ff-range="${key}"]',
            rangeKey: 'ffRange',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax'
        },
        DatePicker: {
            pathToScripts: './modules/datepicker.js?v=565ghf657546754',
            jsPath: 'assets/components/flatfilters/js/web/libs/airdatepicker/air-datepicker.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/airdatepicker/air-datepicker.min.css',
            formSelector: '[data-si-form]',
            pickerSelector: '[data-ff-datepicker]',
            pickerKey: 'ffDatepicker',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax'
        },
        MainHandler: {
            pathToScripts: './modules/mainhandler.js?v=565ghf657546754',
            sendEvent: 'si:send:finish',
            beforeSendEvent: 'si:send:before',
            formSelector: '[data-ff-form]',
            formKey: 'ffForm',
            resultSelector: '[data-ff-results]',
            resetBtnSelector: '[data-ff-reset]',
            filtersSelector: '[data-ff-filter]',
            selectedSelector: '[data-ff-selected]',
            totalSelector: '[data-ff-total]',
            timeSelector: '[data-ff-time]',
            tplSelector: '[data-ff-tpl]',
            itemSelector: '[data-ff-item="${key}-${value}"]',
            filterSelector: '[data-ff-filter="${key}"]',
            filterKey: 'ffFilter',
            captionKey: 'ffCaption',
            itemKey: 'ffItem',
            totalKey: 'ffTotal',
            hideClass: 'v_hidden',
        }
    };
}
