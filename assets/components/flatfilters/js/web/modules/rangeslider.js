export default class RangeSlider {
    constructor(config) {
        if (window.FlatFilters && window.FlatFilters.RangeSlider) return window.FlatFilters.RangeSlider;
        const defaults = {
            jsPath: 'assets/components/flatfilters/js/web/libs/nouislider/nouislider.min.js',
            cssPath: 'assets/components/flatfilters/css/web/libs/nouislider/nouislider.css',
            formSelector: '[data-ff-form]',
            rangeSelector: '[data-ff-range]',
            rangeSelectorAlt: '[data-ff-range="${key}"]',
            rangeKey: 'ffRange',
            stepKey: 'ffStep',
            startFieldSelector: '[data-ff-start="${key}"]',
            endFieldSelector: '[data-ff-end="${key}"]',
            minKey: 'ffMin',
            maxKey: 'ffMax',
            touchedKey: 'ffTouched',
            sendEvent: 'si:send:finish'
        }

        this.config = Object.assign(defaults, config);
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
        const ranges = document.querySelectorAll(this.config.rangeSelector);
        if (ranges.length) {
            ranges.forEach(el => {
                this.createRange(el);
            });
        }

        // При фильтрации сервер пересчитывает min/max по выборке и присылает их
        // в filterValues (disabling-ответ). Подхватываем и сужаем границы слайдера.
        document.addEventListener(this.config.sendEvent, (e) => {
            const filterValues = e.detail?.result?.data?.filterValues;
            if (!filterValues) return;
            Object.entries(filterValues).forEach(([key, item]) => {
                if (item && typeof item.type === 'string' && item.type.includes('range')
                    && item.min != null && item.max != null) {
                    this.updateRange(key, Number(item.min), Number(item.max));
                }
            });
        });
    }

    /**
     * Обновить границы слайдера под новую выборку, сохранив выбор пользователя.
     * Если ручки стояли на краях (пользователь не сужал) — растягиваем на новый
     * диапазон; иначе прижимаем текущий выбор к новым границам.
     */
    updateRange(key, newMin, newMax) {
        const rangeSelector = this.config.rangeSelectorAlt.replace('${key}', key);
        const el = document.querySelector(rangeSelector);
        if (!el || !el.noUiSlider) return;
        if (!Number.isFinite(newMin) || !Number.isFinite(newMax)) return;
        // noUiSlider требует min < max строго
        if (newMax <= newMin) newMax = newMin + 1;

        const touched = !!el.dataset[this.config.touchedKey];
        const [curStart, curEnd] = el.noUiSlider.get().map(Number);

        // Границы приходят посчитанными БЕЗ собственного фильтра (сервер), поэтому
        // сужать себя они не могут. Нетронутый слайдер прижимаем ручки к краям
        // (показ доступного), тронутый — сохраняем выбор пользователя внутри границ.
        el.noUiSlider.updateOptions({ range: { min: newMin, max: newMax } }, false);
        const start = touched ? Math.max(newMin, Math.min(curStart, newMax)) : newMin;
        const end = touched ? Math.min(newMax, Math.max(curEnd, newMin)) : newMax;
        el.noUiSlider.set([start, end], false);
    }

    createRange(el) {
        if (typeof noUiSlider === 'undefined') return;

        const {min, max, startField, endField, start, end} = this.getItems(el.dataset[this.config.rangeKey]);
        noUiSlider.create(el, {
            start: [start, end],
            range: {
                'min': min,
                'max': max
            },
            connect: true,
            step: el.dataset[this.config.stepKey] ? Number(el.dataset[this.config.stepKey]) : 1
        });

        startField.addEventListener('change', (e) => {
            if (e.isTrusted) {
                el.dataset[this.config.touchedKey] = '1';
                el.noUiSlider.set([startField.value, null]);
            }
        });
        endField.addEventListener('change', (e) => {
            if (e.isTrusted) {
                el.dataset[this.config.touchedKey] = '1';
                el.noUiSlider.set([null, endField.value]);
            }
        });

        el.noUiSlider.on('update', (values, handle) => {
            startField.value = values[0];
            endField.value = values[1];
        });

        el.noUiSlider.on('change', (values, handle) => {
            SendIt?.setComponentCookie('sitrusted', '1')
            el.dataset[this.config.touchedKey] = '1';
            if (handle === 1) {
                endField.dispatchEvent(new Event('change', {bubbles: true}));
            } else {
                startField.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
    }

    getItems(key) {
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

    reset(key) {
        const {el, min, max} = this.getItems(key);
        delete el.dataset[this.config.touchedKey];
        el.noUiSlider.set([min, max]);
    }
}
