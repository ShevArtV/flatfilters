<?php
require_once MODX_CORE_PATH . 'components/flatfilters/handlers/filtering.class.php';
$result = [];
//$modx->log(1, print_r($_REQUEST, 1));
$scriptProperties = $_SESSION['flatfilters']['properties'] ?: [];

$FF = new Filtering($modx, $scriptProperties);
$headers = getallheaders();
$preset = $headers['x-sipreset'];
$presetName = $modx->getOption('ff_preset_name', '', 'flatfilters');

$scriptProperties['limit'] = isset($_REQUEST['limit']) ? (int)$_REQUEST['limit'] : $scriptProperties['limit'];

if($preset === $presetName){
    $result =  $FF->initialize();
}
if($preset === 'get_disabled'){
    $result['filterValues'] = $FF->getFiltersValues();
    $result['getDisabled'] = 1;
    //$modx->log(1, print_r($result, 1));
}

return $SendIt->success('', $result);