<?php

namespace Crastlin\LaravelAnnotation;

use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AnnotationProvider extends ServiceProvider
{

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadCommands();
        $this->autoBuildRouteWithNode();
    }


    /**
     * 加载命令
     *
     * @return void
     */
    protected function loadCommands()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Commands\MakeRoute::class,
                Commands\MakeNode::class,
            ]);
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->setupConfig();
    }

    /**
     * 设置配置文件
     *
     * @return void
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__ . '/config.php');
        $userConfig = config_path('annotation.php');
        $this->publishes([$source => $userConfig]);
        $this->mergeConfigFrom($source, 'annotation');
    }


    /**
     * auto build route and node
     */
    protected function autoBuildRouteWithNode()
    {
        $config = config('annotation');
        $namespace = !empty($config['controller_namespace']) ? rtrim($config['controller_namespace'], '\\') : 'App\\Http\\Controllers';
        $filePath = !empty($config['annotation_path']) ? rtrim($config['annotation_path'], '/') : 'data';
        $routeBasePath = base_path($filePath . '/routes');
        if (!empty($config['auto_create_case'])) {
            $path = Request::instance()->path();
            if (!empty($config['modules']) && !empty($path) && preg_match('#^(\w+)(/\w+)?(/\w+)?$#', $path, $matches)) {
                if (!empty($matches[1]) && in_array(ucfirst($matches[1]), $config['modules'])) {
                    $redis = Redis::connection();
                    $distributedLock = new RedisLock($redis, 'distributed_lock:create_route_with_node', 60);
                    try {
                        if ($distributedLock->acquire()) {
                            $moduleBasePath = !empty($config['controller_base']) ? rtrim($config['controller_base'], '/') : 'app/Http/Controllers';
                            $moduleBasePath = base_path($moduleBasePath);
                            \Crastlin\LaravelAnnotation\Annotation\Route::autoBuildRouteMapping(ltrim($path, "/{$matches[1]}"), $config['modules'], $moduleBasePath, $namespace, $routeBasePath, $config['default_middleware'] ?? [], !empty($config['auto_create_node']));
                            $distributedLock->release();
                        }
                    } catch (Throwable $exception) {
                        $distributedLock->release();
                        Log::error('sync create route mapping was failed: ' . $exception->getMessage());
                    }
                }
            }
        }
        // register route file
        if (!empty($config['modules'])) {
            foreach ($config['modules'] as $module) {
                $module = ucfirst($module);
                $annotationRoute = "{$routeBasePath}/{$module}/route.php";
                if (is_file($annotationRoute))
                    \Illuminate\Support\Facades\Route::namespace($namespace)->group($annotationRoute);
            }
        }
    }
}