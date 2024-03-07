export default class Notify {
    constructor(config) {
        if (window.SendIt && window.SendIt.Notify) return window.SendIt.Notify;
        const defaults = {
            jsPath: 'assets/components/sendit/web/js/lib/izitoast/iziToast.min.js',
            cssPath: 'assets/components/sendit/web/css/lib/izitoast/iziToast.min.css',
            handlerClassName: 'iziToast',
            toastSelector: '.iziToast',
            typeSelectors: {
                success: '.iziToast-color-green',
                info: '.iziToast-color-blue',
                error: '.iziToast-color-red',
                warning: '.iziToast-color-yellow',
            },
            titleSelector: '.iziToast-title',
            handlerOptions: {
                timeout: 2500,
                position: "topCenter"
            }
        }

        this.config = Object.assign(defaults, config);
        this.loadScript(this.config.jsPath, () => {
        }, this.config.cssPath);
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

    show(type, message, options = {}) {
        message = message ? message.trim() : '';
        this.loadScript(this.config.jsPath, () => {
            if (window[this.config.handlerClassName] && Boolean(message)) {
                options = Object.assign(this.config.handlerOptions, {title: message}, options);
                try {
                    const toast = document.querySelector(this.config.typeSelectors[type]);
                    if (toast && options.upd) {
                        this.updateText(this.config.titleSelector, message);
                    } else {
                        window[this.config.handlerClassName][type](options);
                    }
                } catch (e) {
                    console.error(e, `Не найден метод ${type} в классе ${this.config.handlerClassName}`);
                }
            }
        }, this.config.cssPath);
    }

    success(message) {
        this.show('success', message, {upd: 0});
    }

    error(message) {
        this.show('error', message, {upd: 0});
    }

    info(message) {
        this.show('info', message, {upd: 0});
    }

    warning(message) {
        this.show('warning', message, {upd: 0});
    }

    close() {
        this.loadScript(this.config.jsPath, () => {
            const toast = document.querySelector(this.config.toastSelector);
            if (!toast) return;
            window[this.config.handlerClassName].hide({}, toast);
        }, this.config.cssPath)
    }

    closeAll() {
        this.loadScript(this.config.jsPath, () => {
            window[this.config.handlerClassName].destroy();
        }, this.config.cssPath)
    }

    updateText(selector, text) {
        const toastMsg = document.querySelector(selector);
        if (toastMsg) {
            toastMsg.textContent = text;
        }
    }

    setOptions(options) {
        window[this.config.handlerClassName].settings(options);
    }

    progressControl(action, options = {}) {
        const toast = document.querySelector(this.config.toastSelector);
        if (!toast) return;
        window[this.config.handlerClassName].progress(options, toast)[action]();
    }
}
