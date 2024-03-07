<?php

define('MODX_API_MODE', true);
require_once dirname(__FILE__, 4) . '/index.php';
require_once MODX_CORE_PATH . 'components/sendit/model/sendit/sendit.class.php';

$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);

$headers = array_change_key_case(getallheaders());
$token = $headers['x-sitoken'];
$cookie = $_COOKIE['SendIt'] ? json_decode($_COOKIE['SendIt'], 1) : [];

$preset = $headers['x-sipreset'];
$formName = $headers['x-siform'];
$action = $headers['x-siaction'];
$event = $headers['x-sievent'];

$sendit = new SendIt($modx, (string)$preset, (string)$formName, (string)$event);

$res = [
    'success' => false,
    'msg' => '',
    'data' => []
];

if (!isset($_SESSION['sitoken']) || !$token || $token !== $_SESSION['sitoken']) die(json_encode($sendit->error('si_msg_token_err')));
if (!$cookie['sitrusted']) die(json_encode($sendit->error('si_msg_trusted_err')));

switch ($action) {
    case 'validate_files':
        $filesData = isset($_POST['filesData']) ? json_decode($_POST['filesData'], JSON_UNESCAPED_UNICODE) : [];
        $fileList = !empty($_POST['fileList']) ? explode(',', $_POST['fileList']) : [];
        if(isset($_POST['params'])){
            $params = json_decode($_POST['params'], true);
            $sendit->params = array_merge($sendit->params, $params);
        }
        $res = $sendit->validateFiles($filesData, count($fileList));
        break;
    case 'uploadChunk':
        $content = file_get_contents('php://input');
        $res = $sendit->uploadChunk($content, $headers);
        break;
    case 'send':
        $res = $sendit->process();
        break;

    case 'removeDir':
        $res = $sendit->success('', []);
        $uploaddir = $sendit->uploaddir . session_id() . '/';
        if (strpos($uploaddir, MODX_ASSETS_PATH) !== false) {
            $sendit->removeDir($uploaddir);
        }
        break;
    case 'removeFile':
        $path = MODX_BASE_PATH . $_POST['path'];
        if(strpos($path, session_id()) === false){
            $res = $sendit->error('si_msg_file_remove_session_err', [], ['filename' => basename($_POST['path'])]);
        }else{
            if(file_exists($path)){
                unlink($path);
            }
            $res = $sendit->success('si_msg_file_remove_success', ['filename' => basename($_POST['path']), 'path' => $_POST['path']]);
        }
        break;
}

if (is_array($res)) {
    $res = json_encode($res);
} else {
    $res = json_encode(['result' => $res]);
}
die($res);