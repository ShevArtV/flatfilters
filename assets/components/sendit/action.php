<?php

$headers = array_change_key_case(getallheaders());
$token = $headers['x-sitoken'];
$cookie = $_COOKIE['SendIt'] ? json_decode($_COOKIE['SendIt'],1): [];
$res = [
    'success' => false,
    'msg' => '',
    'data' => []
];

if (!$token || $token !== $cookie['sitoken']) die(json_encode($res));
if (!$cookie['sitrusted']) die(json_encode($res));

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/index.php';
require_once MODX_CORE_PATH . 'components/sendit/model/sendit/sendit.class.php';

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$preset = $headers['x-sipreset'];
$formName = $headers['x-siform'];
$action = $headers['x-siaction'];
$event = $headers['x-sievent'];

$sendit = new SendIt($modx, (string)$preset, (string)$formName, (string)$event);

switch ($action) {
    case 'send':
        $res = $sendit->process();
        break;

    case 'preset':
        $res = $sendit->getPreset();
        break;
    case 'upload':
        $filename = $headers['x-upload-id'];
        $portionSize = $headers['x-portion-size'];
        $from = $headers['x-position-from'];
        $filesize = $headers['x-file-size'];
        $currentIndex = $headers['x-current-index'];
        $loaded = $headers['x-loaded'];
        $validate['success'] = true;
        if(!$from){
            $validate = $sendit->validateFile($filename, $filesize, (int)$loaded);
        }
        if($validate['success']){
            $res = $sendit->uploadFile($filename, $filesize, $portionSize, (float)$from, (int)$currentIndex);
        }else{
            $validate['data']['nextIndex'] = (int)$currentIndex + 1;
            $res = $sendit->error('', $validate['data']);
        }
        break;

    case 'removeDir':
        $res = $sendit->success('', []);
        $uploaddir = $sendit->basePath . $sendit->uploaddir . session_id() . '/';
        if (strpos($uploaddir, 'assets/') !== false) {
            $sendit->removeDir($uploaddir);
        }
        break;
    case 'removeFile':
        $path = MODX_BASE_PATH . $_POST['path'];
        if(file_exists($path) && strpos($path, session_id()) !== false){
            unlink($path);
        }
        $res = $sendit->success('Файл удалён.', ['path' => $_POST['path']]);
        break;
}

if(is_array($res)){
    $res = json_encode($res);
}else{
    $res = json_encode(['result' => $res]);
}
die($res);