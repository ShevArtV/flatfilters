<div class="container-fluid">
    <h1 class="my-4">Список конфигураций</h1>
    <a class="btn btn-primary mb-3" href="{$_modx->config.site_url}{$_modx->config.manager_url | replace:'/': ''}/?a=configuration/manage&namespace=flatfilters">ДОБАВИТЬ КОНФИГУРАЦИЮ <i class="icon icon-plus"></i></a>

    <div class="d-flex text-center">
        <div class="col-lg-1 p-3 border-end border-white bg-secondary text-white flex-grow-1">
            <span class="fw-bold">ID</span>
        </div>
        <div class="col-lg-3 p-3 border-end border-white bg-secondary text-white flex-grow-1">
            <span class="fw-bold">Название</span>
        </div>
        <div class="col-lg-2 p-3 border-end border-white bg-secondary text-white flex-grow-1">
            <span class="fw-bold">Родители</span>
        </div>
        <div class="col-lg-2 p-3 bg-secondary text-white flex-grow-1">
            <span class="fw-bold">Действия</span>
        </div>
    </div>
    <div id="rows">
        {if $results}
            {foreach $results as $result}
                {$result.html}
            {/foreach}
        {else}
            <p class="py-3 btn">Нет ни одной конфигурации</p>
        {/if}
    </div>
    <form class="d-flex justify-content-between align-items-center mt-3">
        <div class="row">
            <div class="col-auto">
                <div class="btn-group">
                    <button type="button" disabled class="btn btn-outline-secondary js-to-first"><i class="icon icon-chevron-left"></i><i class="icon icon-chevron-left"></i></button>
                    <button type="button" disabled class="btn btn-outline-secondary js-to-prev"><i class="icon icon-chevron-left"></i></button>
                </div>

            </div>
            <div class="col-auto d-flex align-items-center justify-content-between">
                <span class="px-1">Страница:</span>
                <input type="text" class="form-control page-input" name="current_page" pattern="\d*" value="1">
                <span class="px-1">из</span>
                <span class="px-1" id="page_total">{$page_total}</span>
            </div>
            <div class="col-auto">
                <div class="btn-group">
                    <button type="button" {$page_total == 1 ? 'disabled' : ''} class="btn btn-outline-secondary js-to-next"><i class="icon icon-chevron-right"></i></button>
                    <button type="button" {$page_total == 1 ? 'disabled' : ''} class="btn btn-outline-secondary js-to-last"><i class="icon icon-chevron-right"></i><i class="icon icon-chevron-right"></i></button>
                </div>
            </div>
            <div class="col-auto d-flex align-items-center justify-content-between">
                <span class="px-1">На странице:</span>
                <input type="text" class="form-control page-input" name="limit" pattern="\d*" value="{$limit}">
            </div>
        </div>
        <div>
            <p class="mb-0">
                Показаны результаты с <span id="first_on_page">1</span> по <span id="last_on_page">{$last_on_page}</span>
            </p>
        </div>
        <div>
            <p class="mb-0">Всего: <span id="total">{$total}</span></p>
        </div>
    </form>
</div>

<div class="modal fade" tabindex="-1" id="removeModal">
    <div class="modal-dialog">
        <form class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Подтвердите действие.</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="mgr/configuration/remove">
                <input type="hidden" name="id" value="">
                <h5>Данное действие нельзя будет отменить.</h5>
                <h5>Вы уверены, что хотите удалить эту конфигурацию?</h5>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Нет</button>
                <button type="submit" class="btn btn-primary">Да</button>
            </div>
        </form>
    </div>
</div>