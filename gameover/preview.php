<?php
require_once __DIR__ . '/../app/bootstrap.php';
if(!SK_OFFLINE){http_response_code(404);exit;}
$gamePreview=true;
$gameResult=($_GET['type']??'')==='loss'?'LOSS':'WIN';
require __DIR__ . '/result.php';
