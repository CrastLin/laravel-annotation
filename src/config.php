<?php

return [
    /*
     | 控制器根目录，通用的注解自动从这个目录扫描
     | The controller root directory
     | from which common annotations are automatically scanned
     */
    'controller_base' => 'app/Http/Controllers',
    /*
     | 控制器根命名空间
     | Controller root namespace
     */
    'controller_namespace' => 'App\Http\Controllers',
    /*
     | 是否自动扫描模块，当设置为true时则扫描控制器目录下所有模块
     | Whether to automatically scan for modules.
     | When this parameter is set to True, all modules in the controller directory are scanned
     */
    'scan_modules_case' => env('ANNOTATION_AUTO_SCAN_MODULES', true),
    /*
     | 指定扫描的模块目录名称
     | If modules are set, only classes in the module directory are scanned
     */
    'modules' => [],
    /*
     | 生成路由文件根目录
     | Root directory of the route file
     */
    'annotation_path' => 'data/',
    /*
     | 是否开启自动生成（建议debug模式下开启）请求时将自动创建新增加的注解到路由表
     | Whether to enable automatic generation (debug mode is recommended)
     | The newly added annotations are automatically created to the routing table
     */
    'auto_create_case' => env('APP_DEBUG'),
    /*
     | 根路由分组配置
     | Configure the root route group
     | for example ['module1' => ['prefix' => 'route path prefix', 'namespace' => 'controller namespace（no defined then use the module1）', 'middleware' => 'middleware set name（ the name of $routeMiddleware in Http/Kernel.php）], 'module2' => ...]
     */
    'root_group' => [],
    /*
     | 请求时自动创建节点
     | Nodes are automatically created on request
     */
    'auto_create_node' => env('ANNOTATION_AUTO_CRATE_NODE', false),

    /*
     | 拦截器配置
     | interceptor config
     | Including all annotation configurations related to interception
     */
    'interceptor' => [
        // Distributed lock configuration
        'lock' => [
            // lock switch, on by default
            'case' => true,
            // Data of response when intercepted
            'response' => [],
            // Lock status validity period setting
            'expire' => 86400,
        ],
        // Parameter input verifier configuration
        'validate' => [
            // validator switch, on by default
            'case' => true,
        ],
    ],

];