<div class="d-flex text-center w-100 position-relative" id="item-{$id}">
    {set $percent = $total ? ($offset * 100 / $total) : '0'}
    <div class="progress {($percent >= 100) ? 'progress_finished':''}" {($percent < 100) ? 'style="width:'~$percent~'%"' : ''}></div>
    <div class="col-1 flex-grow-1 py-3 border-start border-end border-bottom border-secondary text-secondary d-flex align-items-center justify-content-center position-relative">
        <span class="btn">{$id}</span>
    </div>
    <div class="col-3 flex-grow-1 py-3 border-end border-bottom border-secondary text-secondary d-flex flex-column align-items-center justify-content-center position-relative">
        <span class="btn">{$name}</span>
    </div>
    <div class="col-2 flex-grow-1 py-3 border-end border-bottom border-secondary text-secondary d-flex flex-wrap align-items-center justify-content-center position-relative">
        {set $cats = $parents | split: ','}
        {foreach $cats as $cat}
            <span class="btn w-100">{$cat | resource: 'pagetitle'}</span>
        {/foreach}
    </div>
    <div class="col-2 flex-grow-1 py-3 border-bottom border-end border-secondary text-secondary d-flex align-items-center justify-content-center position-relative">
        <div class="btn-group" role="group">
            <a title="Редактировать" href="{$_modx->config.site_url}{$_modx->config.manager_url | replace:'/': ''}/?a=configuration/manage&namespace=flatfilters&id={$id}" class="btn btn-primary"><i
                        class="icon icon-edit"></i></a>
            <form data-js-form>
                <input type="hidden" name="action" value="mgr/configuration/duplicate">
                <input type="hidden" name="id" value="{$id}">
                <button title="Копировать" type="submit" class="btn btn-warning radius_0"><i class="icon icon-copy"></i></button>
            </form>
            <form data-js-form>
                <input type="hidden" name="action" value="mgr/configuration/indexing">
                <input type="hidden" name="id" value="{$id}">
                <button title="Индексировать" type="submit" class="btn btn-success radius_0"><i class="icon icon-refresh"></i></button>
            </form>
            <button title="Удалить" type="button" class="btn btn-danger" data-id="{$id}" data-bs-target="#removeModal" data-bs-toggle="modal"><i class="icon icon-trash"></i></button>
        </div>
    </div>
</div>
