<form id="filterForm" class="row py-5" action="#" data-si-preset="{$presetName}" data-si-event="change">
    {$filters}
    <div class="col-md-3 mb-3">
        <select class="form-select" data-ff-filter="sortby" name="sortby">
            <option value="">Сортировать</option>
            <option value="Data.price|ASC" {$.get['sortby'] == 'Data.price|ASC' ? 'selected' : ''}>Сначала дешёвые</option>
            <option value="Data.price|DESC" {$.get['sortby'] == 'Data.price|DESC' ? 'selected' : ''}>Сначала дорогие</option>
        </select>
    </div>
</form>
<div class="d-flex justify-content-between align-items-center">
    <div>
        <p>Найдено: <span id="total">{$totalResources?:0}</span></p>
        <p><span id="time">{$totalTime}</span></p>
    </div>
    <button type="reset" class="btn-secondary btn" form="filterForm" data-ff-reset>Сбросить</button>
</div>

<div class="row" data-ff-results>
    {$resources}
</div>

<div class="d-flex justify-content-between">
    <div data-pn-pagination style="{$totalPages > 0 ? 'display:block;' : 'display:none;'}">
        <button type="button" class="btn btn-primary" data-pn-first="1">&#10094;&#10094;</button>
        <button type="button" class="btn btn-primary" data-pn-prev>&#10094;</button>
        <input type="number" name="page" data-pn-current form="filterForm" min="0" max="{$totalPages}" value="{$.get.page?:1}">
        <input type="hidden" name="limit" form="filterForm" value="{$limit}">
        <span data-pn-total>{$totalPages}</span>
        <button type="button" class="btn btn-primary" data-pn-next>&#10095;</button>
        <button type="button" class="btn btn-primary" data-pn-last="{$totalPages}">&#10095;&#10095;</button>
    </div>
</div>