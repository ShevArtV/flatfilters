<?php

require_once MODX_CORE_PATH . 'components/flatfilters/tools/modxbuilder.class.php';

class FlatFiltersConfigurationRemoveProcessor extends modObjectRemoveProcessor
{
    public $classKey = 'ffConfiguration';

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize() {
        $corePath = MODX_CORE_PATH . 'components/flatfilters/';

        $this->config = array(
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'templatesPath' => $corePath . 'templates/',
        );

        $this->modxBuilder = new modxBuilder($this->modx);

        $this->tablePrefix = $this->modx->getOption('table_prefix', '', 'modx_');
        $this->modx->addPackage('flatfilters', $this->config['modelPath']);

        $this->modx->lexicon->load('flatfilters:default');

        return parent::initialize();
    }

    /**
     * Can contain post-removal logic.
     * @return bool
     */
    public function beforeRemove() {
        $this->dropTable();
        $this->removeConfigResources();
        $this->modxBuilder->writeSchema(true,true,false);
        $this->modxBuilder->parseSchema();
        return !$this->hasErrors();
    }

    private function dropTable(){
        $tableName = $this->modx->getTableName("ffIndex{$this->object->get('id')}");
        $sql = "DROP TABLE {$tableName}";
        $this->modx->exec($sql);    }

    private function removeConfigResources(){
        $tableName = $this->modx->getTableName("ffConfigResource");
        $sql = "DELETE FROM {$tableName} WHERE `config_id` = {$this->object->get('id')}";
        $this->modx->exec($sql);
    }

    public function cleanup() {
        return $this->success($this->modx->lexicon('ff_msg_success_remove'), $this->object);
    }
}

return 'FlatFiltersConfigurationRemoveProcessor';