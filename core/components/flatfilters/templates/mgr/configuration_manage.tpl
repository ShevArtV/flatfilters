<div class="container-fluid">
    <form action="" data-js-method="sendForm" data-js-event="submit">
        <input type="hidden" name="action" value="{$.get['id'] ? 'mgr/configuration/update' : 'mgr/configuration/create'}">
        <input type="hidden" name="id" value="{$.get['id']}">
        <h1 class="my-4">{$.get['id'] ? $title : ('mgr_ff_new_title' | lexicon)}</h1>
        <div class="d-flex justify-content-between">
            <a class="btn btn-primary mb-3" href="{$back_url}">
                {'mgr_ff_btn_back' | lexicon}
            </a>
            <button type="submit" class="btn btn-success mb-3" id="submitBtn" {$filters ? '' : 'disabled'}>{'mgr_ff_btn_save' | lexicon}</button>
        </div>
        <div class="grid">
            <div class="grid-col">
                <!-- Название и шаг -->
                <div class="bg-light shadow px-3 py-4">
                    <div class="row">
                        <div class="col-md-6 mb-3 position-relative">
                            <h5 class="form-label">Название</h5>
                            <input type="text" name="name" class="form-control" placeholder="Любое понятное имя" autocomplete="off" value="{$name}">
                        </div>
                        <div class="col-md-6 mb-3 position-relative">
                            <h5 class="form-label">Шаг</h5>
                            <input type="number" name="step" min="100" step="50" class="form-control" placeholder="100" autocomplete="off" value="{$step?:100}">
                        </div>
                    </div>
                </div>
                <!-- /Название и шаг -->
            </div>
            <div class="grid-col">
                <!-- Родители -->
                <div class="bg-light shadow px-3 py-4">
                    <div class="d-flex">
                        <div class="col-8">
                            <h5 class="">Родители</h5>
                            <p>ресурсы из этих разделов будут проиндексированы</p>
                        </div>
                    </div>
                    <div class="row justify-content-between">
                        <div class="col-9 position-relative">
                            <input type="text" name="parents" class="form-control mb-1" placeholder="введите название ресурса" autocomplete="off" id="parents"
                                   data-js-method="getSuggestions"
                                   data-js-event="input"
                                   data-js-caption="pagetitle"
                                   data-js-sort="pagetitle"
                                   data-js-fields="pagetitle,id,menutitle,longtitle"
                                   data-js-action="mgr/api/getsuggestions">
                            <ul class="suggestions_list d-none" data-js-suggestions="parents"></ul>
                            <div data-js-selected="parents">
                                {set $cats = $parents | split: ','}
                                {if $parents}
                                    {foreach $cats as $cat}
                                        <span data-js-value="{$cat}">{$cat | resource: 'pagetitle'}</span>
                                    {/foreach}
                                {/if}
                            </div>
                        </div>
                        <div class="col-3 text-end">
                            <button type="button" class="btn btn-danger" data-js-method="clearAll" data-js-event="click" data-js-target="parents">
                                {'mgr_ff_btn_clear_all' | lexicon}
                            </button>
                        </div>
                    </div>
                </div>
                <!-- /Родители -->
            </div>

            <div class="grid-col grid-col_fw">
                <!-- Фильтры  -->
                <div class="bg-light shadow px-3 py-4">
                    <div class="d-flex">
                        <div class="col-8">
                            <h5 class="">Фильтры</h5>
                            <p>поля для фильтрации</p>
                        </div>
                    </div>
                    <div class="row justify-content-between">
                        <div class="col-4">
                            <button type="button" data-bs-target="#addFilterModal" data-bs-toggle="modal" class="btn btn-primary mb-3">
                                {'mgr_ff_btn_add' | lexicon}
                            </button>
                        </div>
                        <div class="col-4 text-end">
                            <button type="button" class="btn btn-danger mb-3" data-js-method="clearAll" data-js-event="click" data-js-target="filters">
                                {'mgr_ff_btn_clear_all' | lexicon}
                            </button>
                        </div>
                    </div>
                    <div class="d-flex text-center">
                        <div class="col-4 p-2 border-end border-white bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Наименование</span>
                        </div>
                        <div class="col-2 p-2 border-end border-white bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Тип поля</span>
                        </div>
                        <div class="col-2 p-2 border-end border-white bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Тип сравнения</span>
                        </div>
                        <div class="col-2 p-2 border-end border-white bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Значение</span>
                        </div>
                        <div class="col-1 p-2 border-end border-white bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Знак</span>
                        </div>
                        <div class="col-1 p-2 border-end border-secondary bg-secondary text-white flex-grow-1 align-items-center d-flex justify-content-center">
                            <span class="fw-bold">Удалить</span>
                        </div>
                    </div>
                    <div data-js-wrapper="filters">
                        {$filtersHtml}
                    </div>
                </div>
                <!-- /Фильтры -->
            </div>
        </div>
    </form>
</div>

<div class="modal fade" data-checkbox-modal data-add-modal tabindex="-1" id="addFilterModal">
    <div class="modal-dialog">
        <form class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" data-title>Добавление фильтра</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-12 mb-3 position-relative">
                        <h5 class="form-label">Ключ поля</h5>
                        <input type="text" name="filter" class="form-control" placeholder="введите ключ" autocomplete="off" id="filters"
                               data-js-method="getLocalSuggestions"
                               data-js-event="input"
                               data-js-caption="caption">
                        <ul class="suggestions_list d-none" data-js-suggestions="filters"></ul>
                    </div>
                    <div class="col-12 mb-3 position-relative">
                        <h5 class="form-label">Тип поля</h5>
                        <select class="form-select" name="field_type" data-js-method="setAllowed" data-js-event="change" data-js-target="filter_type">
                            <option value="varchar" data-js-allow="string,multiple">{'ff_type_varchar' | lexicon}</option>
                            <option value="int" data-js-allow="number,numrange,multiple">{'ff_type_int' | lexicon}</option>
                            <option value="decimal" data-js-allow="number,numrange,multiple">{'ff_type_decimal' | lexicon}</option>
                            <option value="timestamp" data-js-allow="date,daterange,multiple">{'ff_type_timestamp' | lexicon}</option>
                            <option value="tinyint" data-js-allow="number">{'ff_type_tinyint' | lexicon}</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3 position-relative">
                        <h5 class="form-label">Тип сравнения</h5>
                        <select class="form-select" name="filter_type" id="filter_type">
                            <option value="string">{'ff_type_string' | lexicon}</option>
                            <option value="number" disabled>{'ff_type_number' | lexicon}</option>
                            <option value="numrange" disabled>{'ff_type_numrange' | lexicon}</option>
                            <option value="date" disabled>{'ff_type_date' | lexicon}</option>
                            <option value="daterange" disabled>{'ff_type_daterange' | lexicon}</option>
                            <option value="multiple">{'ff_type_multiple' | lexicon}</option>
                        </select>
                    </div>
                    <div class="col-12 position-relative">
                        <p class="form-text mb-1">
                            Если нужно задать параметры выборки заранее, используйте поля снизу.
                            При этом фильтры, для которых заданы сравнение и значение по умолчанию, не будут показаны пользователю на фронте.
                        </p>
                    </div>
                    <div class="col-12 mb-3 position-relative">
                        <h5 class="form-label">Знак</h5>
                        <select class="form-select" name="sign">
                            <option value="">{'ff_compare_empty' | lexicon}</option>
                            <option value="eq">{'ff_compare_eq' | lexicon}</option>
                            <option value="gt">{'ff_compare_gt' | lexicon}</option>
                            <option value="lt">{'ff_compare_lt' | lexicon}</option>
                            <option value="lteq">{'ff_compare_lteq' | lexicon}</option>
                            <option value="gteq">{'ff_compare_gteq' | lexicon}</option>
                            <option value="in">{'ff_compare_in' | lexicon}</option>
                            <option value="between">{'ff_compare_between' | lexicon}</option>
                        </select>
                    </div>
                    <div class="col-12 mb-3 position-relative">
                        <h5 class="form-label">Значение</h5>
                        <input type="text" name="default_value" class="form-control" placeholder="введите значение" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary"
                        data-js-method="setFilters"
                        data-js-event="click"
                        data-js-target="filter"
                        data-js-action="mgr/api/renderchunk"
                        data-js-tpl="chunks/filter_table_row.tpl">
                    Готово
                </button>
            </div>
        </form>
    </div>
</div>