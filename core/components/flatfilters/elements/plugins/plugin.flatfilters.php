<?php
include_once MODX_CORE_PATH . 'components/flatfilters/handlers/indexing.class.php';
$corePath = MODX_CORE_PATH . 'components/flatfilters/';
$modx->addPackage('flatfilters', $corePath . 'model/');

switch ($modx->event->name) {
    case 'OnGetFormParams':
        $values = &$modx->event->returnedValues;
        if ($presetName === $modx->getOption('ff_preset_name', '', 'flatfilters') || $presetName === 'get_disabled') {
            $values = [
                'hooks' => '',
                'snippet' => $modx->getOption('ff_connector', '', 'ffConnector')
            ];
        }
        break;

    case 'OnDocFormSave':
    case 'OnResourceUndelete':
        Indexing::removeResourceIndex($modx, $id);
        $resourceData = $resource->toArray();
        if ($resourceData['published'] && !$resourceData['deleted']) {
            $parents = Indexing::getParentIds($modx, $resourceData['parent']);
            if ($resourceData['class_key'] === 'msProduct') {
                $table_prefix = $modx->getOption('table_prefix');
                $sql = "SELECT `category_id` FROM `{$table_prefix}ms2_product_categories` WHERE `product_id` = :id";
                $statement = $modx->prepare($sql);
                if ($statement->execute(['id' => $id])) {
                    $result = $statement->fetchAll(PDO::FETCH_COLUMN);
                    $parents = array_merge($parents, $result);
                }
            }
            $resourceConfigs = [];
            $configs = $modx->getIterator('ffConfiguration');
            foreach ($configs as $config) {
                if ($config->get('parents')) {
                    $configParents = explode(',', $config->get('parents'));
                    foreach ($configParents as $configParent) {
                        if (in_array($configParent, $parents)) {
                            $Indexing = new Indexing($modx, $config->toArray());
                            $Indexing->indexResource($resourceData);
                        }
                    }
                } else {
                    $Indexing = new Indexing($modx, $config->toArray());
                    $Indexing->indexResource($resource->toArray());
                }
            }
        }
        break;

    case 'OnResourceDelete':
        Indexing::removeResourceIndex($modx, $id);
        break;

    case 'OnLoadWebDocument':
        $jpPath = $modx->getOption('ff_js_path', '', 'assets/components/flatfilters/js/web/flatfilters.js');
        $modx->regClientScript("<script type=\"module\" src=\"{$jpPath}\"></script>", 1);
        break;

    case 'OnHandleRequest':
        $basePath = $modx->getOption('base_path');
        $jsConfigPath = $modx->getOption('ff_js_config_path', '', './flatfilters.inc.js');
        $cookies = $_COOKIE['FlatFilters'] ? json_decode($_COOKIE['FlatFilters'],1) : [];
        $data = [
            'ffJsConfigPath' => $jsConfigPath
        ];
        $data = array_merge($cookies, $data);
        setcookie('FlatFilters', json_encode($data), 0, '/');
        break;
}