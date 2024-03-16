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
            filterSelector: '[data-ff-filter="${key}"]',
            filterKey: 'ffFilter',
            hideClass: 'd-none'
        },
        PaginationHandler: {
            pathToScripts: './modules/paginationhandler.js?v=56554',
            sendEvent: 'si:send:finish',
            paginationWrapSelector: '[data-pn-pagination]',
            firstPageBtnSelector: '[data-pn-first]',
            lastPageBtnSelector: '[data-pn-last]',
            lastPageKey: 'pnLast',
            prevPageBtnSelector: '[data-pn-prev]',
            nextPageBtnSelector: '[data-pn-next]',
            currentPageInputSelector: '[data-pn-current]',
            totalPagesSelector: '[data-pn-total]',
            hideClass: 'd-none'
        }
    };
}