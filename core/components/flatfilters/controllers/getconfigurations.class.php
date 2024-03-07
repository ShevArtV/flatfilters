<?php

class FlatFiltersGetConfigurationsManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return array('flatfilters:default');
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('ff_title_list');
    }

    public function loadCustomCssJs()
    {
        $FF = $this->modx->getService('flatfilters', 'Flatfilters', MODX_CORE_PATH . 'components/flatfilters/');
        $otherProps = array(
            'processors_path' => MODX_CORE_PATH . 'components/flatfilters/processors/'
        );
        $scriptProps = array(
            'sort' => 'id',
            'dir' => 'ASC',
            'start' => 0,
            'limit' => $this->modx->getOption('default_per_page', '', 20)
        );
        $response = $this->modx->runProcessor('mgr/configuration/getlist', $scriptProps, $otherProps);
        $params = array_merge($scriptProps, json_decode($response->response,1));
        $params['page_total'] = ceil($params['total'] / $params['limit']);
        $params['last_on_page'] = $params['total'] < $params['limit'] ? $params['total'] : $params['limit'];
        $params['types'] = $FF->types;
        $assetsBaseUrl = MODX_ASSETS_URL . 'components/flatfilters/';
        $assetsUrl = $assetsBaseUrl . 'js/';
        $tplPath = MODX_CORE_PATH . 'components/flatfilters/templates/mgr/';
        $tpl_html = '@INLINE ' .  file_get_contents($tplPath. 'list.tpl');
        $template = $FF->pdoTools->parseChunk($tpl_html, $params);
        $config = json_encode([
            'connector_url' => $assetsBaseUrl . 'connector.php',
            'template' => $template,
            'token' => $this->modx->user->getUserToken($this->modx->context->get('key'))
        ]);


        $this->addCss($assetsBaseUrl . 'css/mgr/main.min.css');
        $this->addCss($assetsBaseUrl . 'css/mgr/swal.min.css');
        $this->addJavascript($assetsBaseUrl . 'js/libs/bootstrap.bundle.min.js');
        $this->addJavascript($assetsBaseUrl . 'js/libs/sweetalert2.js');

        $this->addHtml(<<<HTML

<script type="module">
import GetList from "{$assetsUrl}/mgr/getlist.min.js";
Ext.onReady(function(){     
    new GetList({$config});   
});
</script>
HTML
        );
    }
}