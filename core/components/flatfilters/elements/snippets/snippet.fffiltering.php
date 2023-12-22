<?php
require_once MODX_CORE_PATH . 'components/flatfilters/handlers/filtering.class.php';
$FF = new Filtering($modx, $scriptProperties);
if(!$configId || !$modx->getCount('ffConfiguration', $configId)) return $modx->lexicon('ff_err_config_id', ['configId' => $configId]);
$output =  $FF->initialize();
$output['filtersValues'] = $configId ? $FF->getAllFiltersValues() : [];
$output['presetName'] = $modx->getOption('ff_preset_name', '', 'flatfilters');

if(!empty($output['filtersValues']) && $return !== 'data'){
    foreach($output['filtersValues'] as $key => $item){
        $item['key'] = $key;
        $item['options'] = '';
        if(is_array($item['values'])){
            $chunk = $scriptProperties["{$key}TplRow"] ?: $scriptProperties["defaultTplRow"];
            foreach($item['values'] as $idx => $value){
                $item['options'] .= $FF->pdo->parseChunk($chunk, ['key' => $key, 'value' => $value, 'idx' => $idx]);
            }
        }
        $chunk = $scriptProperties["{$key}TplOuter"] ?: $scriptProperties["defaultTplOuter"];
        $output['filters'] .= $FF->pdo->parseChunk($chunk, $item);
    }
}

return $FF->pdo->parseChunk($wrapper, $output);
