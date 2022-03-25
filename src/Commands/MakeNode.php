<?php

namespace Crastlin\LaravelAnnotation\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;
use Crastlin\LaravelAnnotation\Annotation\Node;

class MakeNode extends Command
{
    protected $signature = 'make:node {module?}';

    protected $description = '根据控制器注解创建权限节点';

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
        foreach ($moduleList as $module):
            $this->info("开始创建 <模块：{$module}> 菜单和权限节点...");
            $module = ucfirst($module);
            $scanBase = !empty($config['controller_base']) ? rtrim($config['controller_base'], '/') : 'app/Http/Controllers';
            $scanPath = base_path($scanBase . '/' . $module);
            if (is_dir($scanPath)) {
                $namespaceBase = !empty($config['controller_namespace']) ? rtrim($config['controller_namespace'], '\\') : 'App\\Http\\Controllers';
                $namespace = $namespaceBase . '\\' . $module;
                try {
                    Node::runCreateWithAnnotation($scanPath, $namespace);
                    $this->info("创建 <模块：{$module}> 菜单和权限节点成功");
                } catch (\Throwable $exception) {
                    $this->warn("创建 <模块：{$module}> 失败" . $exception->getMessage());
                }

            } else {
                $this->error('创建失败，模块目录不存在');
            }
        endforeach;
    }
}