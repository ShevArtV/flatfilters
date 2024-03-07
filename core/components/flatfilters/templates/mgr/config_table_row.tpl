<div class="d-flex text-center" id="item-{$id}">
    <div class="col-lg-1 py-3 border-start border-end border-bottom border-secondary text-secondary d-flex align-items-center justify-content-center">
        <span class="btn">{$id}</span>
    </div>
    <div class="col-lg-3 py-3 border-end border-bottom border-secondary text-secondary d-flex flex-column align-items-center justify-content-center">
        <span class="btn">{$name}</span>
    </div>
    <div class="col-lg-2 py-3 border-end border-bottom border-secondary text-secondary d-flex align-items-center justify-content-center">
        {set $cats = $categories | split: ','}
        {foreach $cats as $cat}
            <span class="btn">{$cat | resource: 'pagetitle'}</span>
        {/foreach}
    </div>
    <div class="col-lg-2 py-3 border-bottom border-end border-secondary text-secondary d-flex align-items-center justify-content-center">
        <div class="btn-group" role="group">
            <a href="{$_modx->config.site_url}{$_modx->config.manager_url | replace:'/': ''}/?a=discount/manage&namespace=flatfilters&id={$id}" class="btn btn-primary"><i
                        class="fas fa-edit"></i></a>
            <button type="button" class="btn btn-danger" data-id="{$id}" data-bs-target="#removeModal" data-bs-toggle="modal"><i class="fas fa-trash"></i></button>
        </div>
    </div>
</div>