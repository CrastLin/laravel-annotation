<?php


namespace Crastlin\LaravelAnnotation\Annotation;


use ReflectionMethod;
use Throwable;

/**
 * Class Route
 * @package Crastlin\LaravelAnnotation\Annotation
 * @author crastlin@163.com
 * @date 2022-03-15
 * @route 不带参数，默认method = get，路由名为：{模块名}/{控制器名（不含Controller）}/{方法名}
 * @route (method=post)
 * @RequestMapping  限制get或post请求，不设置参数，则为默认路由地址
 * @RequestMapping ("自定义路由")
 * @PostMapping  限制post请求
 * @PostMapping ("自定义路由")
 * @GetMapping  限制get请求
 * @GetMapping ("自定义路由")
 * @OptionsMapping 限制options请求
 * @OptionsMapping("自定义路由")
 * 参数说明：
 * method      允许类型（可选），使用route注解时有效
 * url         自定义路由地址（可选）
 * *如果需要路由分组，请使用@Group注解
 */
class Route extends Node
{
    protected $target = [self::ELEMENT_METHOD];

    protected $annotateName = 'Route|RequestMapping|Request|PostMapping|Post|GetMapping|Get|OptionsMapping|Options|DeleteMapping|Delete|PutMapping|Put|Resource';

    protected $defaultFields = ['method' => 'get'];

    protected $annotateNameMethodBatchSet = [
        'RequestMapping' => [
            'method' => 'GET|POST|OPTIONS|DELETE|PUT',
        ],
        'GetMapping' => [
            'method' => 'GET',
        ],
        'PostMapping' => [
            'method' => 'POST',
        ],
        'DeleteMapping' => [
            'method' => 'DELETE',
        ],
        'PutMapping' => [
            'method' => 'PUT',
        ],
    ];

    /**
     * @var array $rootGroup
     */
    protected $rootGroup;

    /**
     * set root group
     * @param array $rootGroup
     * @return $this
     */
    function setRootGroup(array $rootGroup): Route
    {
        $this->rootGroup = $rootGroup;
        return $this;
    }

    /**
     * @var array $groupParamList
     */
    protected static $groupParamList;

    /**
     * @var array $config
     */
    protected static $config;

    /**
     * @var string $rootSavePath
     */
    protected static $rootSavePath;

    /**
     * @var array $routeGlobalMap
     */
    protected static $routeGlobalMap;

    /**
     * @var array $anotherAnnotationGlobalMap
     */
    protected static $anotherAnnotationGlobalMap;


    // parse base url
    static function parseBaseUrl(string $url)
    {
        $minCount = $maxCount = 0;
        $vars = '';
        if (!empty($url) && preg_match_all('(\{[a-z]+[\w\?]*\})', $url, $matches)) {
            $url = str_replace($matches[0], '', $url);
            $urlStringList = explode('/', $url);
            $optCount = 0;
            foreach ($matches[0] as $k => $value) {
                $vars .= ($k > 0 ? ',' : '') . $value;
                if (strpos($value, '?') !== false)
                    ++$optCount;
            }
            $url = join('/', array_filter($urlStringList));
            $maxCount = count($matches[0]);
            $vars = join(',', $matches[0]);
            $minCount = $maxCount - $optCount;
        }
        return [$url, $minCount, $maxCount, $vars];
    }


    /**
     * check route exists
     * @param string $path the request url string
     * @param string $namespace the controllers base namespace
     * @param string $mapBasePath the base path of map file cache
     * @param int $varCount
     * @return bool
     */
    static function exists(string $path, string $namespace, string $mapBasePath, array $mapRecords = null, $varCount = 0): bool
    {
        if (is_null($mapRecords)) {
            $mapFile = "{$mapBasePath}/map.php";
            $mapRecords = is_file($mapFile) ? require_once $mapFile : [];
        }
        if (!empty($mapRecords) && array_key_exists($path, $mapRecords)) {
            foreach ($mapRecords[$path] as $cs => $map):
                if (strpos($cs, '-') === false)
                    break;
                list($minCount, $maxCount) = explode('-', $cs);
                if ($varCount > 0 && ($varCount < $minCount || $varCount > $maxCount))
                    continue;
                if (empty($map['name']))
                    break;
                $actionList = explode('@', $map['name']);
                if (count($actionList) == 2) {
                    $controller = $namespace . '\\' . $actionList[0];
                    if (class_exists($controller) && method_exists($controller, $actionList[1])) {
                        // check modify time
                        $mtime = 0;
                        if (!empty($map['mtime'])) {
                            $reflect = new \ReflectionClass($controller);
                            $mtime = filemtime($reflect->getFileName());
                        }
                        if (empty($map['mtime']) || $map['mtime'] == $mtime)
                            return true;
                    }
                }
                break;
            endforeach;
            return false;
        }
        // repeat check base path
        $pathList = explode('/', $path);
        if (count($pathList) > 1) {
            array_pop($pathList);
            return self::exists(join('/', $pathList), $namespace, $mapBasePath, $mapRecords, ++$varCount);
        }
        return false;
    }


    /**
     * run create with annotation
     * @param string $scanPath
     * @param string $namespace
     * @param array $params
     * @return void
     */
    static function runCreateWithAnnotation(string $scanPath, string $namespace, ...$params): void
    {
        self::$groupParamList = null;
        list($basePath, $repeatCreate, $rootGroup) = [
            $params[0] ?? 'data',
            $params[1] ?? false,
            $params[2] ?? [],
        ];
        $routePath = "{$basePath}/route.php";
        if (!is_file($routePath) || $repeatCreate) {
            $aliasPath = "{$basePath}/alias.php";
            $routeAnnotationList = [];
            $lockAnnotationSet = [];
            $lastModifyTimeList = [];
            self::scanAnnotation($scanPath, $namespace, function (string $class, ?int $mtime) use (&$routeAnnotationList, &$lockAnnotationSet, &$lastModifyTimeList, $rootGroup, $routePath, $namespace) {
                // create group route annotate object
                $group = new Group($class);
                $group->matchClassAnnotate();

                // create controller sync lock
                $lockAnnotation = new SyncLock($class);

                $classStringList = explode('\\', $class);
                $classShoreName = join('\\', array_slice($classStringList, 3));
                $lastModifyTimeList[$classShoreName] = $mtime;
                $lockAnnotation->matchAllMethodAnnotation(function (array $annotation, ReflectionMethod $method) use ($classShoreName, &$lockAnnotationSet) {
                    $action = "{$classShoreName}@{$method->name}";
                    if (empty($annotation['response']) && !empty(self::$config['lock']['response']))
                        $annotation['response'] = self::$config['lock']['response'];
                    $lockAnnotationSet[$action] = $annotation;
                });
                // create route annotate object
                $route = new Route($class);
                $route->setRootGroup($rootGroup)->matchAllMethodAnnotation(function (ReflectionMethod $method) use ($route, $group, &$routeAnnotationList) {

                    $routeAnnotation = $route->parseRouteAnnotation($method, $group);
                    if (!empty($routeAnnotation))
                        $routeAnnotationList[] = $routeAnnotation;
                });
            });
            try {
                if (!empty($routeAnnotationList)) {
                    if (!is_dir($basePath)) {
                        mkdir($basePath, 0755, true);
                    }
                    $routeCode = '';
                    $groupList = [];
                    $aliasCode = [];
                    $reduceGroupRoute = function (array $items, array $buildRoute, &$data) use (&$reduceGroupRoute) {
                        foreach ($items as $node => $item):
                            if (!empty($item) && count($item) > 0) {
                                return $reduceGroupRoute($items[$node], $buildRoute, $data[$node]);
                            } else {
                                $data[$node][] = $buildRoute;
                            }
                        endforeach;
                    };
                    foreach ($routeAnnotationList as $routeAnnotation):
                        if (!empty($routeAnnotation['route'])) {
                            $method = strtolower($routeAnnotation['route']['method']) ?? 'get';
                            $isManyMethod = strpos($method, '|') !== false;
                            $methodList = $isManyMethod ? explode('|', $method) : [$method];
                            $url = $routeAnnotation['route']['url'];
                            $routeSingleSet = [];
                            if (!$isManyMethod && in_array($method, ['get', 'post', 'options'])) {
                                $methodString = $method;
                                $routeSingle = sprintf("  Route::%s('%s', '%s')->name('%s');\r\n", $method, $url, $routeAnnotation['route']['action'], $routeAnnotation['route']['name']);
                            } else {
                                $methodString = var_export(array_values($methodList), true);
                                $method = 'match';
                                $routeSingle = sprintf("  Route::match(%s, '%s', '%s')->name('%s');\r\n", $methodString, $url, $routeAnnotation['route']['action'], $routeAnnotation['route']['name']);
                            }
                            $routeSingleSet = [
                                'method' => $method,
                                'methodString' => $methodString,
                                'url' => $url,
                                'action' => $routeAnnotation['route']['action'],
                                'name' => $routeAnnotation['route']['name'],
                            ];
                            $groupTree = $routeAnnotation['route']['groupTree'] ?? [];

                            if (!empty($groupTree)) {
                                $reduceGroupRoute($groupTree, $routeSingleSet, $groupList);
                            } else {
                                $routeCode .= $routeSingle;
                                $classes = explode('@', $routeAnnotation['route']['action']);
                                [$baseUrl, $minCount, $maxCount, $vars] = self::parseBaseUrl($url);
                                self::$routeGlobalMap[$baseUrl]["{$minCount}-{$maxCount}"] = [
                                    'name' => $routeAnnotation['route']['action'],
                                    'mtime' => $lastModifyTimeList[$classes[0]] ?? 0,
                                    'vars' => $vars,
                                ];
                                if (!empty($lockAnnotationSet) && array_key_exists($routeAnnotation['route']['action'], $lockAnnotationSet))
                                    self::$anotherAnnotationGlobalMap['lock_annotation'][$url] = $lockAnnotationSet[$routeAnnotation['route']['action']];
                            }
                        }
                        if (!empty($routeAnnotation['alias'])) {
                            $aliasCode = array_merge($aliasCode, $routeAnnotation['alias']);
                        }
                    endforeach;
                    // 写入文件
                    $buildRoute = " /**\r\n  * @author crastlin@163.com\r\n  * @date 2022-03-12\r\n  * 命令：php artisan make:route 生成控器带注解的路由（可配置config/annotation 请求时自动生成路由和权限菜单节点）\r\n  * 路由分组：@Group({\"prefix\":\"api\", \"namespace\": \"Api\", \"domain\": \"xxx.com\", \"middleware\": \"xxx.xx\", \"as\": \"xxx::\"})\r\n  * 例子：@route (method=post|get, url=自定义路由名)\r\n  * 例子：@RequestMapping(\"自定义路由名\") 另外还支持@PostMapping/@GetMapping/@OptionsMapping\r\n  * 路由规则说明：未定义url注解时，默认使用{控制器名}（不含Controller）/{方法名}做为路由\r\n  * 路由分组说明：创建路由时，可以添加默认分组，存在类@Group注解时，当前类的方法会全部划分到该分组中，路由分组根据层级成树状包装\r\n  */\r\n";
                    if ($routeCode)
                        $buildRoute .= $routeCode;
                    if (!empty($groupList)) {
                        $reduceRouteFile = function (array $items, string &$routeCode, string $prefix = '', string $namesapce = '') use (&$reduceRouteFile, &$space, &$lockAnnotationSet, &$lastModifyTimeList) {
                            foreach ($items as $node => $item):
                                if (is_string($node)) {
                                    // 路由分组参数
                                    $params = self::$groupParamList[$node] ?? [];
                                    $paramsString = var_export($params, true);
                                    $routeCode .= "Route::group({$paramsString}, function () {\r\n";
                                    $reduceRouteFile($item, $routeCode, $prefix . (!empty($params['prefix']) ? "{$params['prefix']}/" : ''), $namesapce . (!empty($params['namespace']) ? "{$params['namespace']}\\" : ''));
                                    $routeCode .= "});\r\n";
                                } else {
                                    // 路由规则
                                    $url = "{$prefix}{$item['url']}";
                                    $classShoreName = "{$namesapce}{$item['action']}";
                                    $classes = explode('@', $classShoreName);
                                    [$baseUrl, $minCount, $maxCount, $vars] = self::parseBaseUrl($url);
                                    self::$routeGlobalMap[$baseUrl]["{$minCount}-{$maxCount}"] = [
                                        'name' => $classShoreName,
                                        'mtime' => $lastModifyTimeList[$classes[0]] ?? 0,
                                        'vars' => $vars,
                                    ];

                                    if (!empty($lockAnnotationSet) && array_key_exists($classShoreName, $lockAnnotationSet))
                                        self::$anotherAnnotationGlobalMap['lock_annotation'][$url] = $lockAnnotationSet[$classShoreName];

                                    if ($item['method'] == 'match') {
                                        $routeCodeString = sprintf("  Route::match(%s, '%s', '%s')->name('%s');\r\n", $item['methodString'], $item['url'], $item['action'], $item['name']);
                                    } else {
                                        $routeCodeString = sprintf("  Route::%s('%s', '%s')->name('%s');\r\n", $item['method'], $item['url'], $item['action'], $item['name']);
                                    }
                                    $routeCode .= $routeCodeString;
                                }
                            endforeach;
                        };
                        $reduceRouteFile($groupList, $buildRoute);
                        self::$groupParamList = null;
                    }
                    if (!empty($buildRoute))
                        file_put_contents($routePath, "<?php\r\n{$buildRoute}");
                    if (!empty($aliasCode))
                        file_put_contents($aliasPath, "<?php\r\nreturn " . var_export($aliasCode, true) . ";");
                }
            } catch (\Throwable $exception) {
                $message = static::class . " has Exception:" . $exception->getMessage() . ' --> ' . $exception->getFile() . ':' . $exception->getLine();
                var_dump($message);
            }
        }
    }

    /**
     * parse route annotation
     * @param ReflectionMethod $method
     * @param Group $group
     * @return array
     */
    function parseRouteAnnotation(ReflectionMethod $method, Group $group): ?array
    {
        $annotation = $this->matchMethodAnnotate($method);
        if (empty($this->matchMethodAnnotateName))
            return null;
        $actionName = $method->getName();
        if ($this->annotateNameMethodBatchSet) {
            $annotateNameBatch = $this->annotateNameMethodBatchSet[$this->matchMethodAnnotateName] ?? [];
            $annotation = !empty($annotateNameBatch) ? array_merge($annotation, $annotateNameBatch) : $annotation;
        }
        if (empty($annotation['method']))
            $annotation['method'] = !empty($this->defaultFields['method']) ? trim($this->defaultFields['method']) : 'get';
        $routeAnnotation = ['alias' => [], 'route' => []];
        $rawAction = "{$this->controller}/{$actionName}";
        $annotateValue = $annotation['url'] ?? (isset($annotation['value']) && strtolower($this->matchMethodAnnotateName) != 'route' ? $annotation['value'] : '');
        if (!empty($annotateValue)) {
            $routeAnnotation['alias'][$rawAction] = $annotateValue;
            $annotation['url'] = $annotateValue;
        } else {
            $annotation['url'] = $rawAction;
        }
        $annotation['action'] = $this->controllerName . '@' . $actionName;
        $annotation['name'] = strtolower($this->module) . '.' . $this->controller . '.' . $actionName;
        // merge route group
        // get group class annotate result
        $groupClassAnnotate = $group->getClassAnnotateResult();
        $groupClassAnnotate = $groupClassAnnotate ?: [];
        // merge default group annotate
        $groupKey = ucfirst($this->module);
        $routeGroup = !empty($this->rootGroup) && array_key_exists($groupKey, $this->rootGroup) ? $this->rootGroup[$groupKey] : [];
        $groupAnnotateList = !empty($routeGroup) && !empty($groupClassAnnotate) ? array_merge($routeGroup, $groupClassAnnotate) : (!empty($groupClassAnnotate) ? $groupClassAnnotate : $routeGroup);
        // get group method annotates result
        $groupMethodAnnotate = $group->matchMethodAnnotate($method);
        $groupMethodAnnotate = $groupMethodAnnotate ?: [];
        $groupAnnotateList = !empty($groupAnnotateList) && !empty($groupMethodAnnotate) ? array_merge($groupAnnotateList, $groupMethodAnnotate) : (!empty($groupMethodAnnotate) ? $groupMethodAnnotate : $groupAnnotateList);
        $annotation['groupTree'] = null;
        if (!empty($groupAnnotateList)) {
            $unique = [];
            foreach ($groupAnnotateList as $groupAnnotate):
                ksort($groupAnnotate);
                $uniqueSet = [];
                foreach ($groupAnnotate as $key => $value):
                    if (empty($value)) {
                        unset($groupAnnotate[$key]);
                        continue;
                    }
                    $uniqueSet[] = trim($key) . '=' . (is_array($value) ? join(',', $value) : trim($value));
                endforeach;
                $uniqueKey = sha1(join('&', $uniqueSet));
                if (!empty($unique) && in_array($uniqueKey, $unique))
                    continue;
                $unique[] = $uniqueKey;
                if (empty(self::$groupParamList[$uniqueKey]))
                    self::$groupParamList[$uniqueKey] = $groupAnnotate;

            endforeach;
            // build a group tree
            $buildTree = function ($index = 0) use (&$buildTree, $unique) {
                $key = $unique[$index];
                $result[$key] = [];
                if (!empty($unique[++$index]))
                    $result[$key] = $buildTree($index);
                return $result;
            };
            $annotation['groupTree'] = $buildTree();
        }
        $routeAnnotation['route'] = $annotation;
        return $routeAnnotation;
    }

    /**
     * auto create route mapping
     * @param string $routePath
     * @param array $moduleList
     * @param string $moduleBasePath
     * @param string $namespaceBase
     * @param string $routeBasePath
     * @param array|null $rootGroup
     * @param bool $isAsyncBuildNode
     * @param callable|null $callback
     * @throws Throwable
     */
    static function autoBuildRouteMapping(array $moduleList, string $moduleBasePath, string $namespaceBase, string $routeBasePath, ?array $rootGroup = null, array $annotationConfig = [])
    {
        self::$routeGlobalMap = [];
        $modulePathMapping = [];
        $toCreateRoute = true;
        self::$rootSavePath = $routeBasePath;
        self::$config = $annotationConfig;

        foreach ($moduleList as $module):
            $module = ucfirst(strtolower($module));
            /* $routeFile = "{$routeBasePath}/{$module}/route.php";
             $exists = is_file($routeFile);
             if ($exists) {
                 // 如果存在,则查找是否定义
                 $content = file_get_contents($routeFile);
                 if ($toCreateRoute && stristr($content, $routePath) !== false) {
                     $toCreateRoute = false;
                     break;
                 }
            }*/
            $modulePath = $moduleBasePath . '/' . $module;
            if (is_dir($modulePath))
                $modulePathMapping[$module] = $modulePath;
        endforeach;
        // 扫描所有目录，并创建路由
        if ($toCreateRoute && !empty($modulePathMapping)) {
            foreach ($modulePathMapping as $module => $scanPath):
                $namespace = $namespaceBase . '\\' . $module;
                $basePath = "{$routeBasePath}/{$module}";
                self::runCreateWithAnnotation($scanPath, $namespace, $basePath, true, $rootGroup);
                // 自动更新节点
                if (!empty($annotationConfig['auto_create_node']))
                    Node::runCreateWithAnnotation($scanPath, $namespace);
            endforeach;
            if (!empty(self::$routeGlobalMap)) {
                file_put_contents("{$routeBasePath}/map.php", "<?php\r\nreturn " . var_export(self::$routeGlobalMap, true) . ";");
                self::$routeGlobalMap = null;
            }
            if (!empty(self::$anotherAnnotationGlobalMap)) {
                foreach (self::$anotherAnnotationGlobalMap as $name => $list) {
                    file_put_contents("{$routeBasePath}/{$name}.php", "<?php\r\nreturn " . var_export($list, true) . ";");
                }
                self::$anotherAnnotationGlobalMap = null;
            }
        }
    }

}
