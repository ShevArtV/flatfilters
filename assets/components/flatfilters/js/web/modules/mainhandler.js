export default class MainHandler {
    constructor(config) {
        if (window.FlatFilters && window.FlatFilters.MainHandler) return window.FlatFilters.MainHandler;
        const defaults = {
            sendEvent: 'si:send:finish',
            beforeSendEvent: 'si:send:before',
            saveEvent: 'sf:save',
            formSelector: '[data-si-preset="flatfilters"]',
            resultSelector: '[data-ff-results]',
            resetBtnSelector: '[data-ff-reset]',
            filtersSelector: '[data-ff-filter]',
            filterSelector: '[data-ff-filter="${key}"]',
            filterKey: 'ffFilter',
        }

        this.config = Object.assign(defaults, config);
        this.form = document.querySelector(this.config.formSelector);
        this.resetBtn = document.querySelector(this.config.resetBtnSelector);
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
                this.changeHandler(e)
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
            SendIt.setComponentCookie('sitrusted', '1');
            SendIt.Sending.prepareSendParams(this.form, 'get_disabled');
        }else{
            this.resetBtn.style.display = 'none';
        }
    }

    resetHandler(e){
        e.preventDefault();
        const filters = document.querySelectorAll(this.config.filtersSelector);
        filters.forEach(filter => {
            const type = this.getElemType(filter);
            switch (type) {
                case 'select':
                case 'multiple':
                    const options = Array.from(filter.options);
                    options.forEach(option => {option.selected = false});
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
        FlatFilters.PaginationHandler.goto(1);
        this.resetBtn.style.display = 'none';
    }

    beforeSendHandler(e){
        const getparams = new URLSearchParams(window.location.search);
        const limit = e.detail.params.get('limit');
        const page = e.detail.params.get('page');
        e.detail.fetchOptions.body = new FormData();
        getparams.forEach((value, key) => e.detail.fetchOptions.body.append(key, value))
        e.detail.fetchOptions.body.append('limit', limit);
        e.detail.fetchOptions.body.append('page', page);
    }

    changeHandler(e){
        const elem = e.target.closest(this.config.filtersSelector);
        const key = elem.dataset[this.config.filterKey];
        const type = this.getElemType(elem);
        this.setSearchParams(type, key, elem.value);
        FlatFilters.PaginationHandler.goto(1);
    }

    responseHandler(result) {
        //console.log(result);
        const resultsBlock = document.querySelector(this.config.resultSelector);
        const filters = document.querySelectorAll(this.config.filtersSelector);

        if (result.data.resources && resultsBlock) {
            resultsBlock.innerHTML = result.data.resources;
        }

        if (result.data.totalPages) {
            const totalBlock = document.querySelector(FlatFilters.PaginationHandler.config.totalPagesSelector);
            const currentPageInput = FlatFilters.PaginationHandler.pageInput;
            const lastPage = FlatFilters.PaginationHandler.gotoLastBtn;
            totalBlock.textContent = lastPage.dataset[FlatFilters.PaginationHandler.config.lastPageKey] = currentPageInput.max = result.data.totalPages;
            FlatFilters.PaginationHandler.wrapper.style.display = 'block';
        } else {
            !result.data.getDisabled && (FlatFilters.PaginationHandler.wrapper.style.display = 'none');
        }
        result.data.currentPage && FlatFilters.PaginationHandler.buttonsHandler(result.data.currentPage);

        if(result.data.getDisabled){
            filters.forEach(el => {
                const key = el.dataset[this.config.filterKey];
                if (result.data.filterValues && result.data.filterValues[key] && result.data.filterValues[key]['values']) {
                    const type = this.getElemType(el);
                    switch (type) {
                        case 'select':
                            for (let i = 1; i < el.options.length; i++) {
                                el.options[i].disabled = !result.data.filterValues[key]['values'].includes(el.options[i].value);
                            }
                            break;
                        case 'radio':
                            el.disabled = !result.data.filterValues[key]['values'].includes(el.value);
                            break;
                        case 'checkbox':
                            const selector = this.config.filterSelector.replace('${key}', key);
                            if(document.querySelectorAll(selector).length === 1){
                                el.disabled = !result.data.filterValues[key]['values'].includes(el.value);
                            }
                            break;
                    }
                }
            });
        }else{
            SendIt.setComponentCookie('sitrusted', '1');
            SendIt.Sending.prepareSendParams(this.form, 'get_disabled');
        }

        if (result.data.totalTime) {
            document.querySelector('#time').textContent = result.data.totalTime;
        }
        if (result.data.totalResources) {
            document.querySelector('#total').textContent = result.data.totalResources;
        }

        window.location.search && (this.resetBtn.style.display = 'block');
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
                if(elem.closest(pickerSelector)){
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