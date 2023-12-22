export default class FileUploader {
    constructor(config) {
        if(window.SendIt && window.SendIt.FileUploader) return window.SendIt.FileUploader;
        const defaults = {
            formSelector: '[data-si-form]',
            rootSelector: '[data-fu-wrap]',
            fieldSelector: '[data-fu-field]',
            rootKey: 'fuWrap',
            presetKey: 'siPreset',
            sendEvent: 'si:send:after',
            pathKey: 'fuPath',
            pathAttr: 'data-fu-path',
            actionUrl: 'assets/components/sendit/web/action.php',
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
        this.position = 0;
        this.multiplier = 1024 * 1024;
        this.events = {
            before: 'fu:before:add',
            uploading: 'fu:uploading',
            remove: 'fu:remove',
        }
        this.config = Object.assign(defaults, config);

        document.addEventListener('si:init', (e) => {
            this.initialize();
        });
    }

    initialize() {
        document.addEventListener('change', (e) => {
            const form = e.target.closest(this.config.formSelector);
            const root = e.target.closest(this.config.rootSelector);
            if (form && root) {
                this.preset = form.dataset[this.config.presetKey]
                this.getParams(root);
            }
        });

        document.addEventListener(this.config.sendEvent, (e) => {
            if(e.detail.target === document) return true;
            const result = e.detail.result;
            const form = e.detail.target.closest(this.config.formSelector) || e.detail.target.closest('form');
            const root = form.querySelector(this.config.rootSelector);
            const field = root?.querySelector(this.config.fieldSelector);
            switch (e.detail.action) {
                case 'preset':
                    if (result.success) {
                        this['portion'] = result.data['portion'] * this.multiplier;
                        this.config.layout.input['name'] = result.data['allowFiles'];
                        form.querySelectorAll('[type="submit"]').forEach(btn => btn.disabled = true);
                        this.upload(root, field.files[0], 0);
                        field.disabled = true;
                    }
                    break;

                case 'upload':
                    if (!document.dispatchEvent(new CustomEvent(this.events.uploading, {
                        bubbles: true,
                        cancelable: true,
                        detail: {
                            result: result,
                            form: form,
                            root: root,
                            field: field,
                            FileUploader: this
                        }
                    }))) {
                        return;
                    }
                    if (result.success) {
                        this.position = result.data.position;
                        const file = field.files[result.data.currentIndex];

                        if (!result.data.path) {
                            if (result.data.loadingMsg) {
                                SendIt?.Notify?.show('info', result.data.loadingMsg, {upd: 1});
                                SendIt?.Notify?.progressControl('pause');
                            }

                            this.upload(root, file, result.data.currentIndex);
                        } else {
                            SendIt?.Notify?.close();
                            this.addFileToList(result.data, root, form);
                            this.uploadFinished(field, result, root, form);
                        }
                    } else {
                        this.uploadFinished(field, result, root, form);
                    }
                    addEventListener('beforeunload', (e) => {
                        if(root){
                            this.removeDir(root);
                        }
                    });
                    break;
                case 'removeFile':
                    this.removeFromList(form, result.data.path);
                    break;
                case 'send':
                    if(root){
                        this.removeList(root, form);
                    }
                    break;
            }
        });

    }

    removeList(root, form){
        const list = root.querySelector(this.config.layout.list.selector);
        const listInput = root.querySelector(this.config.layout.input.selector);
        if(listInput){
            const values = listInput.value ? listInput.value.split(',') : [];
            values.forEach(path => this.removeFromList(form, path));
        }
        if(list){
            list.remove();
        }
    }

    uploadFinished(field, result, root, form) {
        this.position = 0;
        if (field.files[result.data.nextIndex]) {
            setTimeout(() => {
                SendIt?.Notify?.close();
                this.upload(root, field.files[result.data.nextIndex], result.data.nextIndex);
            },SendIt?.Notify?.config?.handlerOptions?.timeout);

        } else {
            form.querySelectorAll('[type="submit"]').forEach(btn => btn.disabled = false);
            field.value = '';
            field.disabled = false;
        }
    }

    upload(root, file, index) {
        const from = this.position;
        const loaded = root.querySelectorAll(this.config.layout.item.selector);
        if (!file || !file.size) return;
        const blob = file.slice(from, from + this.portion);

        const headers = {
            'X-CURRENT-INDEX': index,
            'X-LOADED': loaded.length,
            'X-UPLOAD-ID': file.name,
            'X-POSITION-FROM': from,
            'X-PORTION-SIZE': this.portion,
            'X-FILE-SIZE': file.size,
            'Content-Type': 'application/x-binary; charset=x-user-defined',
            'X-SIACTION': 'upload',
            'X-SIPRESET': this.preset,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }

        SendIt?.Sending?.send(root, this.config.actionUrl, headers, blob);
    }

    removeFromList(form, path) {
        const layout = this.config.layout;
        const input = form.querySelector(layout.input.selector);
        let list = input.value ? input.value.split(',') : [];
        const btn = form.querySelector(layout.btn.selector.replace('${filepath}', path));
        const item = btn.closest(layout.item.selector);
        list = list.filter(el => el !== path);
        item.remove();
        input.value = list.join(',');
    }

    addFileToList(data, root, form) {
        if (!document.dispatchEvent(new CustomEvent(this.events.before, {
            bubbles: true,
            cancelable: true,
            detail: {
                form: form,
                data: data,
                root: root,
                FileUploader: this
            }
        }))) {
            return;
        }

        const layout = this.config.layout;
        let fileList = root.querySelector(layout.list.selector);
        let input = root.querySelector(layout.input.selector);
        if (!fileList) {
            fileList = this.createElement(layout.list, root);
        }
        if (!input) {
            input = this.createElement(layout.input, root);
        }
        const list = input.value ? input.value.split(',') : [];
        if (list.includes(data.path)) return;
        const item = this.createElement(layout.item, fileList);
        const btn = this.createElement(layout.btn, item);
        btn.innerHTML = layout.btn.text.replace('${filename}', data.filename);
        btn.setAttribute(this.config.pathAttr, data.path)
        btn.addEventListener('click', (e) => {
            this.removeFile(root, btn);
        });
        list.push(data.path);
        input.value = list.join(',');
    }

    removeFile(root, btn) {
        const params = new FormData();
        params.append('path', btn.dataset[this.config.pathKey]);
        const headers = {
            'X-SIACTION': 'removeFile',
            'X-SIPRESET': this.preset,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }

        if (!document.dispatchEvent(new CustomEvent(this.events.remove, {
            bubbles: true,
            cancelable: true,
            detail: {
                btn: btn,
                params: params,
                root: root,
                headers: headers,
                FileUploader: this
            }
        }))) {
            return;
        }

        SendIt?.Sending?.send(root, this.config.actionUrl, headers, params);
    }

    createElement(elementData, parentEl) {
        const elem = document.createElement(elementData.tagName);
        if (elementData.classNames && elementData.classNames.length) {
            elementData.classNames.forEach(className => elem.classList.add(className))
        }
        elementData.type ? elem.type = elementData.type : '';
        elementData.name ? elem.name = elementData.name : '';
        parentEl ? parentEl.appendChild(elem) : '';
        return elem;
    }

    removeDir(root) {
        const headers = {
            'X-SIACTION': 'removeDir',
            'X-SIPRESET': this.preset,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }

        SendIt?.Sending?.send(root, this.config.actionUrl, headers, '');
    }

    getParams(root) {
        const headers = {
            'X-SIACTION': 'preset',
            'X-SIPRESET': this.preset,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }

        SendIt?.Sending?.send(root, this.config.actionUrl, headers, '');
    }
}