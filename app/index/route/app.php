<?php
use think\facade\Route;

Route::get('install', 'Install/index');
Route::post('install', 'Install/init');
