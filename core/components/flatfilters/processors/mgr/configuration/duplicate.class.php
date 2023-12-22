<?php

class FlatFiltersConfigurationDuplicateProcessor extends modObjectDuplicateProcessor
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
        $this->modx->addPackage('flatfilters', $this->config['modelPath']);

        return parent::initialize();
    }

    /**
     * @return boolean
     */
    public function beforeSave()
    {
        $this->newObject->set('offset',0);
        $this->newObject->set('total',0);
        return !$this->hasErrors();
    }

    public function cleanup() {
        $url = explode('?', $_SERVER['HTTP_REFERER']);
        $this->newObject->set('action', 'mgr/configuration/duplicate');
        $this->newObject->set('url', "{$url[0]}?a=configuration/manage&namespace=flatfilters&id={$this->newObject->get('id')}");

        return $this->success('',$this->newObject);
    }
}

return 'FlatFiltersConfigurationDuplicateProcessor';