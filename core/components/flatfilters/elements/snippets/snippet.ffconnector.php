<?php

$configId = (int) $_REQUEST['configId'];

if(!$configId || !($config = $modx->getObject('ffConfiguration', $configId))){
    return $modx->lexicon('ff_err_config_id', ['configId' => $configId]);
}
$scriptProperties = $_SESSION['flatfilters'][$configId]['properties'] ?: [];
$FF = $modx->getService('flatfilters','FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
$configData = $config->toArray();
$scriptProperties['limit'] = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : $scriptProperties['limit'];
$scriptProperties['upd'] = $_REQUEST['upd'];
$configData['scriptProperties'] = $scriptProperties;
if(!$Filtering = $FF->loadClass($configData, 'filtering')){
    return false;
}

$headers = getallheaders();
$preset = $headers['x-sipreset'];
$presetName = $modx->getOption('ff_preset_name', '', 'flatfilters');
if(in_array($preset, [$FF->presets['filtering'], $FF->presets['pagination']])){
    $result =  $Filtering->run();
}
if($preset === $FF->presets['disabling']){
    $result['filterValues'] = $Filtering->getCurrentFiltersValues();
    //$modx->log(1, print_r($result, 1));
}

return $SendIt->success('', $result);