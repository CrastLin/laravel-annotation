<?php

namespace Crastlin\LaravelAnnotation;

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
            if (!empty($path) && preg_match('#^(\w+)(/\w+)?(/\w+)?$#', $path, $matches)) {
                if (!empty($matches[1])) {
                    $mapFile = "{$routeBasePath}/map.php";
                    $map = is_file($mapFile) ? require_once $mapFile : [];
                    $exists = !empty($map) && array_key_exists($path, $map);
                    $actionExists = false;
                    if ($exists && !empty($map[$path]['name'])) {
                        $actionList = explode('@', $map[$path]['name']);
                        if (count($actionList) == 2) {
                            $controller = $namespace . '\\' . $actionList[0];
                            if (class_exists($controller) && method_exists($controller, $actionList[1])) {
                                // check modify time
                                $mtime = 0;
                                if (!empty($map[$path]['mtime'])) {
                                    $reflect = new \ReflectionClass($controller);
                                    $mtime = filemtime($reflect->getFileName());
                                }
                                if (empty($map[$path]['mtime']) || $map[$path]['mtime'] == $mtime)
                                    $actionExists = true;
                            }

                        }
                    }
                    if (!$actionExists) {
                        $redis = Redis::connection();
                        $distributedLock = new RedisLock($redis, 'distributed_lock:create_route_with_node', 60);
                        try {
                            if ($distributedLock->acquire()) {
                                $moduleBasePath = !empty($config['controller_base']) ? rtrim($config['controller_base'], '/') : 'app/Http/Controllers';
                                $moduleBasePath = base_path($moduleBasePath);
                                \Crastlin\LaravelAnnotation\Annotation\Route::autoBuildRouteMapping($config['modules'], $moduleBasePath, $namespace, $routeBasePath, $config['root_group'] ?? [], $config);

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