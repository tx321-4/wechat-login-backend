<?php

namespace App\Http\Controllers;

use EasyWeChat\OfficialAccount\Application;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Request;

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
      return response()->api(0,'success',$data);
    } catch (\Exception $e) {
      return response()->api(500,'系统繁忙，请稍后再试');
    }
  }
  public function login(\Redis $cache, Request $request)
  {
    $key = $request->input('key');
    $userId = $cache->get($key);
    if(-1 == $userId){
      return response()->api(-1, 'not login');
    }elseif(!$userId){
      return response()->api(1, '二维码已经过期');
    }else{
      Auth::loginUsingId($userId);
      return response()->api(0, '已登陆');
      
    }
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
