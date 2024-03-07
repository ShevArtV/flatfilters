<div class="d-flex text-center" id="filter-{$default_filter}" data-js-value="filters">
    <div class="col-4 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {('ff_'~$default_filter) | lexicon}
    </div>
    <div class="col-3 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {('ff_compare_'~$compare) | lexicon}
    </div>
    <div class="col-3 p-2 border-start border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        {$value}
    </div>
    <div class="col-2 p-2 border-start border-end border-bottom border-secondary text-secondary align-items-center d-flex justify-content-center">
        <button type="button" class="btn btn-danger"
                data-js-target="filter-{$default_filter}"
                data-js-method="removeItem"
                data-js-action="mgr/api/renderchunk"
                data-js-value="{$default_filter}">
            <i class="icon icon-trash"></i>
        </button>
    </div>
</div>