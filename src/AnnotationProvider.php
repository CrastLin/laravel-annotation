<?php

namespace Crastlin\LaravelAnnotation;

use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;
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
        $this->registerAutoCrateRouteMappingAndSaveNode();
    }

    /**
     * 设置配置文件
     *
     * @return void
     */
    protected function setupConfig()
    {
        $source = realpath(__DIR__ . '/config.php');

        $this->publishes([$source => config_path('annotation.php')]);

        $this->mergeConfigFrom($source, 'annotation');
    }

    /**
     * 扫描注解自动创建路由映射文件和保存权限节点
     *
     * @return void
     */
    protected function registerAutoCrateRouteMappingAndSaveNode()
    {
        $this->app->singleton('annotation.create_route_mapping', function ($app) {
            // when debug mode, then auto sync create route mapping and menu node by the controllers annotation
            // support new action annotation to create
            // support edit annotation for update
            $config = config('annotation');
            $namespace = !empty($config['controller_namespace']) ? rtrim($config['controller_base'], '\\') : 'App\\Http\\Controllers';
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
                                \Crastlin\LaravelAnnotation\Annotation\Route::autoBuildRouteMapping(ltrim($path, "/{$matches[1]}"), $config['modules'], $moduleBasePath, $namespace, $routeBasePath, !empty($config['auto_create_node']));
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
                        Route::namespace($namespace)->group($annotationRoute);
                }
            }
        });
    }

}