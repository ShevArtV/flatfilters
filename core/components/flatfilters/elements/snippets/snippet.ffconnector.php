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

if($preset === $FF->presets['disabling'] && !$scriptProperties['noDisabled']){
    $result['filterValues'] = $Filtering->getCurrentFiltersValues();
}

$totalVar = $_SESSION['flatfilters'][$configId]['totalVar'];
$result[$totalVar] = $_SESSION['flatfilters'][$configId][$totalVar];
$result['totalTime'] = $_SESSION['flatfilters'][$configId]['totalTime'];

return $SendIt->success('', $result);
