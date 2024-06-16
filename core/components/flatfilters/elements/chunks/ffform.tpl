<form id="filterForm" class="row py-5" action="#" data-ff-form="filterForm" data-si-preset="flatfilters">
    <input type="hidden" name="configId" value="{$configId}">
    {$filters}
    <div class="col-md-3 mb-3">
        <select class="form-select" data-ff-filter="sortby" name="sortby">
            <option value="">Сортировать</option>
            <option value="Data.price|ASC" {$.get['sortby'] == 'Data.price|ASC' ? 'selected' : ''}>Сначала дешёвые</option>
            <option value="Data.price|DESC" {$.get['sortby'] == 'Data.price|DESC' ? 'selected' : ''}>Сначала дорогие</option>
        </select>
    </div>
</form>
