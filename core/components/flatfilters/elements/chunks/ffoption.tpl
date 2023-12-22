{if $value != '0'}
    {switch $key}
    {case 'parent'}
    {set $caption = ($value | resource: 'pagetitle')}
    {case 'vendor'}
    {set $caption = ($value | vendor: 'name')}
    {default}
    {set $caption = $value}
    {/switch}
    {set $values = $.get[$key] | split: ','}
    <option value="{$value}" {($value in list $values) ? 'selected' : ''}>{$caption}</option>
{/if}