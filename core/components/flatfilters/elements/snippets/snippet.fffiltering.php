<?php
if(!$configId || !($config = $modx->getObject('ffConfiguration', $configId))){
    return $modx->lexicon('ff_err_config_id', ['configId' => $configId]);
}

$FF = $modx->getService('flatfilters','FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
$configData = $config->toArray();
$configData['scriptProperties'] = $scriptProperties;
if(!$Filtering = $FF->loadClass($configData, 'filtering')){
    return false;
}

$output =  $Filtering->run();
$output['filtersValues'] = $Filtering->getAllFiltersValues() ?: [];
$output['presetName'] = $modx->getOption('ff_preset_name', '', 'flatfilters');

if(!empty($output['filtersValues']) && $return !== 'data'){
    foreach($output['filtersValues'] as $key => $item){
        $item['key'] = $key;
        $item['options'] = '';
        $item['props'] = $scriptProperties;
        if(is_array($item['values'])){
            $chunk = $scriptProperties["{$key}TplRow"] ?? $scriptProperties["defaultTplRow"];
            if(!$chunk) continue;
            foreach($item['values'] as $idx => $value){
                $params = array_merge($scriptProperties, ['key' => $key, 'value' => $value, 'idx' => $idx]);
                $item['options'] .= $FF->pdoTools->parseChunk($chunk, $params);
            }
        }
        $chunk = $scriptProperties["{$key}TplOuter"] ?? $scriptProperties["defaultTplOuter"];
        if(!$chunk) continue;
        $item = array_merge($scriptProperties, $item);
        $output['filters'] .= $FF->pdoTools->parseChunk($chunk, $item);
    }
}

$output = array_merge($scriptProperties,$output);
return $wrapper ? $FF->pdoTools->parseChunk($wrapper, $output) : $output;