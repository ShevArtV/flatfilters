<?php
include_once MODX_CORE_PATH . 'components/flatfilters/handlers/indexing.class.php';
$FF = $modx->getService('flatfilters', 'Flatfilters', MODX_CORE_PATH . 'components/flatfilters/');

switch ($modx->event->name) {
    case 'OnGetFormParams':
        if (in_array($presetName, $FF->presets)) {
            $modx->event->returnedValues = $FF->getFormParams();
        }
        break;

    case 'OnDocFormSave':
    case 'OnResourceUndelete':
        $FF->removeResourceIndex($id);
        $resourceData = $resource->toArray();
        if ($resourceData['published'] && !$resourceData['deleted']) {
            $FF->indexing($resourceData);
        }
        break;

    case 'OnResourceDelete':
        $FF->removeResourceIndex($id);
        break;

    case 'OnLoadWebDocument':
        $FF->regClientScripts($modx->resource->get('template'));
        break;

    case 'OnHandleRequest':
        $FF->setCookie();
        break;
}