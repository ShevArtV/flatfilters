<?php

require_once MODX_CORE_PATH . 'components/flatfilters/handlers/indexing.class.php';

class FlatFiltersConfigurationIndexingProcessor extends modProcessor
{

    public function initialize()
    {
        $this->tablePrefix = $this->modx->getOption('table_prefix', '', 'modx_');
        $this->ff = $this->modx->getService('flatfilters', 'Flatfilters', MODX_CORE_PATH . 'components/flatfilters/');;
        $this->modx->lexicon->load('flatfilters:default');

        return parent::initialize();
    }

    public function process()
    {
        $total = 0;
        $offset = 0;
        $properties = $this->getProperties();

        if ($config = $this->modx->getObject('ffConfiguration', $properties['id'])) {
            $configData = $config->toArray();
            $configData['filters'] = json_decode($configData['filters'], 1);
            $configData['default_filters'] = json_decode($configData['default_filters'], 1);
            if ($configData['offset'] > 0 && $configData['total'] > 0 && $configData['offset'] === $configData['total']) {
                $configData['offset'] = 0;

                $className = "ffIndex{$configData['id']}";
                $tableName = $this->modx->getTableName($className);
                $sql = "TRUNCATE TABLE {$tableName}";
                $this->modx->exec($sql);

                $tableName = $this->modx->getTableName('ffConfigResource');
                $sql = "DELETE FROM {$tableName} WHERE config_id = {$configData['id']}";
                $this->modx->exec($sql);
            }

            if(!$Indexing = $this->ff->loadClass($configData, 'indexing')){
                return $this->failure("Ошибка получения класса индексации.");
            }
            $result = $Indexing->indexConfig();
            $offset = $result['offset'];
            $total = $result['total'];
            $config->fromArray($result);
            $config->save();
        }

        $percent = $total ? round(($offset / $total) * 100) . '%' : '0%';

        return $this->success('', [
            'id' => $properties['id'],
            'finished' => $offset >= $total,
            'percent' => $percent,
            'action' => 'mgr/configuration/indexing'
        ]);
    }
}

return 'FlatFiltersConfigurationIndexingProcessor';