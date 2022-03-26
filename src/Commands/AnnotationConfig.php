<?php


namespace Crastlin\LaravelAnnotation\Commands;


use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

class AnnotationConfig extends Command
{

    protected $signature = 'annotation:config';

    protected $description = '生成注解配置';


    function handle()
    {
        $this->info("开始生成注解配置文件...");
        $config = config_path('annotation.php');
        if (!is_file($config)) {
            $source = file_get_contents(realpath(__DIR__ . '/../config.php'));
            file_put_contents($config, $source);
        }
        $this->info("注解配置文件生成完毕");
    }
}