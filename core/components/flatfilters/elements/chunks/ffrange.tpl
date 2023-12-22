{if $.get[$key]}
    {set $vals = $.get[$key] | split}
{/if}
{set $start = $vals[0]?:$min}
{set $end = $vals[1]?:$max}
<div class="col-md-3 mb-3">
    <div class="row">
        <div class="col-5">
            <input type="number" value="{$start}" data-ff-start="{$key}" data-ff-filter="{$key}" name="{$key}[]">
        </div>
        <div class="col-5">
            <input type="number" value="{$end}" data-ff-end="{$key}" data-ff-filter="{$key}" name="{$key}[]">
        </div>
    </div>
    <div data-ff-range="{$key}" data-ff-min="{$min}" data-ff-max="{$max}"></div>
</div>