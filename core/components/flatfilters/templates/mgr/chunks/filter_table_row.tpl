<div class="d-flex text-center" id="filter-{$filter}" data-js-value="filters">
    <div class="col-4 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {('ff_frontend_'~$filter) | lexicon}
    </div>
    <div class="col-2 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {('ff_type_'~$field_type) | lexicon}
    </div>
    <div class="col-2 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {('ff_type_'~$filter_type) | lexicon}
    </div>
    <div class="col-2 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {$default_value}
    </div>
    <div class="col-1 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {$sign ? (('ff_compare_'~$sign) | lexicon) : ''}
    </div>
    <div class="col-1 p-2 border-start border-end border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        <button type="button" class="btn btn-danger"
                data-js-target="filter-{$filter}"
                data-js-method="removeItem"
                data-js-event="click"
                data-js-value="{$filter}">
            <i class="icon icon-trash"></i>
        </button>
    </div>
</div>