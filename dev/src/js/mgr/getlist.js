import * as functions from './functions.min.js';

export default class GetList {
    constructor(config) {
        this.config = config;
        this.mainContainer = document.querySelector('#modx-content');
        this.mainContainer.innerHTML = this.config.template;
        this.config.rows_wrapper = document.querySelector('#rows');
        this.config.row_prefix = '#item-'
        this.config.remove_modal = document.querySelector('#removeModal');
        this.config.copy_modal = document.querySelector('#copyModal');
        this.config.modals = document.querySelectorAll('.modal');
        this.config.forms = document.querySelectorAll('[data-js-form]');
        this.config.activateForms = document.querySelectorAll('.js_form_active');

        this.start = 0;
        this.limit = document.querySelector('input[name="limit"]');
        this.current_page = document.querySelector('input[name="current_page"]');
        this.first_on_page_el = document.querySelector('#first_on_page');
        this.last_on_page_el = document.querySelector('#last_on_page');
        this.page_total_el = document.querySelector('#page_total');
        this.total_el = document.querySelector('#total');
        this.to_first_btn = document.querySelector('.js-to-first');
        this.to_prev_btn = document.querySelector('.js-to-prev');
        this.to_last_btn = document.querySelector('.js-to-last');
        this.to_next_btn = document.querySelector('.js-to-next');

        this.changeEvent = new Event('change');

        this.config.headers = {
            "X-Requested-With": "XMLHttpRequest",
            "modAuth": MODx.siteId
        };
        this.initialize();
    }

    initialize() {

        if(this.config.activateForms.length){
            this.config.activateForms.forEach(form => {
                this.addListenerToActiveBtn(form);
            });
        }


        if (this.config.remove_modal) {
            this.addListenerToRemoveForm(this.config.remove_modal)
        }

        if (this.config.copy_modal) {
            this.config.copy_modal.addEventListener('shown.bs.modal', function (e) {
                functions.confirmModal(this.config.copy_modal, {id: e.relatedTarget.dataset.id});
            }.bind(this));
        }

        if (this.current_page) {
            this.current_page.addEventListener('change', e => {
                let start = 0,
                    last_page = Number(this.page_total_el.innerText),
                    page = Number(this.current_page.value);
                if (page > last_page) {
                    page = last_page;
                }
                if (page < 1) {
                    page = 1
                }
                start = (page - 1) * this.limit.value;
                this.current_page.value = page;
                this.getList(start, this.limit.value);
            });
        }

        if (this.limit) {
            this.limit.addEventListener('change', e => {
                this.current_page.value = 1;
                this.current_page.dispatchEvent(this.changeEvent);
            });
        }

        if (this.to_first_btn) {
            this.to_first_btn.addEventListener('click', e => {
                this.current_page.value = 1;
                this.current_page.dispatchEvent(this.changeEvent);
            });
        }
        if (this.to_prev_btn) {
            this.to_prev_btn.addEventListener('click', e => {
                this.current_page.value = Number(this.current_page.value) - 1;
                this.current_page.dispatchEvent(this.changeEvent);
            });
        }
        if (this.to_next_btn) {
            this.to_next_btn.addEventListener('click', e => {
                this.current_page.value = Number(this.current_page.value) + 1;
                this.current_page.dispatchEvent(this.changeEvent);
            });
        }
        if (this.to_last_btn) {
            this.to_last_btn.addEventListener('click', e => {
                this.current_page.value = this.page_total_el.innerText;
                this.current_page.dispatchEvent(this.changeEvent);
            });
        }

        if (this.config.forms.length) {
            this.setFormHandler(this.config.forms);
        }
    }

    setFormHandler(forms) {
        forms.forEach(form => {
            let fields = form.querySelectorAll('[name]');
            form.addEventListener('submit', e => {
                e.preventDefault();
                form.closest('.btn-group').classList.add('disabled');
                let params = new FormData(form);
                this.sendForm(params, form);
            });
            fields.forEach(field => {
                field.addEventListener('input', e => {
                    if (field.classList.contains('error')) {
                        this.hideErrors(field, form);
                    }
                });
            });
        });
    }

    sendForm(params, form){
        functions.sendAjax(params, (response) => {
            if (response.success) {
                this.responseSuccess(response, form);
            } else {
                this.showErrors(response.data, form);
                if (response.message) {
                    functions.showNotify(response.message, 'error');
                }
            }
        }, this.config.connector_url, this.config.headers);
    }

    responseSuccess(response, form) {
        if (response.object.action === 'mgr/configuration/duplicate') {
            if (response.object.url) {
                window.location.href = response.object.url;
            }
        }
        if (response.object.action === 'mgr/configuration/indexing') {
            document.querySelector(`#item-${response.object.id} .progress`).classList.remove('progress_finished');
            if(!response.object.finished){
                let params = new FormData(form);
                this.sendForm(params, form);
                document.querySelector(`#item-${response.object.id} .progress`).style.width = response.object.percent;

            }else{
                document.querySelector(`#item-${response.object.id} .progress`).style.width = response.object.percent;
                document.querySelector(`#item-${response.object.id} .progress`).classList.add('progress_finished');
                form.closest('.btn-group').classList.remove('disabled');
            }
        }
    }

    hideErrors(field, form) {
        let error = form.querySelector('.error_' + field.name);
        field.classList.remove('error');
        if (error) {
            error.innerText = '';
        }
    }

    showErrors(data, form) {
        data.forEach(el => {
            if (form.querySelector('.error_' + el.id)) {
                form.querySelector('.error_' + el.id).innerText = el.msg;
            }
            if (form.querySelector('[name="' + el.id + '"]')) {
                form.querySelector('[name="' + el.id + '"]').classList.add('error');
            }
        });
    }

    pagination(total) {
        let total_page = Math.ceil(total / this.limit.value),
            first_on_page = (Number(this.current_page.value) - 1) * Number(this.limit.value) + 1,
            last_on_page = first_on_page + Number(this.limit.value) - 1;

        if (this.page_total_el) {
            this.page_total_el.innerText = total_page;
        }

        if (this.first_on_page_el) {
            this.first_on_page_el.innerText = first_on_page;
        }
        if (this.last_on_page_el) {
            if (last_on_page > Number(this.total_el.innerText)) last_on_page = Number(this.total_el.innerText);
            this.last_on_page_el.innerText = last_on_page;
        }

        if (this.total_el) {
            this.total_el.innerText = total;
        }

        if (this.page_total_el && this.current_page.value) {
            if (this.current_page.value == 1) {
                this.to_first_btn.disabled = true;
                this.to_prev_btn.disabled = true;
            } else {
                this.to_first_btn.disabled = false;
                this.to_prev_btn.disabled = false;
            }
            if (this.current_page.value == this.page_total_el.innerText) {
                this.to_last_btn.disabled = true;
                this.to_next_btn.disabled = true;
            } else {
                this.to_last_btn.disabled = false;
                this.to_next_btn.disabled = false;
            }
        }
    }

    getList(start, limit) {
        let get_forms_params = new FormData();
        get_forms_params.append('start', start);
        get_forms_params.append('limit', limit);
        get_forms_params.append('action', 'mgr/discount/getlist');

        functions.sendAjax(get_forms_params, (response) => {
            this.config.rows_wrapper.classList.remove('loading');
            if (response.success) {
                if (response.total) {
                    this.renderResult(response.results);
                    const activateForms = document.querySelectorAll('.js_form_active');
                    if(activateForms.length){
                        activateForms.forEach(form => {
                            this.addListenerToActiveBtn(form);
                        });
                    }
                    this.pagination(response.total);
                    if (this.config.remove_modal) {
                        this.addListenerToRemoveForm(this.config.remove_modal)
                    }
                }
            }
        }, this.config.connector_url, this.config.headers, this.config.rows_wrapper);
    }

    addListenerToRemoveForm(modal) {
        let form = modal.querySelector('form');
        this.config.remove_modal.addEventListener('shown.bs.modal', function (e) {
            functions.confirmModal(modal, {id: e.relatedTarget.dataset.id});
        });
        form.addEventListener('submit', e => {
            e.preventDefault();
            let get_forms_params = new FormData(form);
            functions.sendAjax(get_forms_params, (response) => {
                if (response.success) {
                    functions.success(response, this.config.rows_wrapper, this.config.modals, this.config.row_prefix);
                }
            }, this.config.connector_url, this.config.headers, this.config.rows_wrapper);
        });
    }

    addListenerToActiveBtn(form) {
        form.addEventListener('submit', e => {
            e.preventDefault();
            let btn = document.querySelector('button[form="' + form.getAttribute('id') + '"]'),
                input = form.querySelector('[name="active"]'),
                get_forms_params = '';
            if (btn.classList.contains('choosen')) {
                input.value = 0;
            } else {
                input.value = 1;
            }
            btn.disabled = true;
            get_forms_params = new FormData(form);
            functions.sendAjax(get_forms_params, (response) => {
                this.config.rows_wrapper.classList.remove('loading');
                if (response.success) {
                    btn.classList.toggle('choosen');
                    btn.disabled = false;
                }
            }, this.config.connector_url, this.config.headers, this.config.rows_wrapper);
        });
    }

    renderResult(results) {
        let output = '';
        results.forEach((el) => {
            output += el.html;
        });
        this.config.rows_wrapper.innerHTML = output;
    }
}