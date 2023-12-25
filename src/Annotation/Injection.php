<?php

namespace Crastlin\LaravelAnnotation\Annotation;

use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * @package Inject
 * @author crastlin@163.com
 * @date 2023-09-19
 * @example using bind for inject object
 */
class Injection
{
    /**
     * @var Injection $inject
     */
    static $inject;

    /**
     * @var array $attributes inject container
     */
    protected $attributes = [];

    protected $injectObjectList = [];


    function bind(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    function offsetSet(string $name, $value): void
    {
        $this->bind($name, $value);
    }

    function into(array $attributes, bool $recover = false)
    {
        $this->attributes = $recover ? $attributes : array_merge($this->attributes, $attributes);
    }

    function bindAll(array $attributes, bool $recover = false)
    {
        $this->into($attributes, $recover);
    }

    function take(string $name)
    {
        return $this->attributes[$name] ?? null;
    }


    function offsetGet(string $name)
    {
        return $this->take($name);
    }


    function takeAll(): ?array
    {
        return $this->attributes;
    }

    function clearAll()
    {
        $this->attributes = [];
    }

    function takeAllToJson(): ?string
    {
        return json_encode($this->takeAll(), 256);
    }

    function unbind(string $name): void
    {
        if ($this->offsetExists($name))
            unset($this->attributes[$name]);
    }

    function offsetUnset(string $name): void
    {
        $this->unbind($name);
    }

    function exists(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    function offsetExists(string $name): bool
    {
        return $this->exists($name);
    }


    /**
     * 获取注入信息缓存
     * @param \ReflectionClass $reflect
     * @return array
     */
    protected function getInjectInformation(\ReflectionClass $reflect): array
    {
        $conf = config('annotation');
        $rootPath = $conf && !empty($conf['annotation_path']) ? $conf['annotation_path'] : 'data/';
        $rootPath = base_path("{$rootPath}inject/");
        $class = $reflect->getName();
        // get current class file modify time
        $classFile = $reflect->getFileName();
        $subList = explode('\\', $class);
        $name = array_pop($subList);
        $path = $rootPath . join('/', $subList) . '/';
        $hasPath = is_dir($path);
        $file = $path . $name . '.php';
        $hasFile = $hasPath && is_file($file);
        $injectData = $hasFile ? require_once $file : [];

        $mtime = (string)filemtime($classFile);
        $basePath = base_path();
        // get parent class file modify time
        if (empty($injectData['parents'])) {
            // repeat get parent class information
            $repeatGetParentClass = function (\ReflectionClass $reflect) use (&$repeatGetParentClass, &$injectData, $basePath) {
                $parentClass = $reflect->getParentClass();
                if ($parentClass) {
                    $class = $parentClass->getFileName();
                    if (empty($class))
                        return;
                    $injectData['parents'][] = [
                        'file' => str_replace($basePath, '', $parentClass->getFileName()),
                        'class' => $parentClass->getName(),
                    ];
                    if ($parentReflect = $parentClass->getParentClass())
                        $repeatGetParentClass($parentReflect);
                }
            };
            $repeatGetParentClass($reflect);
        }
        if (!empty($injectData['parents'])) {
            foreach ($injectData['parents'] as $parent) {
                $mtime .= (string)filemtime("{$basePath}{$parent['file']}");
            }
        }

        if (!$hasFile || empty($injectData['mtime']) || $injectData['mtime'] != $mtime) {
            $redis = Redis::connection();
            $locker = new RedisLock($redis, "sync_inject_cache:{$class}", 60);
            try {
                if (!$hasPath)
                    mkdir($path, 0755, true);
                $annotations = $this->parseAnnotationByReflect($reflect);
                $injectData = array_merge($injectData, $annotations);
                $injectData['mtime'] = $mtime;
                if ($locker->acquire()) {
                    file_put_contents($file, "<?php\r\n/**\r\n  * @author crastlin@163.com\r\n  * @date 2022-03-12\r\n  * 使用Injection::bind(name, value) 绑定数据，使用属性注解@Inject 注入到属性\r\n*/\r\nreturn " . var_export($injectData, true) . ";");
                    $locker->release();
                }
            } catch (\Throwable $exception) {
                echo "file: " . $exception->getFile() . ' -> ' . $exception->getLine() . PHP_EOL;
                echo 'message: ' . $exception->getMessage();
                $locker->release();
                Log::error('sync create route mapping was failed: ' . $exception->getMessage());
                throw new $exception;
            }
        }
        return $injectData;
    }


    // parse annotation by reflect docComment
    protected function parseAnnotationByReflect(\ReflectionClass $reflectionClass): array
    {
        $targetList = [$reflectionClass->getProperties(), $reflectionClass->getMethods(\ReflectionMethod::IS_PUBLIC)];
        $maps = [];
        foreach ($targetList as $targetReflect) {
            foreach ($targetReflect as $target) {
                $key = $target instanceof \ReflectionProperty ? 'properties' : 'methods';
                if (!isset($maps[$key]))
                    $maps[$key] = [];
                $annotation = $this->matchAnnotation($target->getDocComment());
                if ($annotation === null)
                    continue;
                $targetName = $target->getName();
                $name = !empty($annotation['value']) ? $annotation['value'] : (!empty($annotation['name']) ? $annotation['name'] : $targetName);
                $map = [];
                switch ($key) {
                    case 'properties':
                        $map = [
                            'property' => $targetName,
                            'name' => $name,
                            'prefix' => $annotation['prefix'] ?? '',
                            'typeof' => $annotation['typeof'] ?? '',
                        ];
                        break;
                    case 'methods':
                        $map = [
                            'method' => $targetName,
                            'name' => $name,
                            'prefix' => $annotation['prefix'] ?? '',
                        ];
                        break;
                }
                $maps[$key][] = $map;
            }
        }
        return $maps;
    }

    // match annotation from content
    protected function matchAnnotation(string $content): ?array
    {
        if (empty($content))
            return null;
        if (!preg_match('#@(Inject|Autowired)\s*(\(\s*(.*)\))?#i', $content, $mt) || empty($mt[1])) {
            return null;
        }
        if (empty($mt[3]))
            return [];
        // 注解参数格式：({value = "", value2 = xxx})
        $annotate = $mt[3];
        if (preg_match('#^\s*\{(.*)\}\s*$#', $annotate, $matches))
            $annotate = $matches[1];
        // 默认格式 (xxx=yyy, zzz=mmm)
        $annotate = str_replace(['"', '\''], '', $annotate);
        $annotateSet = [];
        if (strpos($annotate, ',') === false) {
            if (strpos($annotate, '=') !== false) {
                $annotateField = explode('=', $annotate);
                if (!empty($annotateField[0]))
                    $annotateSet[$annotateField[0]] = $annotateField[1] ?? '';
            } else {
                $annotateSet['value'] = trim($annotate);
            }
        } else {
            $annotateList = explode(',', $annotate);
            foreach ($annotateList as $item):
                $nodeParam = explode('=', $item);
                // check node params when its count grant 2
                if (count($nodeParam) > 2) {
                    $tmp = $nodeParam;
                    unset($tmp[0]);
                    $nodeParam[1] = join('=', $tmp);
                    $nodeParam = array_slice($nodeParam, 0, 2);
                }

                if (!isset($nodeParam[1]) || $nodeParam[1] === '') {
                    if (isset($nodeParam[0]) && !empty($this->defaultFields) && array_key_exists($nodeParam[0], $this->defaultFields)) {
                        $nodeParam[1] = $this->defaultFields[$nodeParam[0]];
                    } else {
                        continue;
                    }
                }
                list($key, $value) = [trim($nodeParam[0]), trim($nodeParam[1])];
                $annotateSet[$key] = $value;
            endforeach;
        }
        return $annotateSet;
    }

    // auto inject all properties and methods
    protected function autoInject(string $class, &$object = null): void
    {
        if (!empty($this->injectObjectList) && array_key_exists($class, $this->injectObjectList))
            return;
        $reflect = new \ReflectionClass($class);
        $this->bind('reflectionClass', $reflect);
        $object = $object ?: $reflect->newInstance();
        $propertySetMethodType = method_exists($object, 'setProperty') ? 'setProperty' : (method_exists($object, '__set') ? 'set' : '');

        $propertiesCache = $this->getInjectInformation($reflect);
        // inject all properties
        if (!empty($propertiesCache['properties'])) {
            foreach ($propertiesCache['properties'] as $property) {
                $propertyName = $property['property'];
                $injectName = !empty($property['name']) ? $property['name'] : $propertyName;
                $prefix = $property['prefix'] ?? '';
                $bindName = $prefix ? "{$prefix}.{$injectName}" : $injectName;
                if ($this->exists($bindName)) {
                    $propertySetter = 'set' . ucfirst($propertyName);
                    $value = $this->take($bindName);
                    switch (true) {
                        // using setter action
                        case method_exists($object, $propertySetter):
                            $object->{$propertySetter}($value);
                            break;
                        // using setProperty action
                        case $propertySetMethodType == 'setProperty':
                            $object->setProperty($propertyName, $value);
                            break;
                        // using __set magic action
                        case $propertySetMethodType == 'set':
                            $object->{$propertyName} = $value;
                            break;
                        default:
                            throw new \Exception("Class {$class} must define the property set method: setProperty or __set for injection");
                    }
                }
            }
        }
        // inject all methods
        if (!empty($propertiesCache['methods'])) {
            foreach ($propertiesCache['methods'] as $method) {
                $methodName = $method['method'];
                $injectName = !empty($method['name']) ? $method['name'] : $methodName;
                $prefix = $method['prefix'] ?? '';
                $bindName = $prefix ? "{$prefix}.{$injectName}" : $injectName;
                if ($this->exists($bindName)) {
                    if (!method_exists($object, $methodName))
                        throw new \Exception("{$class}::{$methodName} is not defined");
                    $value = $this->take($bindName);
                    $object->{$methodName}($value);
                }
            }
        }
    }

    // inject by object
    function injectWithObject($object): void
    {
        $this->autoInject(get_class($object), $object);
    }

    // inject by class namesapce
    function injectWithClass(string $class)
    {
        if (!class_exists($class))
            throw new \Exception("Class {$class} is not exists");
        $object = null;
        $this->autoInject($class, $object);
        return $object;
    }

}