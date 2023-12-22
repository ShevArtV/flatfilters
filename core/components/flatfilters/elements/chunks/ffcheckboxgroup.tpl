{if $value != '0'}
    {set $values = $.get[$key] | split: ','}
<div class="form-check col-auto">
    <input class="form-check-input" type="checkbox" data-ff-filter="{$key}" name="{$key}[]" value="{$value}" id="{$key}-{$idx}" {($value in list $values) ? 'checked' : ''}>
    <label class="form-check-label" for="{$key}-{$idx}">
        {$value}
    </label>
</div>
{/if}