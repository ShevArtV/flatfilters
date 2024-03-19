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
        if(!$this->modx->query("SHOW TABLES LIKE '{$tableName}'")->rowCount()){
            $this->createTable();
            $this->modxBuilder->writeSchema(true, true, false);
            $this->modxBuilder->parseSchema();
        }
        $oldFilters = json_decode($this->object->get('filters'), 1);
        $oldParents = $this->object->get('parents') ? explode(',', $this->object->get('parents')) : [];
        $properties = $this->getProperties();
        $parents = $properties['parents'] ? explode(',', $properties['parents']) : [];
        $filters = json_decode($properties['filters'], 1);
        $defaults = [
            'varchar' => "`#fieldName#` varchar(50) CHARACTER SET utf8mb4 DEFAULT NULL",
            'int' => "`#fieldName#` INT(10) UNSIGNED DEFAULT NULL",
            'decimal' => "`#fieldName#` DECIMAL(12,2) DEFAULT NULL",
            'timestamp' => "`#fieldName#` INT(10) NULL DEFAULT NULL",
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
            $addSQL = [];
            $addKeySQL = [];
            foreach ($addFields as $i => $key) {
                $addSQL[] = "ADD " . str_replace('#fieldName#', $key, $defaults[$filters[$key]['field_type']]);
                $addKeySQL[] = " ADD KEY `{$key}` (`{$key}`)";
            }
            $this->modx->exec($sql . implode(', ', $addSQL));
            $this->modx->exec($sql . implode(', ', $addKeySQL));
        }
        if ($dropFields) {
            $dropSQL = [];
            foreach ($dropFields as $key) {
                $dropSQL[] = "DROP COLUMN {$key}";
            }
            $this->modx->exec($sql . implode(', ', $dropSQL));
        }

        if ($dropFields || $addFields || $changeFields || count($changeParents) || count($parents) !== count($oldParents)) {
            $this->modxBuilder->writeSchema(true, true, false);
            $this->modxBuilder->parseSchema();
        }
    }

    private function createTable(){

        $fields = [];
        $keys = [];
        $tableName = "{$this->tablePrefix}ff_indexes_{$this->object->get('id')}";
        $filters = json_decode($this->object->get('filters'),1);
        $defaults = [
            'varchar' => "`#fieldName#` VARCHAR(50) CHARACTER SET utf8mb4 DEFAULT NULL",
            'int' => "`#fieldName#` INT(10) UNSIGNED DEFAULT NULL",
            'decimal' => " `#fieldName#` DECIMAL(12,2) DEFAULT NULL",
            'timestamp' => "`#fieldName#` INT(10) NULL DEFAULT NULL",
            'tinyint' => "`#fieldName#` TINYINT(1) UNSIGNED DEFAULT NULL",
        ];

        $sql = "CREATE TABLE `{$tableName}` (
              `id` int(10) UNSIGNED NOT NULL,
              `rid` int(10) UNSIGNED NOT NULL,
              #fields#
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='ffIndex{$this->object->get('id')}'";

        $addKeys = "ALTER TABLE `{$tableName}`
                    ADD PRIMARY KEY (`id`),
                    ADD KEY `rid` (`rid`),
                    #keys#";

        $modify = "ALTER TABLE `{$tableName}` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=349326";

        foreach($filters as $key => $data){
            $fields[] = str_replace('#fieldName#', $key,$defaults[$data['field_type']]);
            $keys[] = "ADD KEY `{$key}` (`{$key}`)";
        }
        $fields = implode(','.PHP_EOL, $fields);
        $sql = str_replace('#fields#', $fields, $sql);

        $keys = implode(','.PHP_EOL, $keys);
        $addKeys = str_replace('#keys#', $keys, $addKeys);

        $this->modx->exec($sql);
        $this->modx->exec($addKeys);
        $this->modx->exec($modify);
    }

    private function prepareDefaultFilters(){
        $properties = $this->getProperties();
        $filters = json_decode($properties['filters'], 1);
        $defaultFilters = [];
        foreach($filters as $key => $data){
            if (!isset($data['default_value'])) {
                continue;
            }
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