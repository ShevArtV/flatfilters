<?php

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
        if ($resource->get('published') && !$resource->get('deleted')) {
            $FF->indexingDocument($resource);
        }
        break;

    case 'OnUserSave':
        if($user){
            $FF->removeResourceIndex($user->get('id'));
            $FF->indexingUser($user);
        }
        break;

    case 'OnUserRemove':
        if($user){
            $FF->removeResourceIndex($user->get('id'));
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