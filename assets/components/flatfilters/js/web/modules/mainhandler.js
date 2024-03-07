export default class MainHandler {
    constructor(config) {
        if (window.FlatFilters && window.FlatFilters.MainHandler) return window.FlatFilters.MainHandler;
        const defaults = {
            sendEvent: 'si:send:finish',
            beforeSendEvent: 'si:send:before',
            formSelector: '[data-ff-form]',
            formKey: 'ffForm',
            resultSelector: '[data-ff-results]',
            resetBtnSelector: '[data-ff-reset]',
            filtersSelector: '[data-ff-filter]',
            filterSelector: '[data-ff-filter="${key}"]',
            filterKey: 'ffFilter',
        }

        this.config = Object.assign(defaults, config);
        this.form = document.querySelector(this.config.formSelector);
        this.resetBtn = document.querySelector(this.config.resetBtnSelector);
        this.presets = SendIt.getComponentCookie('presets', 'FlatFilters');
        this.initialize();
    }

    initialize() {
        document.addEventListener(this.config.beforeSendEvent, (e) => {
            if (e.detail.target.closest(this.config.formSelector)) {
                this.beforeSendHandler(e)
            }
        })

        document.addEventListener('change', (e) => {
            if (e.target.closest(this.config.filtersSelector)) {
                this.changeHandler(e);
                this.sendResponse(this.presets.filtering);
            }
        })

        document.addEventListener(this.config.sendEvent, (e) => {
            this.responseHandler(e.detail.result);
        })

        document.addEventListener('click', (e) => {
            if (e.target.closest(this.config.resetBtnSelector)) {
                this.resetHandler(e)
            }
        })

        if (window.location.search) {
            this.sendResponse(this.presets.disabling);
        } else {
            this.resetBtn.style.display = 'none';
        }
    }

    resetHandler(e) {
        e.preventDefault();
        const filters = document.querySelectorAll(this.config.filtersSelector);
        filters.forEach(filter => {
            const type = this.getElemType(filter);
            switch (type) {
                case 'select':
                case 'multiple':
                    const options = Array.from(filter.options);
                    options.forEach(option => {
                        option.selected = false
                    });
                    options[0].selected = true;
                    break;
                case 'checkbox':
                    filter.checked = false;
                    break;
                case 'daterange':
                    FlatFilters.DatePicker.reset(filter);
                    break;
                case 'numrange':
                    FlatFilters.RangeSlider.reset(filter.dataset[this.config.filterKey]);
                    break;
            }
            this.setSearchParams(type, filter.dataset[this.config.filterKey], '');
        });

        this.resetBtn.style.display = 'none';
        this.sendResponse(this.presets.filtering);
    }

    beforeSendHandler(e) {
        e.detail.fetchOptions.headers['X-SIFORM'] = e.detail.target.closest(this.config.formSelector).dataset[this.config.formKey];
    }

    changeHandler(e) {
        //console.log(e.target)
        const elem = e.target.closest(this.config.filtersSelector);
        const key = elem.dataset[this.config.filterKey];
        const type = this.getElemType(elem);
        this.setSearchParams(type, key, elem.value);
    }

    async sendResponse(preset) {
        SendIt.setComponentCookie('sitrusted', '1');
        await SendIt.Sending.prepareSendParams(this.form, preset, 'change');
    }

    responseHandler(result) {
        //console.log(result);
        if(!result.data) return;
        const resultsBlock = document.querySelector(this.config.resultSelector);
        const filters = document.querySelectorAll(this.config.filtersSelector);

        if (result.data.resources && resultsBlock) {
            resultsBlock.innerHTML = result.data.resources;
        }

        if (!result.data.getDisabled) {
            this.setDisabled(filters, result.data);
        } else {
            this.sendResponse(this.presets.disabling);
        }

        if (result.data.totalTime) {
            document.querySelector('#time').textContent = result.data.totalTime;
        }
        if (result.data.totalResources) {
            document.querySelector('#total').textContent = result.data.totalResources;
        }

        window.location.search && (this.resetBtn.style.display = 'block');
    }

    setDisabled(filters, data){
        filters.forEach(el => {
            const key = el.dataset[this.config.filterKey];
            if (data.filterValues && data.filterValues[key] && data.filterValues[key]['values']) {
                const type = this.getElemType(el);
                switch (type) {
                    case 'select':
                        for (let i = 1; i < el.options.length; i++) {
                            el.options[i].disabled = !data.filterValues[key]['values'].includes(el.options[i].value);
                        }
                        break;
                    case 'radio':
                        el.disabled = !data.filterValues[key]['values'].includes(el.value);
                        break;
                    case 'checkbox':
                        const selector = this.config.filterSelector.replace('${key}', key);
                        if (document.querySelectorAll(selector).length === 1) {
                            el.disabled = !data.filterValues[key]['values'].includes(el.value);
                        }
                        break;
                }
            }
        });
    }

    setSearchParams(type, key, value = '') {
        const url = window.location.href;
        let params = new URLSearchParams(window.location.search);

        switch (type) {
            case 'checkbox':
                params = this.setCheckboxParam(key, params);
                break;
            case 'multiple':
                params = this.setMultipleParam(key, params);
                break;
            case 'numrange':
                params = this.setNumrangeParam(key, params);
                break;
            default:
                params = this.setTextParam(key, value, params);
                break;
        }


        if (params.toString()) {
            window.history.replaceState({}, '', url.split('?')[0] + '?' + params.toString());
        } else {
            window.history.replaceState({}, '', url.split('?')[0]);
        }
    }

    setTextParam(key, value, params) {
        if (value) {
            params.set(key, value);
        } else {
            params.delete(key);
        }
        return params;
    }

    setNumrangeParam(key, params) {
        params.delete(key);
        const {el, min, max, startField, endField, start, end} = FlatFilters.RangeSlider.getItems(key);
        if (Number(startField.value) !== min || Number(endField.value) !== max) {
            params.set(key, `${startField.value},${endField.value}`);
        }
        return params;
    }

    setMultipleParam(key, params) {
        const elem = document.querySelector(this.config.filterSelector.replace('${key}', key));
        params.delete(key);
        const values = [];
        Array.from(elem.options).forEach(el => {
            if (el.selected && el.value) values.push(el.value);
        });
        if (values.length) {
            params.set(key, values.join(','));
        }
        return params;
    }

    setCheckboxParam(key, params) {
        params.delete(key);
        const checkboxes = document.querySelectorAll(this.config.filterSelector.replace('${key}', key) + ':checked');
        if (checkboxes.length) {
            const values = [];
            checkboxes.forEach(el => values.push(el.value));
            params.set(key, values.join(','));
        }

        return params;
    }

    getElemType(elem) {
        let type = 'text';
        switch (elem.tagName) {
            case 'INPUT':
                const startFieldSelector = FlatFilters.RangeSlider.config.startFieldSelector.replace('="${key}"', '');
                const endFieldSelector = FlatFilters.RangeSlider.config.endFieldSelector.replace('="${key}"', '');
                const pickerSelector = FlatFilters.DatePicker.config.pickerSelector;
                if (elem.closest(startFieldSelector) || elem.closest(endFieldSelector)) {
                    type = 'numrange';
                }
                if (elem.closest(pickerSelector)) {
                    type = 'daterange';
                }
                (type === 'text') && (type = elem.type);
                break;
            case 'SELECT':
                if (elem.multiple) {
                    type = 'multiple';
                } else {
                    type = 'select'
                }
                break;
        }
        return type;
    }
}