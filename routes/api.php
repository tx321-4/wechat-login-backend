<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/getQrcode','IndexController@getQrcode');
Route::get('/login', 'IndexController@login');
Route::get('/autoLogin', 'IndexController@autoLogin')->middleware('auth.test');
Route::get('/getUcenter', 'IndexController@getUcenter')->middleware('auth.test');
Route::match(['get','post'],'/notify', 'IndexController@notify');