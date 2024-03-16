export default class PaginationHandler {
    constructor(config) {
        if (window.FlatFilters && window.FlatFilters.PaginationHandler) return window.FlatFilters.PaginationHandler;
        const defaults = {
            sendEvent: 'si:send:finish',
            paginationWrapSelector: '[data-pn-pagination]',
            firstPageBtnSelector: '[data-pn-first]',
            lastPageBtnSelector: '[data-pn-last]',
            lastPageKey: 'pnLast',
            prevPageBtnSelector: '[data-pn-prev]',
            nextPageBtnSelector: '[data-pn-next]',
            currentPageInputSelector: '[data-pn-current]',
            totalPagesSelector: '[data-pn-total]',
            limitSelector: '[data-pn-limit]',
            hideClass: 'd-none'
        }

        this.config = Object.assign(defaults, config);
        const presets = SendIt.getComponentCookie('presets', 'FlatFilters');
        this.preset = presets.pagination;
        this.initialize();
    }

    initialize() {
        this.wrapper = document.querySelector(this.config.paginationWrapSelector);
        this.pageInput = this.wrapper.querySelector(this.config.currentPageInputSelector);
        this.gotoFirstBtn = this.wrapper.querySelector(this.config.firstPageBtnSelector);
        this.gotoLastBtn = this.wrapper.querySelector(this.config.lastPageBtnSelector);
        this.gotoNextBtn = this.wrapper.querySelector(this.config.nextPageBtnSelector);
        this.gotoPrevBtn = this.wrapper.querySelector(this.config.prevPageBtnSelector);
        this.form = this.pageInput.form || this.wrapper.closest('form');

        this.buttonsHandler(this.pageInput.value);

        document.addEventListener('change', (e) => {
            if (e.target.closest(this.config.currentPageInputSelector)) {
                this.goto(Number(this.pageInput.value));
            }
            if (e.target.closest(this.config.limitSelector)) {
                this.goto(1);
            }
        })
        document.addEventListener('click', (e) => {
            if (e.target.closest(this.config.lastPageBtnSelector)) {
                this.goto(e.target.closest(this.config.lastPageBtnSelector).dataset[this.config.lastPageKey]);
            }
            if (e.target.closest(this.config.firstPageBtnSelector)) {
                this.goto(1);
            }
            if (e.target.closest(this.config.nextPageBtnSelector)) {
                this.goto(Number(this.pageInput.value) + 1);
            }
            if (e.target.closest(this.config.prevPageBtnSelector)) {
                this.goto(Number(this.pageInput.value) - 1);
            }
        });

        document.addEventListener(this.config.sendEvent, (e) => {
            this.responseHandler(e.detail.result);
        })
    }

    responseHandler(result){
        if(!result.data) return;
        if (result.data.totalPages) {
            const totalBlock = document.querySelector(this.config.totalPagesSelector);
            const currentPageInput = this.pageInput;
            const lastPage = this.gotoLastBtn;
            totalBlock.textContent = lastPage.dataset[this.config.lastPageKey] = currentPageInput.max = result.data.totalPages;
            this.wrapper.classList[result.data.totalPages > 1 ? 'remove' : 'add'](this.config.hideClass);
        } else {
            result.data.getDisabled && this.wrapper.classList.add(this.config.hideClass);
        }
        if(result.data.currentPage){
            this.buttonsHandler(result.data.currentPage);
            result.data.currentPage !== Number(this.pageInput.value) && this.goto(result.data.currentPage, true);
        }
    }

    buttonsHandler(pageNum) {
        if(this.pageInput.max <= pageNum){
            this.disabled([this.gotoLastBtn, this.gotoNextBtn, this.gotoFirstBtn, this.gotoPrevBtn]);
        }else{
            this.enabled([this.gotoLastBtn, this.gotoNextBtn, this.gotoFirstBtn, this.gotoPrevBtn]);
        }
        if (pageNum >= Number(this.pageInput.max)) {
            this.disabled([this.gotoLastBtn, this.gotoNextBtn]);
        } else {
            this.enabled([this.gotoLastBtn, this.gotoNextBtn]);
        }
        if (pageNum <= 1) {
            this.disabled([this.gotoFirstBtn, this.gotoPrevBtn]);
        } else {
            this.enabled([this.gotoFirstBtn, this.gotoPrevBtn]);
        }
    }

    disabled(elements) {
        elements.forEach(el => {
            el.disabled = true;
        })
    }

    enabled(elements) {
        elements.forEach(el => {
            el.disabled = false;
        })
    }

    goto(pageNum, nosend = false) {
        pageNum = pageNum || 1;
        if (pageNum >= Number(this.pageInput.max)) {
            pageNum = Number(this.pageInput.max);
        }
        if (pageNum <= 1) {
            pageNum = 1;
        }
        this.pageInput.value = pageNum;
        FlatFilters.MainHandler.setSearchParams('text', 'page', pageNum > 1 ? pageNum : '');

        this.form && !nosend && this.sendResponse();
    }

    async sendResponse() {
        SendIt.setComponentCookie('sitrusted', '1');
        await SendIt.Sending.prepareSendParams(this.form, this.preset, 'change');
    }
}