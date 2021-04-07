<?php
header('Content-Type: text/html;charset=utf-8');
header('Access-Control-Allow-Origin:http://localhost:8080'); // *代表允许任何网址请求
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin'); // 设置允许自定义请求头的字段
require './vendor/autoload.php';
$type = $_GET['type'];
$config['app_id'] = '';
$config['secret'] = '';
$config['expire'] = 60;
function getKey()
{
  return time() . rand(0, 10000);
}
function cacheKey()
{
  global $config;
  $cache = getCache();
  $key = getKey();
  return $cache->set($key, -1, ['nx', 'ex' => $config['expire']]) ? $key : cacheKey();
}
function response($code, $msg, $data = [])
{
  exit(json_encode(compact('code', 'msg', 'data')));
}
function getCache()
{
  static $cache;
  return $cache ?? $cache = \Symfony\Component\Cache\Adapter\RedisAdapter::createConnection('redis://localhost');
}
function getWechatApp()
{
  global $config;
  static $app;
  return $app ?? $app = \EasyWeChat\Factory::officialAccount([
    'app_id' => $config['app_id'],
    'secret' => $config['secret'],
  ]);
}

function responseMsg()
{
  $postStr = file_get_contents("php://input");
  if (!$postStr) {
    exit();
  }
  $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
  $msgType = trim($postObj->MsgType);
  if ('event' == $msgType) {
    echo receiveEvent($postObj);
  }
}
function receiveEvent($postObj)
{
  if (in_array($postObj->Event, ['subscribe', 'SCAN'])) {
    $key = str_replace('qrscene_', '', $postObj->EventKey);
    if (empty($key)) {
      return '';
    }
    $responseText = loginInit($key, trim($postObj->FromUserName)) ? '登陆成功' : '系统繁忙，请稍后再试';
    return transmitText($postObj, $responseText);
  }
  return '';
}

function loginInit($key, $openId)
{
  $cache = getCache();
  if (!$cache->exists($key)) {
    return false;
  }
  $con = getCon();
  $result = mysqli_query($con, "select id from users where openid='$openId'");
  if (!$result) {
    try {
      $app = getWechatApp();
      $user = $app->user->get($openId);
    } catch (Exception $e) {
      return false;
    }
    $nickname = $user['nickname'];
    $avatarUrl = $user['headimgurl'];
    $result = mysqli_query($con, "insert into users(nickname, avatar_url, openid')values ('$nickname','$avatarUrl','$openId')");
    if (!$result) {
      return false;
    }
    $uid = mysqli_insert_id();
  }
  $user = mysqli_fetch_assoc($result);
  $uid = $user['id'];
  return $cache->set($key, $uid);
}
function getCon()
{
  static $con;
  return $con ?? $con = mysqli_connect('localhost', '', '', '');
}
function transmitText($postObj, $content)
{
  $xmlTpl = "<xml>
  <ToUserName><![CDATA[%s]]></ToUserName>
  <FromUserName><![CDATA[%s]]></FromUserName>
  <CreateTime>%s</CreateTime>
  <MsgType><![CDATA[text]]></MsgType>
  <Content><![CDATA[%s]]></Content>
</xml>";
  return sprintf($xmlTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $content);
}


if ('getQrcode' == $type) {
  try {
    $key = cacheKey();
    $app = getWechatApp();
    $result = $app->qrcode->temporary($key, $config['expire']);
    $url = $app->qrcode->url($result['ticket']);
    $data = ['key' => $key, 'url' => $url];
    response(0, 'success', $data);
  } catch (Exception $e) {
    response(500, '系统繁忙，请稍后再试');
  }
}

if ('login' == $type) {
  $key = $_GET['key'];
  $cache = getCache();
  $userId = $cache->get($key);
  if (-1 == $userId) {
    response(-1, 'not login');
  } elseif (!$userId) {
    response(1, '二维码已经过期');
  } else {
    session_start();
    $_SESSION['userId'] = $userId;
    response(0, '已登陆');
  }
}

if ('notify' == $type) {
  // echo $_GET['echostr']; //接口配置信息 验证
  responseMsg();
}
