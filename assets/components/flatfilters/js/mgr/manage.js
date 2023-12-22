import * as functions from './functions.min.js';

export default class manage {
    constructor(config) {
        this.config = config;
        this.mainContainer = document.querySelector('#modx-content');
        this.mainContainer.innerHTML = this.config.template;
        this.config.forms = document.querySelectorAll('.jsManageForm');
        this.config.mainObjectId = document.querySelector('.js-main-id');
        this.config.mainObjectAction = document.querySelector('.js-main-action');
        this.config.date_start = document.querySelector('.js-period-start');
        this.config.date_end = document.querySelector('.js-period-end');
        this.config.list_date = document.querySelector('.js-list-date');
        this.config.data_keys = document.querySelectorAll('[name="data_key"]');
        this.config.resource_select = document.querySelector('select[name="resource"]');
        this.config.toggler_radio = document.querySelectorAll('.js-toggler-radio');
        this.config.remove_modal = document.querySelector('#removeModal');
        this.config.add_modals = document.querySelectorAll('[data-add-modal]');
        this.config.checkbox_modals = document.querySelectorAll('[data-checkbox-modal]');
        this.config.discount_name = document.querySelector('[name="name"]');
        this.config.checkbox_togglers = document.querySelectorAll('input[type="checkbox"]');
        this.config.select_all_btns = document.querySelectorAll('[data-select-all]');
        this.config.clear_all_btns = document.querySelectorAll('[data-clear-all]');
        this.config.start_search_btns = document.querySelectorAll('[data-start-search]');
        this.config.headers = {
            "X-Requested-With": "XMLHttpRequest",
            "modAuth": MODx.siteId
        };
        this.inputEvent = new Event('input');
        this.suggestionsElements = document.querySelectorAll('.jsSuggestions');
        this.initialize();

    }

    initialize() {
        if (this.config.resource_select) {
            this.config.resource_select.addEventListener('change', e => {
                if (!this.config.discount_name.value) {
                    this.config.discount_name.value = this.config.resource_select.options[this.config.resource_select.selectedIndex].innerHTML;
                }
            });
        }


        if (this.config.checkbox_togglers.length) {
            this.config.checkbox_togglers.forEach(el => {
                el.addEventListener('change', e => {
                    const input = document.querySelector('[name="' + el.dataset.target + '"]');
                    if (input) {
                        if (el.checked) {
                            input.value = 1;
                        } else {
                            input.value = 0;
                        }
                    }
                });
            });
        }

        if (this.config.start_search_btns.length) {
            this.config.start_search_btns.forEach(btn => {
                btn.addEventListener('click', this.searchProducts.bind(this));
            });
        }

        if (this.config.data_keys.length) {
            this.config.data_keys.forEach(key => {
                key.addEventListener('change', this.toggleDataValue);
            });

        }

        if (this.config.select_all_btns.length) {
            this.config.select_all_btns.forEach(btn => {
                btn.addEventListener('click', this.selectAll.bind(this));
            });
        }

        if (this.config.clear_all_btns.length) {
            this.config.clear_all_btns.forEach(btn => {
                btn.addEventListener('click', this.clearAll.bind(this));
            });
        }

        if (this.config.remove_modal) {
            this.config.remove_modal.addEventListener('show.bs.modal', function (e) {
                functions.confirmModal(this.config.remove_modal, {
                        id: e.relatedTarget.dataset.id,
                        action: e.relatedTarget.dataset.action,
                        class: e.relatedTarget.dataset.class,
                    },
                    {'[data-removed-item]': e.relatedTarget.dataset.removedItem});
            }.bind(this));
        }
        if (this.config.add_modals.length) {
            this.config.add_modals.forEach(modal => {
                modal.addEventListener('show.bs.modal', function (e) {
                    functions.confirmModal(modal, {action: e.relatedTarget.dataset.action}, {'[data-title]': e.relatedTarget.dataset.title});
                }.bind(this));
            });
        }

        if (this.config.checkbox_modals.length) {
            this.config.checkbox_modals.forEach(modal => {
                modal.addEventListener('show.bs.modal', this.selectSomeCheckboxes.bind(this));
            });
        }

        if (this.suggestionsElements) {
            this.suggestionsElements.forEach(el => {
                this.addSuggestions(el);
                el.addEventListener('change', (e) => !el.value ? document.querySelector(el.dataset.target).value = '' : '');
            });
        }
        if (this.config.forms) {
            this.config.forms.forEach(form => {
                let fields = form.querySelectorAll('[name]');
                form.addEventListener('submit', e => {
                    e.preventDefault();
                    form.querySelector('button[type="submit"]').disabled = true;
                    let params = new FormData(form);
                    functions.sendAjax(params, (response) => {
                        form.querySelector('button[type="submit"]').disabled = false;
                        if (response.success) {
                            this.responseSuccess(response, form);
                        } else {
                            this.showErrors(response.data, form);
                            if (response.message) {
                                functions.showNotify('error', response.message);
                            }
                        }
                    }, this.config.connector_url, this.config.headers);
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
        if (this.config.date_start && this.config.date_end) {
            const endDateInput = document.querySelector('[name="end_date"]');
            const startDateInput = document.querySelector('[name="start_date"]');
            const inputEvent = this.inputEvent;
            const today = new Date();
            today.setHours(0, 0);
            const endDate = new AirDatepicker(this.config.date_end, {
                inline: true,
                timepicker: true,
                timeFormat: 'HH:mm',
                minDate: this.config.date_end.dataset.end,
                onSelect({date, formattedDate, datepicker}) {
                    endDateInput.dispatchEvent(inputEvent);
                }
            });
            const startDate = new AirDatepicker(this.config.date_start, {
                inline: true,
                timepicker: true,
                timeFormat: 'HH:mm',
                startDate: today,
                onSelect({date, formattedDate, datepicker}) {
                    startDateInput.dispatchEvent(inputEvent);
                    if (date) {
                        let time = formattedDate.split(' ');
                        let [hours, minutes] = time[1].split(':');
                        date = new Date(date.setHours(Number(hours) + 1, minutes));
                        endDate.clear();
                        endDate.update({
                            minDate: date
                        });
                        endDate.selectDate(date, {updateTime: true, silent: true});
                    } else {
                        endDate.clear();
                    }
                }
            });
        }
    }

    getCategories() {
        const ids = [];
        const categoriesEl = document.querySelectorAll('[data-category]');
        if (categoriesEl.length) {
            categoriesEl.forEach(el => ids.push(el.dataset.category));
        }
        return ids;
    }

    searchProducts(e) {
        const query = document.querySelector(e.target.dataset.query).value;
        const wrapper = document.querySelector(e.target.dataset.target);
        const params = new FormData();
        const action = e.target.dataset.action;
        params.append('action', action);
        params.append('parents', this.getCategories());
        params.append('query', query);
        params.append('fields', e.target.dataset.fields);
        params.append('limit', 999);
        if (query.length > 2) {
            functions.sendAjax(params, function (response) {
                if (response.success) {
                    //console.log(response);
                    if (response.object.html) {
                        wrapper.innerHTML = response.object.html;
                        if (action.indexOf('/products/') !== -1) {
                            this.selectSomeCheckboxes('', wrapper, '[data-product]', 'product')
                        }
                        if (action.indexOf('/exproducts/') !== -1) {
                            this.selectSomeCheckboxes('', wrapper, '[data-exproduct]', 'exproduct')
                        }
                    }
                }
            }.bind(this), this.config.connector_url, this.config.headers);
        }
    }

    toggleDataValue(e) {
        const modal = e.target.closest('.modal');
        const data_value_input = modal.querySelector('input[name="data_value"]');
        const data_value_select = modal.querySelector('select[name="data_value"]');
        if (e.target.value === 'vendor') {
            data_value_input.disabled = true;
            data_value_input.classList.add('visually-hidden');

            data_value_select.disabled = false;
            data_value_select.classList.remove('visually-hidden');
        } else {
            data_value_input.disabled = false;
            data_value_input.classList.remove('visually-hidden');

            data_value_select.disabled = true;
            data_value_select.classList.add('visually-hidden');
        }
    }

    selectSomeCheckboxes(e, wrapper, sourceSelector, param) {
        sourceSelector = sourceSelector || e.relatedTarget.dataset.source;
        param = param || e.relatedTarget.dataset.param;
        wrapper = wrapper || e.target;
        const sources = document.querySelectorAll(sourceSelector);
        const targets = wrapper.querySelectorAll('input[type="checkbox"]');
        const submenus = wrapper.querySelectorAll('.submenu_wrapp');
        const ids = [];

        if (sources.length) {
            sources.forEach(source => ids.push(source.dataset[param]));
        }
        if (targets.length) {
            targets.forEach(target => {
                target.checked = ids.indexOf(target.value) !== -1;
            });
        }
        if (submenus.length) {
            submenus.forEach(submenu => {
                let parentLabel = submenu.querySelector('label');
                let checked = submenu.querySelectorAll('[type="checkbox"]:checked');
                if (checked.length) {
                    parentLabel.classList.add('text-primary');
                } else {
                    parentLabel.classList.remove('text-primary');
                }
            });
        }
    }

    getAllFormCheckboxes(element) {
        const form = element.closest('form');
        if (form) {
            return form.querySelectorAll('input[type="checkbox"]');
        }
    }

    selectAll(e) {
        const checkboxes = this.getAllFormCheckboxes(e.target);
        if (checkboxes && checkboxes.length) {
            checkboxes.forEach(el => el.checked = 1);
        }
    }

    clearAll(e) {
        const checkboxes = this.getAllFormCheckboxes(e.target);
        if (checkboxes && checkboxes.length) {
            checkboxes.forEach(el => el.checked = 0);
        }
    }

    responseSuccess(response, form) {
        const action = response.object.action.split('/');
        if (response.object.action === 'mgr/discount/create') {
            if (response.object.id) {
                this.config.mainObjectId.value = response.object.id;
                this.config.mainObjectAction = 'mgr/discount/update';
                window.location.replace(window.location.href + '&id=' + response.object.id);
            }
        }

        if (action[2] === 'create' && action[1] !== 'discount') {
            let wrap = document.getElementById(action[1] + 'Wrap');
            if (wrap) {
                if (action[1].indexOf('categories') !== -1 || action[1].indexOf('products') !== -1) {
                    wrap.innerHTML = response.object.html;
                } else {
                    wrap.innerHTML += response.object.html;
                }
            }
        }

        if (action[2] === 'remove' || action[2] === 'clean') {
            const modal = bootstrap.Modal.getInstance(this.config.remove_modal);
            modal.hide();
            if (action[2] === 'clean') {
                document.querySelector('[data-' + response.object.class.toLowerCase() + ']').innerHTML = '';
            } else {
                document.querySelector('#' + response.object.class + '-' + response.object.id).remove();
            }
            functions.showNotify('success', 'Данные удалены.');
        }
        if (action[2] === 'create' || action[2] === 'update') {
            functions.showNotify('success', 'Данные сохранены.');
        }

        if (response.object.action === 'mgr/discount/create' || response.object.action === 'mgr/discount/update'){
            setTimeout(() => {
                window.location.reload();
            }, 2000);
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

    suggestionSelect(e, input, wrapper) {
        if (input.dataset.target) {
            document.querySelector(input.dataset.target).value = e.target.dataset.value;
        }
        input.value = e.target.innerText;
        !this.config.discount_name.value ? this.config.discount_name.value = e.target.innerText : '';
        wrapper.innerHTML = '';
        wrapper.classList.add('d-none');
    }

    addSuggestions(el) {
        const form = el.closest('form');
        const suggestionWrap = form.querySelector('.suggestions_' + el.name);
        const params = new FormData();
        params.append('action', el.dataset.action);
        params.append('fields', el.dataset.fields);
        params.append('did', el.dataset.did);
        params.append('limit', 10);
        params.append('start', 0);
        params.append('did', el.dataset.did);
        params.append('sort', el.dataset.sort ? el.dataset.sort : 'pagetitle');
        params.append('dir', el.dataset.dir ? el.dataset.dir : 'ASC');
        el.addEventListener('input', e => {
            if (el.value.length > 1) {
                if (params.has('value')) {
                    params.set('value', el.value);
                } else {
                    params.append('value', el.value);
                }
                functions.sendAjax(params, (response) => {
                    suggestionWrap.innerHTML = '';
                    suggestionWrap.classList.add('d-none');

                    if (response.success) {
                        if (response.results.length) {
                            //console.log(response.results);
                            response.results.forEach(obj => {
                                let value = obj.key || obj.id;
                                let li = document.createElement('li');
                                li.setAttribute('data-value', value);
                                li.innerText = obj[el.dataset.captionField];
                                li.addEventListener('click', e => this.suggestionSelect(e, el, suggestionWrap))
                                suggestionWrap.appendChild(li);
                            });
                            suggestionWrap.classList.remove('d-none');
                        }
                    }
                }, this.config.connector_url, this.config.headers);
            }
        });
    }
}