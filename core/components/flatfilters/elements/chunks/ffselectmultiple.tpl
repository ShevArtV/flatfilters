<div class="col-md-3 mb-3">
    <select class="form-select" multiple data-ff-filter="{$key}" name="{$key}[]">
        <option value="" {!$.get[$key] ? 'selected' : ''}>{('ff_frontend_'~$key) | lexicon}</option>
        {$options}
    </select>
</div>