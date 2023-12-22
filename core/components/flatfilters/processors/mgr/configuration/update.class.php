<?php

require_once MODX_CORE_PATH . 'components/flatfilters/tools/modxbuilder.class.php';


class FlatFiltersConfigurationUpdateProcessor extends modObjectUpdateProcessor
{
    public $classKey = 'ffConfiguration';

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize()
    {
        $corePath = MODX_CORE_PATH . 'components/flatfilters/';

        $this->config = array(
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'templatesPath' => $corePath . 'templates/',
        );

        $this->signs = [
            'eq' => '=',
            'gt' => '>',
            'gteq' => '>=',
            'lt' => '<',
            'lteq' => '<=',
            'in' => 'IN',
            'between' => 'BETWEEN',
        ];

        $this->modxBuilder = new modxBuilder($this->modx);

        $this->tablePrefix = $this->modx->getOption('table_prefix', '', 'modx_');
        $this->modx->addPackage('flatfilters', $this->config['modelPath']);

        $this->modx->lexicon->load('flatfilters:default');

        return parent::initialize();
    }

    /**
     * @return boolean
     */
    public function beforeSet()
    {
        $_SESSION['flatfilters'] = [];
        $this->updateTable();
        $this->prepareDefaultFilters();
        return !$this->hasErrors();
    }

    private function updateTable()
    {
        $tableName = "{$this->tablePrefix}ff_indexes_{$this->object->get('id')}";
        $oldFilters = json_decode($this->object->get('filters'), 1);
        $oldParents = $this->object->get('parents') ? explode(',', $this->object->get('parents')) : [];
        $properties = $this->getProperties();
        $parents = $properties['parents'] ? explode(',', $properties['parents']) : [];
        $filters = json_decode($properties['filters'], 1);
        $defaults = [
            'varchar' => "`#fieldName#` varchar(50) CHARACTER SET utf8mb4 DEFAULT NULL",
            'int' => "`#fieldName#` INT(10) UNSIGNED DEFAULT NULL",
            'decimal' => "`#fieldName#` DECIMAL(12,2) DEFAULT NULL",
            'timestamp' => "`#fieldName#` TIMESTAMP(6) NULL DEFAULT NULL",
            'tinyint' => "`#fieldName#` TINYINT(1) UNSIGNED DEFAULT NULL",
        ];
        $sql = "ALTER TABLE {$tableName} ";
        $addFields = array_diff(array_keys($filters), array_keys($oldFilters));
        $dropFields = array_diff(array_keys($oldFilters), array_keys($filters));
        $changeFields = [];
        $changeParents = array_diff($parents, $oldParents);
        foreach ($filters as $key => $data) {
            if ($oldFilters[$key] && $data['field_type'] !== $oldFilters[$key]['field_type']) {
                $changeFields[] = "MODIFY COLUMN " . str_replace('#fieldName#', $key, $defaults[$data['field_type']]);
            }
        }
        if ($changeFields) {
            $changeFields = implode(',' . PHP_EOL, $changeFields);
            $this->modx->exec($sql . $changeFields);
        }
        if ($addFields) {
            $addSQL = $sql;
            $addKeySQL = $sql;
            foreach ($addFields as $key) {
                $addSQL .= "ADD " . str_replace('#fieldName#', $key, $defaults[$filters[$key]['field_type']]);
                $addKeySQL .= "ADD KEY `{$key}` (`{$key}`)";
            }
            $this->modx->exec($addSQL);
            $this->modx->exec($addKeySQL);
        }
        if ($dropFields) {
            $dropSQL = $sql;
            foreach ($dropFields as $key) {
                $dropSQL .= "DROP COLUMN {$key}";
            }
            $this->modx->exec($dropSQL);
        }
        if ($dropFields || $addFields || $changeFields || count($changeParents) || count($parents) !== count($oldParents)) {
            $this->modxBuilder->writeSchema(true, true, false);
            $this->modxBuilder->parseSchema();
        }
    }

    private function prepareDefaultFilters(){
        $properties = $this->getProperties();
        $filters = json_decode($properties['filters'], 1);
        $defaultFilters = [];
        foreach($filters as $key => $data){
            if(!$data['default_value']) continue;
            $defaultFilters[$key] = [
                'filter_type' => $data['filter_type'],
                'value' => $data['default_value'],
                'sign' => $this->signs[$data['sign']] ?: 'eq'
            ];
        }
        //$this->modx->log(1, print_r($defaultFilters,1));
        $properties['default_filters'] = json_encode($defaultFilters);
        $this->setProperties($properties);
    }

    /**
     * @return boolean
     */
    public function beforeSave() {
        return !$this->hasErrors();
    }

    public function cleanup()
    {
        return $this->success($this->modx->lexicon('ff_msg_success_update'), $this->object);
    }
}

return 'FlatFiltersConfigurationUpdateProcessor';