<?php

class FlatFiltersRenderChunkProcessor extends modProcessor
{

    public function initialize() {

        $this->pdo = $this->modx->getService('pdoTools');
        $this->ff = $this->modx->getService('flatfilters','FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
        $corePath = MODX_CORE_PATH . 'components/flatfilters/';
        $this->config = array(
            'corePath' => $corePath,
            'templatesPath' => $corePath . 'templates/mgr/',
        );

        return parent::initialize();
    }

    public function process(){
        $properties = $this->getProperties();
        $tpl_html = '@INLINE ' . file_get_contents($this->config['templatesPath'] . $properties['tpl']);
        $html = $this->pdo->parseChunk($tpl_html, $properties);

        return $this->success('', ['html' => $html]);
    }
}
return 'FlatFiltersRenderChunkProcessor';