export default class DatePicker {
    constructor(config) {
        if (window.FlatFilters && window.FlatFilters.DatePicker) return window.FlatFilters.DatePicker;
        const defaults = {
            jsPath: 'assets/components/flatfilters/js/web/libs/airdatepicker/air-datepicker.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/airdatepicker/air-datepicker.min.css',
            formSelector: '[data-si-form]',
            pickerSelector: '[data-ff-datepicker]',
            pickerKey: 'ffDatepicker',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax'
        }

        this.config = Object.assign(defaults, config);
        this.form = document.querySelector(this.config.formSelector);
        this.instances = new Map();
        this.loadScript(this.config.jsPath, this.initialize.bind(this), this.config.cssPath);
    }

    loadScript(path, callback, cssPath) {
        if (document.querySelector('script[src="' + path + '"]')) {
            callback(path, "ok");
            return;
        }
        let done = false,
            scr = document.createElement('script');

        scr.onload = handleLoad;
        scr.onreadystatechange = handleReadyStateChange;
        scr.onerror = handleError;
        scr.src = path;
        document.body.appendChild(scr);

        function handleLoad() {
            if (!done) {
                if (cssPath) {
                    let css = document.createElement('link');
                    css.rel = 'stylesheet';
                    css.href = cssPath;
                    document.head.prepend(css);
                }
                done = true;
                callback(path, "ok");
            }
        }

        function handleReadyStateChange() {
            let state;

            if (!done) {
                state = scr.readyState;
                if (state === "complete") {
                    handleLoad();
                }
            }
        }

        function handleError() {
            if (!done) {
                done = true;
                callback(path, "error");
            }
        }
    }

    initialize() {
        const datepickers = document.querySelectorAll(this.config.pickerSelector);
        if (datepickers.length) {
            datepickers.forEach(el => {
                this.createDatepicker(el);
            });
        }
    }

    createDatepicker(el) {
        const min = new Date(Number(`${el.dataset[this.config.minKey]}000`));
        const max = new Date(Number(`${el.dataset[this.config.maxKey]}000`));
        const dp = new AirDatepicker(el, {
            position: 'bottom center',
            dateFormat: 'dd.MM.yyyy HH:mm',
            maxDate: max,
            minDate: min,
            range: true,
            onSelect({date, formattedDate, datepicker}) {
                el.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
        this.instances.set(el, dp);
    }

    reset(el) {
        this.instances.get(el).clear();
    }
}