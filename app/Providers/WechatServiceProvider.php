<?php

namespace App\Providers;

use EasyWeChat\OfficialAccount\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class WechatServiceProvider extends ServiceProvider implements DeferrableProvider
{
  /**
   * Register services.
   *
   * @return void
   */
  public function register()
  {
    $this->app->singleton(Application::class, function ($app) {
      return \EasyWeChat\Factory::officialAccount([
        'app_id' => env('WECHAT_APPID'),
        'secret' => env('WECHAT_APPSECRET')
      ]);
    });
    $this->app->singleton(\Redis::class, function ($app) {
      return \Symfony\Component\Cache\Adapter\RedisAdapter::createConnection('redis://localhost');
    });
  }
  public function provides()
  {
    return [Application::class, \Redis::class];
  }
  /**
   * Bootstrap services.
   *
   * @return void
   */
  public function boot()
  {
    //
  }
}
