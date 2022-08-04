<?php


namespace Crastlin\LaravelAnnotation\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Crastlin\LaravelAnnotation\Annotation\Route;

class AnnotationRoute extends Command
{
    protected $signature = 'annotation:route {module?}';

    protected $description = '根据控制器注解创建路由表';

    protected function getArguments()
    {
        return [[
            'module', InputArgument::OPTIONAL, ''
        ]];
    }

    function handle()
    {
        $module = $this->argument('module');
        $config = config('annotation');
        $moduleList = $module ? [$module] : ($config['modules'] ?? []);
        if (empty($moduleList)) {
            $this->error('创建失败，请输入模块名称，或在annotation配置中指定模块名称');
            return;
        }

        $moduleBasePath = !empty($config['controller_base']) ? rtrim($config['controller_base'], '/') : 'app/Http/Controllers';
        $namespace = !empty($config['controller_namespace']) ? rtrim($config['controller_namespace'], '\\') : 'App\\Http\\Controllers';
        $moduleBasePath = base_path($moduleBasePath);
        $filePath = !empty($config['annotation_path']) ? rtrim($config['annotation_path'], '/') : 'data';
        $routeBasePath = base_path($filePath . '/routes');
        $this->info("开始创建路由文件...");
        \Crastlin\LaravelAnnotation\Annotation\Route::autoBuildRouteMapping($config['modules'], $moduleBasePath, $namespace, $routeBasePath, $config['root_group'] ?? [], !empty($config['auto_create_node']));
        $this->info('所有路由创建成功');
    }
}