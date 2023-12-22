<?php
require_once dirname(dirname(dirname(dirname(__FILE__)))).'/config.core.php';
require_once MODX_CORE_PATH.'config/'.MODX_CONFIG_KEY.'.inc.php';
require_once MODX_CONNECTORS_PATH.'index.php';


//$modx->log(1, dirname(__FILE__));
//$modx->getService('mspdDiscounts', 'mspdDiscounts', MODX_CORE_PATH . 'components/msproductdiscounts/model/');

$processorsPath = MODX_CORE_PATH.'components/flatfilters/processors/';
/** @var modConnectorRequest $request */
$request = $modx->request;
$request->handleRequest([
    'processors_path' => $processorsPath
]);