import * as functions from './functions.min.js';

export default class manage {
    constructor(config) {
        const defaults = {
            storageKey: 'flatfilters',
            methodSelector: '[data-js-method]',
            wrapperSelector: '[data-js-wrapper="${name}"]',
            targetSelector: '[data-js-target]',
            eventSelector: '[data-js-event]',
            valueSelector: '[data-js-value]',
            selectedSelector: '[data-js-selected="${name}"]',
            suggestionWrapSelector: '[data-js-suggestions="${name}"]',
            selectedKey: 'jsSelected',
            methodKey: 'jsMethod',
            wrapperKey: 'jsWrapper',
            actionKey: 'jsAction',
            fieldsKey: 'jsFields',
            sortKey: 'jsSort',
            classKey: 'jsClass',
            dirKey: 'jsDir',
            valueKey: 'jsValue',
            captionKey: 'jsCaption',
            targetKey: 'jsTarget',
            tplKey: 'jsTpl',
            allowKey: 'jsAllow',
            eventKey: 'jsEvent',
            valueAttribute: 'data-js-value',
        };
        this.config = Object.assign(defaults, config);

        this.mainContainer = document.querySelector('#modx-content');
        this.mainContainer.innerHTML = this.config.template;

        this.config.headers = {
            "X-Requested-With": "XMLHttpRequest",
            "modAuth": MODx.siteId
        };
        this.initialize();
        console.log(this.config);
    }

    initialize() {
        sessionStorage.removeItem(this.config.storageKey);
        if(this.config.storage){
            for(let k in this.config.storage){
                this.config.storage[k] && this.setStorageValue(k, this.config.storage[k]);
            }
        }

        const eventElems = document.querySelectorAll(this.config.eventSelector);
        const events = [];
        eventElems.forEach(el => {
            const event = el.dataset[this.config.eventKey];
            !events.includes(event) && events.push(event);
        })

        events.length && events.forEach(event => document.addEventListener(event, this.callMethod.bind(this)));

        document.addEventListener('click', e => {
            const selector = this.config.selectedSelector.replace('="${name}"', '');
            if (!e.target.closest(selector)) {
                const actionElements = document.querySelectorAll(this.config.methodSelector);
                actionElements && actionElements.forEach(el => this.getSuggestionsWrap(el))
            }
            if(e.target.closest(this.config.valueSelector)){
                const wrap = e.target.closest(selector);
                wrap && this.removeSelected(e, wrap.dataset[this.config.selectedKey], e.target.dataset[this.config.valueKey])
            }
        })
    }

    callMethod(e) {
        const target = e.target.closest(this.config.methodSelector);
        if (target && e.type === target.dataset[this.config.eventKey]) {
            e.preventDefault();
            const method = target.dataset[this.config.methodKey];
            (typeof this[method] !== 'undefined') && this[method](target);
        }
    }

    sendForm(target){
        const params = new FormData(target);
        const storageValue = this.getValueFromStorage();
        for(let k in storageValue){
            let value = storageValue[k];
            if(typeof storageValue[k] === 'object'){
                value = JSON.stringify(storageValue[k]);
            }
            params.has(k) ? params.set(k, value) : params.append(k, value)
        }
        functions.sendAjax(params, (response, args) => this.responseHandler(response, args), this.config.connector_url, this.config.headers, {target});
    }

    responseHandler(response, args){
        console.log(response);
        if(response.success){
            if (response.object.action === 'mgr/configuration/create' &&  response.object.id) {
                args.target.id.value = response.object.id;
                args.target.action.value = 'mgr/discount/update';
                window.location.replace(window.location.href + '&id=' + response.object.id);
            }
            functions.showNotify(response.message);
        }
    }

    clearAll(target) {
        const key = target.dataset[this.config.targetKey];
        const selectedSelector = this.config.selectedSelector.replace('${name}', key);
        const wrapperSelector = this.config.wrapperSelector.replace('${name}', key);
        const selectedWrap = document.querySelector(selectedSelector)||document.querySelector(wrapperSelector);
        this.removeStorageValue(key);
        selectedWrap && (selectedWrap.innerHTML = '');
        key === 'filters' && (document.getElementById('submitBtn').disabled = true);
    }

    removeItem(target){
        const item = document.getElementById(target.dataset[this.config.targetKey]);
        item && item.remove();
        let values = this.getStorageValue(item.dataset[this.config.valueKey]) ? this.getStorageValue(item.dataset[this.config.valueKey]) : {};
        delete values[target.dataset[this.config.valueKey]];
        this.setStorageValue(item.dataset[this.config.valueKey], values);

        document.getElementById('submitBtn').disabled = !Object.keys(values).length;
    }

    setAllowed(target){
        const select = document.getElementById(target.dataset[this.config.targetKey]);
        const allowed = target.options[target.selectedIndex].dataset[this.config.allowKey].split(',');
        delete select.options.length;
        Array.from(select.options).map((option, index, array) => {
            index === 0 && (select.selectedIndex = -1);
            option.disabled = !allowed.includes(option.value);
            option.selected = !option.disabled && (select.selectedIndex === -1);
            option.selected && (select.selectedIndex = index);
        })
    }

    removeSelected(e, key, value) {
        let values = this.getStorageValue(key) ? this.getStorageValue(key).split(',') : [];
        values = values.filter(el => Number(el) !== Number(value));
        this.setStorageValue(key, values.join(','));
        e.target.remove();
    }

    getLocalSuggestions(el) {
        if (el.value.length > 2) {
            const filtered = this.config.filters_keys.filter(item =>
                item.key.indexOf(el.value) !== -1 || item.caption.indexOf(el.value) !== -1
            )
            console.log()
            this.manageSuggestions(filtered, {el})
        }else{
            this.manageSuggestions([], {el})
        }
    }

    getSuggestions(el) {
        if (el.value.length > 2) {
            const params = new FormData();
            params.append('action', el.dataset[this.config.actionKey]);
            params.append('fields', el.dataset[this.config.fieldsKey]);
            params.append('sort', el.dataset[this.config.sortKey]);
            params.append('class', el.dataset[this.config.classKey]);
            params.append('value', el.value);
            functions.sendAjax(params, (response, args) => this.manageSuggestions(response.results, args), this.config.connector_url, this.config.headers, {el});
        }else{
            this.manageSuggestions([], {el})
        }
    }

    manageSuggestions(results, args) {
        const suggestionWrap = this.getSuggestionsWrap(args.el);
        if (suggestionWrap && results.length) {
            results.forEach(obj => {
                const value = obj.key || obj.id;
                const text = obj[args.el.dataset[this.config.captionKey]];
                const li = this.createElement('li', value, text, {
                    click: e => this.suggestionSelect(e, args.el, suggestionWrap)
                });
                suggestionWrap.appendChild(li);
            });
            suggestionWrap.classList.remove('d-none');
        }

        if(suggestionWrap && !results.length){
            suggestionWrap.classList.add('d-none');
        }
    }

    getSuggestionsWrap(el) {
        const suggestionWrapSelector = this.config.suggestionWrapSelector.replace('${name}', el.id);
        const suggestionWrap = document.querySelector(suggestionWrapSelector);

        suggestionWrap && (suggestionWrap.innerHTML = '');
        suggestionWrap && suggestionWrap.classList.add('d-none');

        return suggestionWrap;
    }

    createElement(tag, value, text, eventListeners) {
        const element = document.createElement(tag);
        element.setAttribute(this.config.valueAttribute, value);
        element.innerText = text;
        for (let eventName in eventListeners) {
            element.addEventListener(eventName, eventListeners[eventName]);
        }
        return element;
    }

    setFilters(target) {
        const form = target.closest('form');
        const params = new FormData();
        const keyInput = form[target.dataset[this.config.targetKey]];
        const wrapperSelector = this.config.wrapperSelector.replace('${name}', keyInput.id);
        const wrapper = document.querySelector(wrapperSelector);
        const filterData = {};
        if (form) {
            Array.from(form).forEach(item => {
                if (!item.value) return;
                item.name && (filterData[item.name] = item.dataset[this.config.valueKey] || item.value);
            })
        }

        if (!Object.keys(filterData).length || !keyInput.dataset[this.config.valueKey]) return;

        let values = this.getStorageValue(keyInput.id) ? this.getStorageValue(keyInput.id) : {};
        if(!values.hasOwnProperty(keyInput.dataset[this.config.valueKey])){
            values[keyInput.dataset[this.config.valueKey]] = filterData;
            this.setStorageValue(keyInput.id, values);

            for(let k in filterData){
                params.append(k, filterData[k]);
            }
            params.append('action', target.dataset[this.config.actionKey]);
            params.append('tpl', target.dataset[this.config.tplKey]);

            functions.sendAjax(params, (response, args) => this.insertHTML(response,args), this.config.connector_url, this.config.headers, {
                wrapper: wrapper
            });
            document.getElementById('submitBtn').disabled = false;
        }
    }


    insertHTML(response, args){
        const parser = new DOMParser();
        const html = parser.parseFromString(response.object.html, 'text/html');
        args.wrapper && response.object && response.object.html && (args.wrapper.appendChild(html.body.firstChild));
    }

    suggestionSelect(e, input, list) {
        const selectedSelector = this.config.selectedSelector.replace('${name}', input.id);
        const selectedWrap = document.querySelector(selectedSelector);


        if (input.id.indexOf('filters') === -1) {
            let values = this.getStorageValue(input.id) ? this.getStorageValue(input.id).split(',') : [];
            if (!values.includes(e.target.dataset[this.config.valueKey])) {
                values.push(e.target.dataset[this.config.valueKey]);
                this.setStorageValue(input.id, values.join(','));
            }
        }

        if (selectedWrap) {
            input.value = '';
            const span = this.createElement('span', e.target.dataset[this.config.valueKey], e.target.innerText, {})
            selectedWrap.appendChild(span);
        } else {
            input.dataset[this.config.valueKey] = e.target.dataset[this.config.valueKey];
            input.value = e.target.innerText;
        }
        list.innerHTML = '';
        list.classList.add('d-none');
    }

    setStorageValue(key, value) {
        const storageValue = this.getValueFromStorage();
        storageValue[key] = value;
        sessionStorage.setItem(this.config.storageKey, JSON.stringify(storageValue));
    }

    getStorageValue(key) {
        const storageValue = this.getValueFromStorage();
        return storageValue[key];
    }

    removeStorageValue(key) {
        const storageValue = this.getValueFromStorage();
        delete storageValue[key];
        sessionStorage.setItem(this.config.storageKey, JSON.stringify(storageValue));
    }

    getValueFromStorage() {
        return sessionStorage.getItem(this.config.storageKey) ? JSON.parse(sessionStorage.getItem(this.config.storageKey)) : {};
    }
}