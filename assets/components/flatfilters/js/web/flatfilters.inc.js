export default function returnConfigs() {
    return {
        RangeSlider: {
            pathToScripts: './modules/rangeslider.js?v=55736345876787845',
            jsPath: 'assets/components/flatfilters/js/web/libs/nouislider/nouislider.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/nouislider/nouislider.css',
            formSelector: '[data-si-form]',
            rangeSelector: '[data-ff-range]',
            rangeKey: 'ffRange',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax'
        },
        DatePicker: {
            pathToScripts: './modules/datepicker.js?v=565ghf657546754',
        },
        MainHandler: {
            pathToScripts: './modules/mainhandler.js?v=565ghf657546754',
            sendEvent: 'si:send:finish',
            resultSelector: '[data-ff-results]'
        },
        PaginationHandler: {
            pathToScripts: './modules/paginationhandler.js?v=565g7f6754',
            paginationWrapSelector: '[data-pn-pagination]',
            gotoBtnSelector: '[data-pn-goto]',
            gotoKey: 'pnGoto',
            prevPageBtnSelector: '[data-pn-prev]',
            nextPageBtnSelector: '[data-pn-next]',
            currentPageInputSelector: '[data-pn-current]',
            totalPagesSelector: '[data-pn-total]',
        }
    };
}