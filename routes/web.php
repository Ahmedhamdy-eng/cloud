<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('/home', 'UploadController@index');
Route::get('/audio', 'UploadController@audio');
Route::post('/store', 'UploadController@store');
Route::get('download-zip', 'UploadController@downloadZip')->name('download-zip-file');
//Route::get('download-zip-wave', 'UploadController@downloadZipWave')->name('download-zip-wave');;

//Route::get('download','UploadController@download');