<?php

namespace Crastlin\LaravelAnnotation;

use Crastlin\LaravelAnnotation\Annotation\Injection;
use Crastlin\LaravelAnnotation\Annotation\Route;
use Crastlin\LaravelAnnotation\Annotation\Validation;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;
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
                Commands\AnnotationRoute::class,
                Commands\AnnotationNode::class,
                Commands\AnnotationConfig::class,
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
        $this->app->singleton('crast.injection', function () {
            return new Injection();
        });
        $this->app->singleton('crast.validation', function () {
            return new Validation(config('annotation'));
        });
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
        $path = Request::capture()->path();
        if (!empty($config['auto_create_case']) && !empty($config['modules'])) {
            if (!empty($path) && preg_match('#^(\w+)(/[\w/]+)?$#', $path, $matches)) {
                if (!empty($matches[1]) && !Route::exists($path, $namespace, $routeBasePath)) {
                    $redis = Redis::connection();
                    $distributedLock = new RedisLock($redis, 'distributed_lock:create_route_with_node', 60);
                    try {
                        if ($distributedLock->acquire()) {
                            $moduleBasePath = !empty($config['controller_base']) ? rtrim($config['controller_base'], '/') : 'app/Http/Controllers';
                            $moduleBasePath = base_path($moduleBasePath);
                            \Crastlin\LaravelAnnotation\Annotation\Route::autoBuildRouteMapping($config['modules'], $moduleBasePath, $namespace, $routeBasePath, $config['root_group'] ?? [], $config, $path);

                            $distributedLock->release();
                        }
                    } catch (Throwable $exception) {
                        echo "file: " . $exception->getFile() . ' -> ' . $exception->getLine() . PHP_EOL;
                        echo 'message: ' . $exception->getMessage();
                        $distributedLock->release();
                        Log::error('sync create route mapping was failed: ' . $exception->getMessage());
                    }
                }
            }
        }
        // 注册路由
        $this->registerRoute($config, $routeBasePath, $namespace);
    }


    /**
     * register route map into Route
     *
     * @param array $config
     * @param string $routeBasePath
     * @param string $baseNamespace
     * @return void
     */
    protected function registerRoute(array $config, string $routeBasePath, string $baseNamespace)
    {
        // register route file
        if (!empty($config['modules'])) {
            foreach ($config['modules'] as $module) {
                $module = ucfirst($module);
                $annotationRoute = "{$routeBasePath}/{$module}/route.php";
                if (is_file($annotationRoute))
                    \Illuminate\Support\Facades\Route::namespace($baseNamespace)->group($annotationRoute);
            }
        }
    }
}