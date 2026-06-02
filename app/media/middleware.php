<?php
// 全局中间件定义文件
return [
    // 全局请求缓存
     \think\middleware\CheckRequestCache::class,
    // 多语言加载
     \think\middleware\LoadLangPack::class,
    // Session初始化
     \think\middleware\SessionInit::class,
    \app\media\middleware\Csrf::class,
    'MediaAuth' => \app\media\middleware\MediaAuth::class,
    'RealIp' => \app\media\middleware\RealIp::class,
    'CorsMiddleware' => \app\media\middleware\SystemCheck::class,
];
