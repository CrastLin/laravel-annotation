<?php

return [
    // 控制器目录
    'controller_base' => 'app/Http/Controllers',
    // 根命名空间
    'controller_namespace' => 'App\Http\Controllers',
    // 需要扫描的模块目录名称
    'modules' => ['Admin'],
    // 生成文件根目录
    'annotation_path' => 'data/',
    // 是否开启自动生成（建议debug模式下开启）请求时将自动创建新增加的注解到路由表
    'auto_create_case' => env('APP_DEBUG'),
    // 请求时自动创建节点
    'auto_create_node' => env('ANNOTATION_AUTO_CRATE_NODE', false),
];