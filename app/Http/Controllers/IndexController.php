<?php

namespace App\Http\Controllers;

use App\ApiUser;
use EasyWeChat\OfficialAccount\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
// use Request;

class IndexController extends BaseController
{
  protected $expire = 60;
  public function getQrcode(Application $app, \Redis $redis)
  {

    try {
      $key = $this->cacheKey($redis);
      $result = $app->qrcode->temporary($key, $this->expire);
      $url = $app->qrcode->url($result['ticket']);
      $data = ['key' => $key, 'url' => $url];
      return response()->api(0, 'success', $data);
    } catch (\Exception $e) {
      return response()->api(500, '系统繁忙，请稍后再试');
    }
  }
  public function login(\Redis $cache, Request $request)
  {

    $key = $request->input('key');
    //$cache->del(null);
    $userId = $cache->get($key);
    // dd($userId);
    if (-1 == $userId) {
      return response()->api(-1, 'not login');
    } elseif (!$userId) {
      return response()->api(1, '二维码已经过期');
    } else {
      Auth::loginUsingId($userId);
      return response()->api(0, '已登陆');
    }
  }
  public function notify()
  { 
    //echo $_GET['echostr']; //接口配置信息 验证
 
     $this->responseMsg();
  }
  public function autoLogin()
  {
      $user = Auth::user();
      return response()->api(0, 'success', [
          'userInfo' => [
              'nickName' => $user->nick_name,
              'avatarUrl' => $user->avatar_url
          ]
      ]);
  }
  public function getUcenter()
  {

  }
  private function responseMsg()
  {
    $postStr = file_get_contents("php://input");
    if (!$postStr) {
      exit();
    }
    $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
    $msgType = trim($postObj->MsgType);
    if ('event' == $msgType) {
      echo $this->receiveEvent($postObj);
    }
  }
  private function receiveEvent($postObj)
  {
    if (in_array($postObj->Event, ['subscribe', 'SCAN'])) {
      $key = str_replace('qrscene_', '', $postObj->EventKey);
      if (empty($key)) {
        return '';
      }
      $responseText = $this->loginInit($key, trim($postObj->FromUserName)) ? '登陆成功' : '系统繁忙，请稍后再试';
      return $this->transmitText($postObj, $responseText);
    }
    return '';
  }
  private function loginInit($key, $openId)
{
  $cache = app()->make(\Redis::class);
  if (!$cache->exists($key)) {
    return false;
  }

  $user = ApiUser::where('openid', $openId)->first();
  if (!$user) {
    try {
      $app = app()->make(Application::class);
      $wechatUser = $app->user->get($openId);
    } catch (Exception $e) {
      return false;
    }
    $user = new ApiUser();
    $user->nick_name = $wechatUser['nickname'];
    $user->avatar_url = $wechatUser['headimgurl'];
    $user->openid = $openId;
    if (!$user->save()) {
      return false;
    }
  }
  $uid = $user->id;
  return $cache->set($key, $uid, 60);
}
  private function transmitText($postObj, $content)
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

  private function getKey()
  {
    return \Illuminate\Support\Str::random();
  }
  private function cacheKey(\Redis $cache)
  {
    $key = $this->getKey();
    return $cache->set($key, -1, ['nx', 'ex' => $this->expire]) ? $key : $this->cacheKey($cache);
  }
}
