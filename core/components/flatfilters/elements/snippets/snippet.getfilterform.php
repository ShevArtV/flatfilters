<?php
if(!$configId || !($config = $modx->getObject('ffConfiguration', $configId))){
    return $modx->lexicon('ff_err_config_id', ['configId' => $configId]);
}
$scriptProperties['presetName'] = $modx->getOption('ff_preset_name', '', 'flatfilters');
$modx->setPlaceholder('filters.presetName', $scriptProperties['presetName']);
$configData = $config->toArray();
$configData['scriptProperties'] = $scriptProperties;

$FF = $modx->getService('flatfilters','FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
if(!$Filtering = $FF->loadClass($configData, 'filtering')){
    return false;
}

$output = []; //$Filtering->run();
$output['filtersValues'] = $Filtering->getAllFiltersValues() ?: [];
return $Filtering->renderFilterForm($scriptProperties, $output);