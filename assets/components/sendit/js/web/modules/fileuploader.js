export default class FileUploaderFactory {
    constructor(config) {
        if (window.SendIt && window.SendIt.FileUploaderFactory) return window.SendIt.FileUploaderFactory;
        this.rootSelector = config['rootSelector'] || '[data-fu-wrap]';
        this.sendEvent = config['sendEvent'] || 'si:send:after';
        this.pathAttr = config['pathAttr'] || 'data-fu-path';
        this.instances = new Map();

        document.addEventListener('si:init', (e) => {
            this.initialize(config);
        });
    }

    initialize(config) {
        const roots = document.querySelectorAll(this.rootSelector);
        if (roots.length) {
            roots.forEach(root => {
                this.instances.set(root, new FileUploader(root, config));
                const dropzone = root.querySelector(config.dropzoneSelector);
                if (dropzone) {
                    dropzone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                    });

                    dropzone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        const fileInput = dropzone.querySelector('[type="file"]');
                        fileInput.files = e.dataTransfer.files;
                        fileInput.dispatchEvent(new Event('change', {bubbles: true}))
                    });
                }
            })

            document.addEventListener('change', (e) => {
                const root = e.target.closest(this.rootSelector);
                if (this.instances.has(root)) {
                    const fileUploader = this.instances.get(root);
                    fileUploader.changeEventHandler();
                }
            });

            document.addEventListener(this.sendEvent, async (e) => {
                const {target, result} = e.detail;
                if (target === document) return true;
                if (this.instances.has(target)) {
                    const fileUploader = this.instances.get(target);
                    await fileUploader.sendEventHandler(e.detail);
                } else {
                    if (result.data.allowFiles && result.data.clearFieldsOnSuccess) {
                        const fileWrap = target.querySelector(config.rootSelector);
                        if (this.instances.has(fileWrap)) {
                            const fileUploader = this.instances.get(fileWrap);
                            fileUploader.clearFields();
                        }
                    }
                }
            });

            document.addEventListener('click', (e) => {
                const root = e.target.closest(this.rootSelector);
                if (this.instances.has(root) && e.target.closest(`[${this.pathAttr}]`)) {
                    const fileUploader = this.instances.get(root);
                    fileUploader.removeFile(e.target.closest(`[${this.pathAttr}]`))
                }
            })

            window.addEventListener('beforeunload', (e) => {
                for (let fileUploader of this.instances.values()) {
                    fileUploader.removeDir();
                }
            });
        }
    }
}

class FileUploader {
    constructor(root, config) {
        if (window.SendIt && window.SendIt.FileUploader) return window.SendIt.FileUploader;
        const defaults = {
            formSelector: '[data-si-form]',
            progressSelector: '[data-fu-progress]',
            rootSelector: '[data-fu-wrap]',
            tplSelector: '[data-fu-tpl]',
            dropzoneSelector: '[data-fu-dropzone]',
            fileListSelector: '[data-fu-list]',
            progressIdAttr: 'data-fu-id',
            progressTextAttr: 'data-fu-text',
            hideBlockSelector: '[data-fu-hide]',
            presetSelector: '[data-si-preset]',
            presetKey: 'siPreset',
            sendEvent: 'si:send:after',
            pathKey: 'fuPath',
            pathAttr: 'data-fu-path',
            actionUrl: 'assets/components/sendit/action.php',
            hiddenClass: 'v_hidden',
            progressClass: 'progress__line',
            showTime: true
        }
        this.config = Object.assign(defaults, config);

        this.root = root;
        this.field = root.querySelector('[type="file"]');
        this.listField = root.querySelector(this.config.fileListSelector);
        this.preset = this.root.closest(this.config.presetSelector).dataset[this.config.presetKey];
        this.position = 0;
        this.multiplier = 1024 * 1024;
        this.activeConnections = 0;

        this.field.value = '';

        this.events = {
            uploadingStart: 'fu:uploading:start',
            uploadingEnd: 'fu:uploading:end',
        }
    }

    changeEventHandler() {
        const fileList = this.listField.value ? this.listField.value.split(',') : [];
        const files = Array.from(this.field.files);
        const result = this.removeFromFileList(files, fileList);
        this.field.files = result.files;
        if (!result.filesData) return;
        this.validateFiles(result.filesData, this.listField.value);
    }

    async sendEventHandler(detail) {
        const {result, action} = detail;
        const files = Array.from(this.field.files);
        const form = this.root.closest('form');
        switch (action) {
            case 'validate_files':
                if (result.success) {
                    await this.prepareUpload(result.data, form)
                } else {
                    if (result.data.fileNames) {
                        const res = this.removeFromFileList(files, result.data.fileNames);
                        this.field.files = res.files;
                        if (!this.field.files) return false;
                        await this.prepareUpload(result.data, form)
                    }
                }
                break;
            case 'removeFile':
                this.removeFromList(result.data.filename);
                this.removePreview(result.data.path);
                break;
        }
    }

    clearFields() {
        const btns = this.root.querySelectorAll(`[${this.config.pathAttr}]`);
        if (btns.length) {
            btns.forEach(btn => this.removeFile(btn))
        }
    }

    validateFiles(filesData, fileList) {
        const headers = {
            'X-SIACTION': 'validate_files',
            'X-SIPRESET': this.preset,
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }
        const params = new FormData();
        params.append('filesData', JSON.stringify(filesData));
        params.append('fileList', fileList);

        SendIt?.setComponentCookie('sitrusted', '1');
        SendIt?.Sending?.send(this.root, this.config.actionUrl, headers, params);
    }

    async prepareUpload(data, form) {
        this.portion = data.portion * this.multiplier;
        this.threadsQuantity = data.threadsQuantity || 6;
        form && form.querySelectorAll('[type="submit"]').forEach(btn => btn.disabled = true);
        this.field.disabled = true;
        this.times = {};
        this.progressWrap = this.root.querySelector(this.config.progressSelector);

        if (!document.dispatchEvent(new CustomEvent(this.events.uploadingStart, {
            bubbles: true,
            cancelable: true,
            detail: {
                form: form,
                root: this.root,
                field: this.field,
                files: this.field.files,
                FileUploader: this
            }
        }))) {
            return;
        }

        for (let file of this.field.files) {
            const chunksQuantity = Math.ceil(file.size / this.portion);
            const chunksQueue = new Array(chunksQuantity).fill().map((_, index) => index).reverse();
            const filename = this.translitName(file.name);

            this.addProgressBar(filename);
            this.times[filename] = new Date();
            await this.sendNext(file, filename, chunksQueue);
            this.removeProgressBar(filename);
            this.renderPreview(filename);
            this.addToList(filename);
        }

        this.field.disabled = false;
        this.field.value = '';
        form.querySelectorAll('[type="submit"]').forEach(btn => btn.disabled = false);

        document.dispatchEvent(new CustomEvent(this.events.uploadingEnd, {
            bubbles: true,
            cancelable: true,
            detail: {
                form: form,
                root: this.root,
                field: this.field,
                files: this.field.files,
                FileUploader: this
            }
        }))
    }

    removeFromFileList(files, haystack) {
        const newFileList = new DataTransfer();
        const filesData = {};
        files.map(file => {
            const filename = this.translitName(file.name);
            if (!haystack.includes(filename)) {
                newFileList.items.add(file);
                filesData[filename] = file.size;
            }
        })
        return {filesData, files: newFileList.files};
    }

    removePreview(path) {
        const preview = this.root.querySelector(`[${this.config.pathAttr}="${path}"]`);
        preview && preview.remove();
    }

    removeFromList(filename) {
        const hide = this.root.querySelector(this.config.hideBlockSelector);
        let fileList = this.listField.value ? this.listField.value.split(',') : [];
        fileList = fileList.filter(name => name !== filename);
        !fileList.length && hide && hide.classList.remove(this.config.hiddenClass);
        this.listField.value = fileList.join(',');
    }

    removeFile(btn) {
        const params = new FormData();
        params.append('path', btn.dataset[this.config.pathKey]);
        const headers = {
            'X-SIACTION': 'removeFile',
            'X-SIPRESET': 'removeFile',
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }

        SendIt?.setComponentCookie('sitrusted', '1');
        SendIt?.Sending?.send(this.root, this.config.actionUrl, headers, params);
    }

    async sendNext(file, filename, chunksQueue) {
        if (this.activeConnections >= this.threadsQuantity) {
            return;
        }

        if (!chunksQueue.length) {
            if (!this.activeConnections && this.config.showTime) {
                console.log('Время загрузки: ' + (new Date - this.times[filename]) / 1000);
            }
            return;
        }

        const chunkId = chunksQueue.pop();
        const begin = chunkId * this.portion;
        const chunk = file.slice(begin, begin + this.portion);
        this.activeConnections += 1;
        const response = await this.uploadChunk(chunk, chunkId, file, filename)
        if (response.ok) {
            const result = await response.json();
            if (result.success) {
                this.setProgress(filename, result.data.percent, result.message)
                await this.sendNext(file, filename, chunksQueue);
                result.data.path && (this.filePath = result.data.path)
            } else {
                SendIt?.Notify?.error(result.message);
            }
            this.activeConnections -= 1;
        } else {
            this.activeConnections -= 1;
            chunksQueue.push(chunkId);
        }

        await this.sendNext(file, filename, chunksQueue);
    }

    uploadChunk(chunk, chunkId, file, filename) {
        return fetch(SendIt?.Sending?.config.actionUrl, {
            method: 'POST',
            headers: {
                'CONTENT-TYPE': 'application/octet-stream',
                'X-CHUNK-ID': chunkId,
                'CONTENT-LENGTH': chunk.size,
                'X-TOTAL-LENGTH': file.size,
                'X-CONTENT-NAME': filename,
                'X-SIACTION': 'uploadChunk',
                'X-SIPRESET': this.preset,
                'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
            },
            body: chunk
        })
    }

    addProgressBar(filename) {
        if (!this.progressWrap) return false;
        const div = document.createElement('div');
        const span = document.createElement('span');
        span.classList.add(this.config.progressClass);
        div.setAttribute(this.config.progressIdAttr, filename);
        div.appendChild(span);
        this.progressWrap.appendChild(div);
    }

    setProgress(filename, percent, msg) {
        if (!this.progressWrap) return false;

        const progressLine = this.progressWrap.querySelector(`[${this.config.progressIdAttr}="${filename}"] span`);
        const progressLineWrap = this.progressWrap.querySelector(`[${this.config.progressIdAttr}="${filename}"]`);
        progressLine && percent && (progressLine.style.width = percent)
        progressLineWrap && msg && (progressLineWrap.setAttribute(this.config.progressTextAttr, msg))
    }

    removeProgressBar(filename) {
        if (!this.progressWrap) return false;

        const progressLineWrap = this.progressWrap.querySelector(`[${this.config.progressIdAttr}="${filename}"]`);
        setTimeout(() => {
            progressLineWrap.remove()
        }, 2000)
    }

    renderPreview(filename) {
        if (!this.filePath) return;
        const dropzone = this.root.querySelector(this.config.dropzoneSelector);
        const tpl = this.root.querySelector(this.config.tplSelector)?.cloneNode(true);
        if (!tpl) return;
        tpl.innerHTML = tpl.innerHTML.replaceAll('$path', this.filePath).replaceAll('$filename', filename);
        dropzone ? dropzone.appendChild(tpl.content) : this.root.appendChild(tpl.content);
    }

    addToList(filename) {
        const hide = this.root.querySelector(this.config.hideBlockSelector);
        const fileList = this.listField.value ? this.listField.value.split(',') : [];
        if (!fileList.includes(filename)) {
            fileList.push(filename);
        }
        fileList.length && hide && hide.classList.add(this.config.hiddenClass);
        this.listField.value = fileList.join(',');
    }

    removeDir() {
        const headers = {
            'X-SIACTION': 'removeDir',
            'X-SIPRESET': 'removeDir',
            'X-SITOKEN': SendIt?.getComponentCookie('sitoken')
        }
        SendIt?.setComponentCookie('sitrusted', '1');
        SendIt?.Sending?.send(document, this.config.actionUrl, headers, '');
    }

    translitName(filename) {
        const parts = filename.split('.');
        var converter = {
            'а': 'a', 'б': 'b', 'в': 'v', 'г': 'g', 'д': 'd',
            'е': 'e', 'ё': 'e', 'ж': 'zh', 'з': 'z', 'и': 'i',
            'й': 'y', 'к': 'k', 'л': 'l', 'м': 'm', 'н': 'n',
            'о': 'o', 'п': 'p', 'р': 'r', 'с': 's', 'т': 't',
            'у': 'u', 'ф': 'f', 'х': 'h', 'ц': 'c', 'ч': 'ch',
            'ш': 'sh', 'щ': 'sch', 'ь': '', 'ы': 'y', 'ъ': '',
            'э': 'e', 'ю': 'yu', 'я': 'ya'
        };

        let word = parts[0].toLowerCase();

        var answer = '';
        for (var i = 0; i < word.length; ++i) {
            if (converter[word[i]] === undefined) {
                answer += word[i];
            } else {
                answer += converter[word[i]];
            }
        }

        answer = answer.replace(/[^-0-9a-z]/g, '-');
        answer = answer.replace(/[-]+/g, '-');
        answer = answer.replace(/^\-|-$/g, '');
        return `${answer}.${parts[1]}`;
    }
}