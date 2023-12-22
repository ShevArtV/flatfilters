export default class RangeSlider {
    constructor(config) {
        if(window.FlatFilters && window.FlatFilters.RangeSlider) return window.FlatFilters.RangeSlider;
        const defaults = {
            jsPath: 'assets/components/flatfilters/js/web/libs/nouislider/nouislider.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/nouislider/nouislider.css',
            formSelector: '[data-si-form]',
            rangeSelector: '[data-ff-range]',
            rangeSelectorAlt: '[data-ff-range="${key}"]',
            rangeKey: 'ffRange',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax'
        }

        this.config = Object.assign(defaults, config);
        this.loadScript(this.config.jsPath, this.initialize.bind(this), this.config.cssPath);
    }

    loadScript(path, callback, cssPath) {
        if (document.querySelector('script[src="' + path + '"]')){
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

    initialize(){
        const ranges = document.querySelectorAll(this.config.rangeSelector);
        if(ranges.length){
            ranges.forEach(el => {
                this.createRange(el);
            });
        }
    }

    createRange(el){
        const {min, max, startField, endField, start, end} = this.getItems(el.dataset[this.config.rangeKey]);

        noUiSlider.create(el, {
            start: [start, end],
            connect: true,
            range: {
                'min': min,
                'max': max
            },
            step: 1
        });

        startField.addEventListener('change', (e) => {
            if(e.isTrusted){
                el.noUiSlider.set([startField.value, null]);
            }
        });
        endField.addEventListener('change', (e) => {
            if(e.isTrusted){
                el.noUiSlider.set([null, endField.value]);
            }
        });

        el.noUiSlider.on('update', (values, handle) => {
            startField.value = values[0];
            endField.value = values[1];
        });

        el.noUiSlider.on('change', (values, handle) => {
            SendIt?.setComponentCookie('sitrusted', '1')
            if(handle === 1){
                endField.dispatchEvent(new Event('change', {bubbles: true}));
            }else{
                startField.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
    }

    getItems(key){
        const rangeSelector = this.config.rangeSelectorAlt.replace('${key}', key);
        const el = document.querySelector(rangeSelector);
        const min = Number(el.dataset[this.config.minKey]);
        const max = Number(el.dataset[this.config.maxKey]);
        const startField = document.querySelector(this.config.startFieldSelector.replace('${key}', key));
        const endField = document.querySelector(this.config.endFieldSelector.replace('${key}', key));
        const start = Number(startField.value);
        const end = Number(endField.value);
        return {el, min, max, startField, endField, start, end}
    }

    reset(key){
        const {el, min, max} = this.getItems(key);
        el.noUiSlider.set([min, max]);
    }
}
