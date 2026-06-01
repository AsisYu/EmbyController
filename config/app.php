<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

$appHost = env('APP_HOST', '');

if (strpos($appHost, 'http://') === false && strpos($appHost, 'https://') === false) {
    $appHost = 'http://' . $appHost;
}

if (substr($appHost, -1) == '/') {
    $appHost = substr($appHost, 0, -1);
}

if (strpos($appHost, '/media') !== false) {
    $appHost = substr($appHost, 0, strpos($appHost, '/media'));
}

return [
    // 应用地址
    'app_host'         => $appHost,
    // 应用的命名空间
    'app_namespace'    => '',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用
    'default_app'      => 'index',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（自动多应用模式有效）
    'app_map'          => [],
    // 域名绑定（自动多应用模式有效）
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（自动多应用模式有效）
    'deny_app_list'    => [],

    // 异常页面的模板文件
    'exception_tmpl'   => __DIR__ . '/../view/error.tpl',

    // 错误显示信息,非调试模式有效
    'error_message'    => '页面错误！请稍后再试～ ',
    // 显示错误信息
    'show_error_msg'   => false,

    'app_name' => env('APP_NAME', '算艺轩'),
];
