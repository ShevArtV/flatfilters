export default function returnConfigs() {
    return {
        SaveFormData: {
            pathToScripts: './modules/saveformdata.js',
            rootSelector: '[data-si-form]',
            rootKey: 'siForm',
            resetEvent: 'si:send:reset'
        },
        Notify: {
            pathToScripts: './modules/notify.js',
            jsPath: 'assets/components/sendit/js/web/lib/izitoast/iziToast.min.js',
            cssPath: 'assets/components/sendit/css/web/lib/izitoast/iziToast.min.css',
            handlerClassName: 'iziToast',
            toastSelector: '.iziToast',
            typeSelectors: {
                success: '.iziToast-color-green',
                info: '.iziToast-color-blue',
                error: '.iziToast-color-red',
                warning: '.iziToast-color-yellow',
            },
            titleSelector: '.iziToast-title',
            handlerOptions: {
                timeout: 2500,
                position: "topCenter"
            }
        },
        QuizForm: {
            pathToScripts: './modules/quizform.js',
            rootSelector: '[data-si-form]',
            rootKey: 'siForm',
            autoKey: 'qfAuto',
            itemSelector: '[data-qf-item]',
            itemKey: 'qfItem',
            itemCompleteSelector: '[data-qf-complete="1"]',
            itemCompleteKey: 'qfComplete',
            finishSelector: '[data-qf-finish]',
            itemCurrentSelector: '[data-qf-item="${currentIndex}"]',
            btnSelector: '[data-qf-btn]',
            btnKey: 'qfBtn',
            btnNextSelector: '[data-qf-btn="next"]',
            btnPrevSelector: '[data-qf-btn="prev"]',
            btnSendSelector: '[data-qf-btn="send"]',
            btnResetSelector: '[data-qf-btn="reset"]',
            nextIndexSelector: '[data-qf-next]',
            nextIndexKey: 'qfNext',
            progressSelector: '[data-qf-progress]',
            currentQuestionSelector: '[data-qf-page]',
            totalQuestionSelector: '[data-qf-total]',
            pagesSelector: '[data-qf-pages]',
            progressValueSelector: '[data-qf-progress-value]',
            activeClass: 'active',
            visabilityClass: 'v_hidden',
            disabledClass: 'disabled',
            sendEvent: 'si:send:finish',
        },
        Sending: {
            pathToScripts: './modules/sending.js?v=325534567565435',
            rootSelector: '[data-si-form]',
            rootKey: 'siForm',
            presetKey: 'siPreset',
            eventKey: 'siEvent',
            goalKey: 'siGoal',
            actionUrl: 'assets/components/sendit/action.php',
            antiSpamEvent: 'keydown',
            errorBlockSelector: '[data-si-error="${fieldName}"]',
            eventSelector: '[data-si-event="${eventName}"]',
            errorClass: 'si-error'
        },
        FileUploader:{
            pathToScripts: './modules/fileuploader.js',
            formSelector: '[data-si-form]',
            rootSelector: '[data-fu-wrap]',
            fieldSelector: '[data-fu-field]',
            rootKey: 'fuWrap',
            presetKey: 'siPreset',
            sendEvent: 'si:send:after',
            pathKey: 'fuPath',
            pathAttr: 'data-fu-path',
            actionUrl: 'assets/components/sendit/action.php',
            layout: {
                list: {
                    tagName: 'ul',
                    classNames: ['file-list', 'list_unstyled', 'd_flex', 'flex_wrap', 'gap_col-10', 'pt-20'],
                    selector: '.file-list'
                },
                item: {
                    tagName: 'li',
                    classNames: ['file-list__item'],
                    parentSelector: '.file-list',
                    selector: '.file-list__item'
                },
                btn: {
                    tagName: 'button',
                    classNames: ['file-list__btn', 'btn', 'py-5', 'px-20', 'ta_center', 'border-1', 'border_error', 'hover_bg_error', 'radius_pill', 'hover_color_light'],
                    parentSelector: '.file-list__item',
                    selector: '[data-fu-path="${filepath}"]',
                    type: 'button',
                    text: '${filename}&nbsp;X'
                },
                input: {
                    classNames: ['file-list__input'],
                    tagName: 'input',
                    type: 'hidden',
                    selector: '.file-list__input'
                }
            }
        }
    }
}