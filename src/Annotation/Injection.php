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
        $classFile = $reflect->getFileName();
        $subList = explode('\\', $class);
        $mtime = filemtime($classFile);
        $name = array_pop($subList);
        $path = $rootPath . join('/', $subList) . '/';
        $hasPath = is_dir($path);
        $file = $path . $name . '.php';
        $hasFile = $hasPath && is_file($file);
        $injectData = $hasFile ? require_once $file : [];
        if (!$hasFile || empty($injectData['mtime']) || $injectData['mtime'] != $mtime) {
            $redis = Redis::connection();
            $locker = new RedisLock($redis, "sync_inject_config:{$class}", 60);
            try {
                if (!$hasPath)
                    mkdir($path, 0755, true);
                $injectData = ['mtime' => $mtime, 'properties' => []];
                foreach ($reflect->getProperties() as $property) {
                    $annotation = $this->matchAnnotation($property->getDocComment());
                    if ($annotation === null)
                        continue;
                    $propertyName = $property->getName();
                    $injectData['properties'][] = [
                        'property' => $propertyName,
                        'name' => !empty($annotation['value']) ? $annotation['value'] : (!empty($annotation['name']) ? $annotation['name'] : $propertyName),
                        'prefix' => $annotation['prefix'] ?? '',
                        'typeof' => $annotation['typeof'] ?? '',
                    ];
                }
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


    protected function autoInject(string $class, &$object = null): void
    {
        if (!empty($this->injectObjectList) && array_key_exists($class, $this->injectObjectList))
            return;
        $reflect = new \ReflectionClass($class);
        $this->bind('reflectionClass', $reflect);
        $object = $object ?: $reflect->newInstance();
        $propertySetMethodType = method_exists($object, 'setProperty') ? 'setProperty' : (method_exists($object, '__set') ? 'set' : '');

        $propertiesCache = $this->getInjectInformation($reflect);
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
                        case 'set':
                            $object->{$propertyName} = $value;
                            break;
                        default:
                            throw new \Exception("Class {$class} must define the property set method: setProperty or __set for injection");
                    }
                }
            }
        }
    }

    function injectWithObject($object): void
    {
        $this->autoInject(get_class($object), $object);
    }

    function injectWithClass(string $class)
    {
        if (!class_exists($class))
            throw new \Exception("Class {$class} is not exists");
        $object = null;
        $this->autoInject($class, $object);
        return $object;
    }

}