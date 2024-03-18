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
            selectedSelector: '[data-ff-selected]',
            tplSelector: '[data-ff-tpl]',
            itemSelector: '[data-ff-item="${key}-${value}"]',
            filterSelector: '[data-ff-filter="${key}"]',
            filterKey: 'ffFilter',
            captionKey: 'ffCaption',
            itemKey: 'ffItem',
            hideClass: 'd-none'
        }
        this.events = {
            disabling: 'ff:values:disabled',
            loaded: 'ff:results:loaded',
            reset: 'ff:after:reset',
            render: 'ff:before:render',
            remove: 'ff:before:remove',
        };
        this.config = Object.assign(defaults, config);
        this.form = document.querySelector(this.config.formSelector);
        this.resetBtn = document.querySelector(this.config.resetBtnSelector);
        this.presets = SendIt.getComponentCookie('presets', 'FlatFilters');
        this.selected = document.querySelector(this.config.selectedSelector);
        this.params = new URLSearchParams(window.location.search);
        this.captions = {};
        this.initialize();
    }

    initialize() {

        document.addEventListener(this.config.beforeSendEvent, (e) => {
            if (e.detail.target.closest(this.config.formSelector) && this.checkPreset(e.detail)) {
                this.beforeSendHandler(e)
            }
        })

        document.addEventListener(this.config.sendEvent, (e) => {
            if (this.checkPreset(e.detail)) {
                this.responseHandler(e.detail.result);
            }
        })

        document.addEventListener('change', async (e) => {
            if (e.target.closest(this.config.filtersSelector)) {
                await this.filter(e.target.closest(this.config.filtersSelector))
            }
        })

        document.addEventListener('submit', async (e) => {
            if (e.target.closest(this.config.formSelector)) {
                e.preventDefault();
                await this.submit(e);
            }
        })

        document.addEventListener('click', async (e) => {
            const valueItemSelector = this.config.itemSelector.replace('="${key}-${value}"', '');
            if (e.target.closest(this.config.resetBtnSelector)) {
                e.preventDefault();
                await this.reset()
            }
            if (e.target.closest(valueItemSelector)) {
                this.clearFilter(e.target.closest(valueItemSelector))
            }
        })

        document.addEventListener('ff:init', (e) => {
            if (window.location.search) {
                this.sendResponse(this.presets.disabling);
                if (this.selected) {
                    this.showSelectedValues();
                }
            } else {
                this.resetBtn && (this.resetBtn.classList.add(this.config.hideClass));
            }
        })
    }

    async filter(target) {
        this.changeValue(target);
        await this.sendResponse(this.presets.filtering);
    }

    async update() {
        const updElem = document.createElement('input');
        updElem.type = 'hidden';
        updElem.name = 'upd';
        updElem.value = '1';
        this.form.append(updElem);
        await this.sendResponse(this.presets.filtering);
    }

    async reset() {
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
                default:
                    filter.value = '';
                    break;
            }
        });
        this.resetSearchParams();
        await this.update(this.presets.filtering);
        this.resetBtn && this.resetBtn.classList.add(this.config.hideClass);

        document.dispatchEvent(new CustomEvent(this.events.reset, {
            bubbles: true,
            cancelable: false,
            detail: {filters}
        }))
    }

    async submit(e) {
        const form = e.target.closest(this.config.formSelector);
        const elements = Array.from(form.elements);
        elements.forEach(elem => this.changeValue(elem));
        await this.sendResponse(this.presets.filtering);
    }

    changeValue(elem) {
        if (elem.type === 'hidden') return true;
        const key = elem.dataset[this.config.filterKey] || elem.name;
        const type = this.getElemType(elem);
        if (!key) return true;
        this.setSearchParams(type, key, elem.value);
    }

    clearFilter(target) {
        const keyValue = target.dataset[this.config.itemKey].split('-');
        const filterSelector = this.config.filterSelector.replace('${key}', keyValue[0]);
        let filter = this.form.querySelector(filterSelector);
        const type = this.getElemType(filter);
        switch (type) {
            case 'checkbox':
                filter = this.form.querySelector(`${filterSelector}[value="${keyValue[1]}"]`);
                filter.checked = false;
                break;
            case 'multiple':
                filter.options[filter.selectedIndex].selected = false;
                break;
            case 'numrange':
                FlatFilters?.RangeSlider?.reset(keyValue[0]);
                filter = false;
                break;
            default:
                filter.value = '';
                break;
        }

        const eventOptions = {target, filter, type};
        if (!document.dispatchEvent(new CustomEvent(this.events.remove, {
            bubbles: true,
            cancelable: true,
            detail: {eventOptions}
        }))) {
            return false;
        }

        target.remove();
        filter && filter.dispatchEvent(new Event('change', {bubbles: true}))
        !window.location.search && this.resetBtn && this.resetBtn.classList.add(this.config.hideClass);
    }

    renderValue(key, value, caption) {
        const tpl = document.querySelector(this.config.tplSelector)?.cloneNode(true);
        const item = document.querySelector(this.config.itemSelector.replace('${key}', key).replace('${value}', value));
        if (!tpl || item) return;

        const eventOptions = {key, value, caption};
        if (!document.dispatchEvent(new CustomEvent(this.events.render, {
            bubbles: true,
            cancelable: true,
            detail: {eventOptions}
        }))) {
            return false;
        }

        tpl.innerHTML = tpl.innerHTML.replaceAll('$key', eventOptions.key).replaceAll('$value', eventOptions.value).replaceAll('$caption', eventOptions.caption);
        this.selected && this.selected.appendChild(tpl.content);
    }

    resetSelectedValues() {
        const values = this.selected.querySelectorAll(this.config.itemSelector.replace('="${key}-${value}"', ''));
        values.length && values.forEach(el => el.remove())
        this.captions = [];
    }

    checkPreset(detail) {
        const presets = Object.values(this.presets);
        return presets.includes(detail.headers['X-SIPRESET'])
    }


    async sendResponse(preset) {
        this.setHistory();
        SendIt.setComponentCookie('sitrusted', '1');
        await SendIt.Sending.prepareSendParams(this.form, preset, 'change');
    }

    setHistory() {
        const url = window.location.href;

        if (this.params && this.params.toString()) {
            window.history.replaceState({}, '', url.split('?')[0] + '?' + this.params.toString());
        } else {
            window.history.replaceState({}, '', url.split('?')[0]);
        }
    }

    beforeSendHandler(e) {
        e.detail.fetchOptions.headers['X-SIFORM'] = e.detail.target.closest(this.config.formSelector).dataset[this.config.formKey];
    }

    responseHandler(result) {
        //console.log(result);
        if (!result.data) return;
        const resultsBlock = document.querySelector(this.config.resultSelector);
        const filters = document.querySelectorAll(this.config.filtersSelector);
        const updElem = this.form.querySelector('input[name="upd"][type="hidden"]');
        updElem && updElem.remove();

        if (result.data.resources && resultsBlock) {
            resultsBlock.innerHTML = result.data.resources;
            document.dispatchEvent(new CustomEvent(this.events.loaded, {
                bubbles: true,
                cancelable: false,
                detail: {
                    resultsBlock: resultsBlock,
                    data: result.data,
                    FlatFilters: this
                }
            }))
        }

        if (!result.data.getDisabled) {
            this.setDisabled(filters, result.data);

            window.location.search && this.resetBtn && this.resetBtn.classList.remove(this.config.hideClass);

            if (this.selected) {
                this.resetSelectedValues();
                this.showSelectedValues();
            }
        } else {
            this.sendResponse(this.presets.disabling);
        }

        const timeBlock = document.querySelector('#time');
        if (result.data.totalTime && timeBlock) {
            timeBlock.textContent = result.data.totalTime;
        }

        const totalCountBlock = document.querySelector('#total');
        if (('totalResources' in result.data) && totalCountBlock) {
            totalCountBlock.textContent = result.data.totalResources;
        }
    }

    setDisabled(filters, data) {
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

                document.dispatchEvent(new CustomEvent(this.events.disabling, {
                    bubbles: true,
                    cancelable: false,
                    detail: {
                        type: type,
                        element: el,
                        key: key,
                        data: data,
                        filters: filters,
                        FlatFilters: this
                    }
                }))
            }
        });
    }

    showSelectedValues() {
        const urlParams = new URLSearchParams(window.location.search);
        for (const param of urlParams) {
            const selector = this.config.filterSelector.replace('${key}', param[0]);
            const filter = this.form.querySelector(selector);
            if (!filter) continue;
            const type = this.getElemType(filter);
            let values = []
            switch (type) {
                case 'checkbox':
                    values = this.getCheckboxValue(param[0]);
                    break;
                case 'multiple':
                    values = this.getMultipleValues(param[0]);
                    break;
                case 'numrange':
                    values = this.getNumrangeValues(param[0]);
                    break;
                default:
                    this.captions[param[0]] = [{value: filter.value, caption: filter.dataset[this.config.captionKey] || filter.value}]
                    break;
            }
        }
        if (this.captions) {
            for (let key in this.captions) {
                this.captions[key].forEach(data => {
                    this.renderValue(key, data.value, data.caption);
                })
            }
        }
    }

    resetSearchParams() {
        const url = window.location.href;
        window.history.replaceState({}, '', url.split('?')[0]);
    }

    setSearchParams(type, key, value = '') {
        if(['limit','page'].includes(key)) return;

        switch (type) {
            case 'checkbox':
            case 'multiple':
                this.addMultipleParam(key, type);
                break;
            case 'numrange':
                this.addNumrangeParam(key);
                break;
            default:
                this.addTextParam(key, value);
                break;
        }
    }

    addTextParam(key, value) {
        if (value) {
            this.params.set(key, value);
        } else {
            this.params.delete(key);
        }
    }

    addNumrangeParam(key) {
        this.params.delete(key);
        const values = this.getNumrangeValues(key);
        if (values.start !== values.min || values.end !== values.max) {
            this.params.set(key, `${values.start},${values.end}`);
        }
    }

    getNumrangeValues(key) {
        const {min, max, startField, endField} = FlatFilters.RangeSlider.getItems(key);
        this.captions[key] = [
            {value: startField.value + ',' + endField.value, caption: startField.value + ' - ' + endField.value},
        ]
        return {
            start: Number(startField.value),
            end: Number(endField.value),
            min: min,
            max: max
        };
    }

    addMultipleParam(key, type) {
        this.params.delete(key);
        const values = type === 'multiple' ? this.getMultipleValues(key) : this.getCheckboxValue(key);
        if (values.length) {
            this.params.set(key, values.join(','));
        }
    }

    getMultipleValues(key) {
        const values = [];
        this.captions[key] = [];
        const elem = document.querySelector(this.config.filterSelector.replace('${key}', key));
        Array.from(elem.options).forEach(el => {
            if (el.selected && el.value) {
                values.push(el.value);
                this.captions[key].push({value: [el.value], caption: el.dataset[this.config.captionKey] || el.value});
            }
        });
        return values;
    }

    getCheckboxValue(key) {
        const checkboxes = document.querySelectorAll(this.config.filterSelector.replace('${key}', key) + ':checked');
        const values = [];
        this.captions[key] = [];
        if (checkboxes.length) {
            checkboxes.forEach(el => {
                values.push(el.value);
                this.captions[key].push({value: [el.value], caption: el.dataset[this.config.captionKey] || el.value});
            });
        }
        return values;
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