<?php

class FlatFiltersConfigurationManageManagerController extends modExtraManagerController
{
    public function getLanguageTopics()
    {
        return ['flatfilters:default'];
    }

    public function getPageTitle()
    {
        return $this->modx->lexicon('ff_title_manage');
    }

    public function getItemData($id)
    {
        $output = [];
        $output['type'] = $_GET['type'];
        $tplPath = MODX_CORE_PATH . 'components/flatfilters/templates/mgr/';
        $tplFilter = '@INLINE ' . file_get_contents($tplPath . 'chunks/filter_table_row.tpl');

        if ($object = $this->modx->getObject('ffConfiguration', $id)) {
            $output = $object->toArray();
            $filters = json_decode($output['filters'],1);
            $filtersHtml = '';
            foreach ($filters as $filter){
                $filtersHtml .= $this->pdoTools->parseChunk($tplFilter,$filter);
            }
            $output['filters'] = $filters;
            $output['filtersHtml'] = $filtersHtml;
        }

        $output['show_parents_for'] = explode(',', $this->modx->getOption('ff_show_parents_panel_for', '', 'resources,products'));
        $output['show_groups_for'] = explode(',', $this->modx->getOption('ff_show_groups_panel_for', '', 'customers'));

        $defaultName = $this->modx->lexicon('mgr_ff_default_name');
        $output['title'] = $this->modx->lexicon('mgr_ff_item_title', ['name' => $output['name']?:$defaultName]);
        $output['back_url'] = $this->modx->getOption('manager_url') . '?a=getconfigurations&namespace=flatfilters';

        return $output;
    }

    public function loadCustomCssJs()
    {
        $FF = $this->modx->getService('flatfilters','FlatFilters', MODX_CORE_PATH . 'components/flatfilters/');
        $assetsBaseUrl = MODX_ASSETS_URL . 'components/flatfilters/';
        $assetsUrl = $assetsBaseUrl . 'js/';
        $tplPath = MODX_CORE_PATH . 'components/flatfilters/templates/mgr/';
        $this->pdoTools = $FF->pdoTools;
        $data = $this->getItemData((int)$_GET['id']);
        $data['types'] = $FF->types;
        $tpl_html = '@INLINE ' . file_get_contents($tplPath . 'configuration_manage.tpl');
        $template = $this->pdoTools->parseChunk($tpl_html, $data);
        $config = json_encode([
            'connector_url' => $assetsBaseUrl . 'connector.php',
            'template' => $template,
            'token' => $this->modx->user->getUserToken($this->modx->context->get('key')),
            'filters_keys' => $FF->getFieldsKeys($data['type']),
            'storage' => [
                'filters' => $data['filters'] ?: false,
                'parents' => $data['parents'],
                'groups' => $data['groups']
            ]
        ]);
        //$this->modx->log(1, print_r($FF->getFieldsKeys(),1));
        $this->addCss($assetsBaseUrl . 'css/mgr/main.min.css');
        $this->addCss($assetsBaseUrl . 'css/mgr/swal.min.css');
        $this->addJavascript($assetsBaseUrl . 'js/libs/bootstrap.bundle.min.js');
        $this->addJavascript($assetsBaseUrl . 'js/libs/sweetalert2.js');

        $this->addHtml(<<<HTML

<script type="module">
import Manage from "{$assetsUrl}/mgr/manage.min.js";
Ext.onReady(function(){     
    new Manage({$config});   
});
</script>
HTML
        );
    }

}