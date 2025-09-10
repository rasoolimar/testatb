<?php
require __DIR__ . '/config.php';
ini_set('display_errors','0'); error_reporting(E_ALL);
function atb_log($m){ @file_put_contents(__DIR__.'/logs/atb_log.txt','['.date('c').'] '.$m."\n",FILE_APPEND); }
set_error_handler(function($no,$str,$file,$line){ atb_log("PHP ERROR $no: $str in $file:$line"); return false; });
register_shutdown_function(function(){
  $e=error_get_last();
  if($e && in_array($e['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])){
    atb_log("FATAL: {$e['message']} in {$e['file']}:{$e['line']}");
    if(!headers_sent()){ header('Content-Type:text/plain; charset=UTF-8'); http_response_code(200); }
    echo 'OK';
  }
});
$expected = defined('TG_SECRET_TOKEN') ? TG_SECRET_TOKEN : null;
$got = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? null;
if($expected && $got !== $expected){ atb_log("BAD SECRET: got=".($got??'(null)')." expected=".$expected); http_response_code(403); echo 'forbidden'; exit; }
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){ echo 'OK'; exit; }
$raw = file_get_contents('php://input'); atb_log("RAW LEN=".strlen($raw??'')." UA=".($_SERVER['HTTP_USER_AGENT']??''));
$update = json_decode($raw?:'[]', true);
if(JSON_ERROR_NONE!==json_last_error()){ atb_log("JSON ERROR: ".json_last_error_msg()); echo 'OK'; exit; }
try{ $GLOBALS['update'] = $update; require __DIR__ . '/src/bot.php'; } catch(Throwable $t){ atb_log("UNCAUGHT: ".$t->getMessage().' in '.$t->getFile().':'.$t->getLine()); }
if(!headers_sent()){ header('Content-Type:text/plain; charset=UTF-8'); http_response_code(200); }
echo 'OK';
