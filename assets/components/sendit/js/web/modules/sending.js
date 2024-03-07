export default class Sending {
    constructor(config) {
        if (window.SendIt && window.SendIt.Sending) return window.SendIt.Sending;
        const defaults = {
            rootSelector: '[data-si-form]',
            rootKey: 'siForm',
            presetKey: 'siPreset',
            eventKey: 'siEvent',
            goalKey: 'siGoal',
            actionUrl: 'assets/components/sendit/action.php',
            antiSpamEvent: 'click',
            errorBlockSelector: '[data-si-error="${fieldName}"]',
            eventSelector: '[data-si-event="${eventName}"]',
            errorClass: 'si-error'
        }
        this.events = {
            before: 'si:send:before',
            reset: 'si:send:reset',
            after: 'si:send:after',
            success: 'si:send:success',
            error: 'si:send:error',
            finish: 'si:send:finish',
        };
        this.config = Object.assign(defaults, config);

        document.addEventListener('si:init', (e) => {
            this.initialize();
        });
    }

    initialize() {
        document.addEventListener(this.config.antiSpamEvent, (e) => {
            if (e.isTrusted) SendIt?.setComponentCookie('sitrusted', '1');
        });
        document.addEventListener('submit', (e) => {
            if (e.isTrusted) SendIt?.setComponentCookie('sitrusted', '1');
            const root = e.target.closest(this.config.rootSelector);
            const field = e.target.closest(this.config.eventSelector.replace('="${eventName}"', ''));

            if (root) {
                e.preventDefault();
                if(field && field.dataset[this.config.eventKey] !== e.type){
                    return false;
                }
                this.prepareSendParams(root, root.dataset[this.config.presetKey]);
            }
        });
        document.addEventListener('change', this.sendField.bind(this));
        document.addEventListener('input', this.sendField.bind(this));
        document.addEventListener('click', (e) => {
            const root = e.target.closest(this.config.rootSelector);
            if (e.target.type === 'reset' && root) {
                this.resetForm(root);
                this.resetAllErrors(root);
            }

            if (e.target.closest(this.config.eventSelector.replace('${eventName}', e.type)) && root) {
                this.sendField(e);
            }
        });
    }

    sendField(e) {
        if (e.isTrusted) SendIt?.setComponentCookie('sitrusted', '1');
        const field = e.target.closest(this.config.eventSelector.replace('${eventName}', e.type));
        const root = e.target.closest(this.config.rootSelector);
        if (!field && !root) return;
        const preset = (field && field.dataset[this.config.presetKey]) ? field.dataset[this.config.presetKey] : root.dataset[this.config.presetKey];
        if (root) {
            this.resetError(e.target.name, root);
        }
        if (field && field.tagName !== 'FORM') {
            if(field.dataset[this.config.eventKey] === e.type){
                field.tagName !== 'BUTTON' ? this.prepareSendParams(field, preset, e.type) : this.prepareSendParams(root, preset, e.type);
            }
        } else {
            if (root && root.dataset[this.config.eventKey] === e.type) {
                this.prepareSendParams(root, preset, e.type);
            }
        }
    }

    prepareSendParams(root, preset = '', event = 'submit', action = 'send') {
        let formName = '';
        let params = new FormData();
        if (root !== document) {
            formName = root.dataset[this.config.rootKey];
            if(root.tagName === 'FORM'){
                params = new FormData(root);
            }
            else if(root.name){
                params.append(root.name, root.value);
            }else{
                const fields = root.querySelectorAll('input, select, textarea, button');
                if(fields.length){
                    fields.forEach(field => field.name && params.append(field.name, field.value))
                }
            }
        }

        const headers = {
            'X-SIFORM': formName,
            'X-SIACTION': action,
            'X-SIPRESET': preset,
            'X-SIEVENT': event,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken') || ''
        }
        this.send(root, this.config.actionUrl, headers, params);
    }

    async send(target, url, headers, params, method = 'POST') {
        url = url || this.config.actionUrl
        const fetchOptions = {
            method: method,
            body: params,
            headers: headers
        }
        if (!document.dispatchEvent(new CustomEvent(this.events.before, {
            bubbles: true,
            cancelable: true,
            detail: {
                action: headers['X-SIACTION'],
                target: target,
                fetchOptions: fetchOptions,
                headers: headers,
                Sending: this
            }
        }))) {
            return;
        }

        if (SendIt?.getComponentCookie('sitrusted') === '0') return;

        this.resetAllErrors(target);

        const response = await fetch(url, fetchOptions);

        const result = await response.json();

        if (!document.dispatchEvent(new CustomEvent(this.events.after, {
            bubbles: true,
            cancelable: true,
            detail: {
                action: headers['X-SIACTION'],
                headers: headers,
                target: target,
                result: result,
                Sending: this
            }
        }))) {
            return;
        }

        if (result.success) {
            if (!document.dispatchEvent(new CustomEvent(this.events.success, {
                bubbles: true,
                cancelable: true,
                detail: {
                    action: headers['X-SIACTION'],
                    headers: headers,
                    target: target,
                    result: result,
                    Sending: this
                }
            }))) {
                return;
            }

            this.success(result, target)
        } else {
            if (!document.dispatchEvent(new CustomEvent(this.events.error, {
                bubbles: true,
                cancelable: true,
                detail: {
                    action: headers['X-SIACTION'],
                    headers: headers,
                    target: target,
                    result: result,
                    Sending: this
                }
            }))) {
                return;
            }

            this.error(result, target)
        }

        document.dispatchEvent(new CustomEvent(this.events.finish, {
            bubbles: true,
            cancelable: false,
            detail: {
                action: headers['X-SIACTION'],
                headers: headers,
                target: target,
                result: result,
                Sending: this
            }
        }))
    }

    success(result, root) {
        const redirectUrl = result.data.redirectUrl;
        const redirectTimeout = Number(result.data.redirectTimeout) || 0;
        const defaultGoals = result.data.goalName?.split(',') || [];
        const layoutGoals = (root.dataset && root.dataset[this.config.goalKey]) ? root.dataset[this.config.goalKey]?.split(',') : [];
        const goals = [...defaultGoals, ...layoutGoals];

        SendIt?.Notify?.success(result.message);

        if (result.data.goalName && result.data.counterId && result.data.sendGoal && typeof window.ym !== 'undefined') {
            goals.forEach(goal => ym(result.data.counterId, 'reachGoal', goal));
        }

        if (redirectUrl) {
            setTimeout(() => {
                window.location.href = redirectUrl;
            }, redirectTimeout);
        }


        if (result.data.clearFieldsOnSuccess) {
            this.resetForm(root);
        }
    }

    error(result, root) {
        if (!result.data || !result.data.errors) {
            SendIt?.Notify?.info(result.message);
        } else {
            for (let k in result.data.errors) {
                const errorBlock = root.querySelector(this.config.errorBlockSelector.replace('${fieldName}', k));
                const fields = root.querySelectorAll(`[name="${k}"]`);
                if (fields.length) {
                    fields.forEach(field => field.classList.add(this.config.errorClass));
                }
                if (errorBlock) {
                    errorBlock.textContent = result.data.errors[k];
                } else {
                    if (result.data.aliases && result.data.aliases[k]) {
                        SendIt?.Notify?.error(`${result.data.aliases[k]}: ${result.data.errors[k]}`);
                    } else {
                        SendIt?.Notify?.error(`${result.data.errors[k]}`);
                    }
                }
            }
        }
    }

    resetForm(target) {
        if (!document.dispatchEvent(new CustomEvent(this.events.reset, {
            bubbles: true,
            cancelable: true,
            detail: {
                target: target,
                Sending: this
            }
        }))) {
            return
        }

        if (target.tagName === 'FORM') {
            target.reset();
        } else if(target.value){
            target.value = '';
        }
    }

    resetAllErrors(target) {
        if (target === document) return;
        const root = target.closest(this.config.rootSelector);
        const errorBlocks = root?.querySelector(this.config.errorBlockSelector.replace('="${fieldName}"', ''));
        const fields = root?.querySelectorAll(`.${this.config.errorClass}`);
        if (fields && fields.length) {
            fields.forEach(field => field.classList.remove(this.config.errorClass));
        }
        if (errorBlocks && errorBlocks.length) {
            errorBlocks.forEach(errorBlock => errorBlock.textContent = '');
        }
    }

    resetError(fieldName, root) {
        const errorBlock = root?.querySelector(this.config.errorBlockSelector.replace('${fieldName}', fieldName));
        const fields = root?.querySelectorAll(`[name="${fieldName}"]`);
        if (fields.length) {
            fields.forEach(field => field.classList.remove(this.config.errorClass));
        }
        if (errorBlock) errorBlock.textContent = '';
    }
}