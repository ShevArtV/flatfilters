<div class="col-md-3 mb-3">
    <div class="form-check col-auto">
        <input class="form-check-input" type="checkbox" data-ff-filter="{$key}" name="{$key}" value="1" id="{$key}" {$.get[$key] ? 'checked' : ''}>
        <label class="form-check-label" for="{$key}">
            {('ff_frontend_'~$key) | lexicon}
        </label>
    </div>
</div>