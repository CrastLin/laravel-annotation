<?php


namespace Crastlin\LaravelAnnotation\Annotation;


use Closure;
use ReflectionClass;
use ReflectionMethod;
use Exception;
use ReflectionProperty;

/**
 * Class Annotation
 * @package Crastlin\LaravelAnnotation\Annotation
 * @author crastlin@163.com
 * @date 2022-03-15
 */
abstract class Annotation implements AnnotationInterface
{
    /**
     * @var ReflectionClass $reflection 类反射对象
     */
    protected $reflection;
    /**
     * @var ReflectionMethod $method 方法反射对象
     */
    protected $method;
    /**
     * @var ReflectionProperty $property 属性反射对象
     */
    protected $property;

    // 注解在类上
    const ELEMENT_TYPE = 'class';
    // 注解在属性上
    const ELEMENT_PROPERTY = 'property';
    // 注解在方法上
    const ELEMENT_METHOD = 'method';

    protected
        /**
         * @var bool $isCli 是否命令行模式
         */
        $isCli = false,
        /**
         * @var array $annotationTypeList 允许注解位置
         */
        $target,
        /**
         * @var string $pattern 通用匹配规则模板
         */
        $pattern = '#@({annotateName})\s*(\(\s*(.*)\))?#i',
        /**
         * @var string $annotateName 注解名
         */
        $annotateName,
        /**
         * @var array $annotateNameTypeBatchSet 批量匹配类注解规则设置
         */
        $annotateNameTypeBatchSet,
        /**
         * @var array $annotateNamePropertyBatchSet 批量匹配属性注解规则设置
         */
        $annotateNamePropertyBatchSet,
        /**
         * @var array $annotateNameMethodBatchSet 批量匹配方法注解规则设置
         */
        $annotateNameMethodBatchSet,
        /**
         * @var array $matchAnnotateType 匹配到的类注解名
         */
        $matchTypeAnnotateName,
        /**
         * @var array matchPropertyAnnotateName 匹配到的属性注解名
         */
        $matchPropertyAnnotateName,
        /**
         * @var array matchMethodAnnotateName 匹配到的方法注解名
         */
        $matchMethodAnnotateName,
        /**
         * @var array $classAnnotate 注解解析数据
         */
        $classAnnotate,
        /**
         * @var string[] $defaultFields 属性默认值，没有设置值时，自动匹配
         */
        $defaultFields,
        /**
         * @var string[] $ignoreMethodList 忽略的公共方法
         */
        $ignoreMethodList = ['__construct', '__destruct', 'callAction', 'middleware', 'getMiddleware', '__call', 'authorize', 'authorizeForUser', 'authorizeResource', 'dispatchNow', 'validateWith', 'validate', 'validateWithBag'],
        $ignorePropertyList;

    /**
     * Node constructor.
     * @param string $class
     * @param array $ignoreMethodList
     * @param array $ignorePropertyList
     * @throws Exception
     */
    function __construct(string $class, array $ignoreMethodList = null, array $ignorePropertyList = null)
    {
        $this->reflection = new ReflectionClass($class);
        if (empty($this->annotateName)) {
            $class = explode('\\', static::class);
            $this->annotateName = array_pop($class);
            $this->annotateName = strtolower($this->annotateName);
        }
        $this->pattern = str_replace('{annotateName}', $this->annotateName, $this->pattern);
        $this->ignoreMethodList = !empty($ignoreMethodList) ? array_merge($this->ignoreMethodList, $ignoreMethodList) : $this->ignoreMethodList;
        $this->ignorePropertyList = !empty($ignorePropertyList) ? array_merge($this->ignorePropertyList, $ignorePropertyList) : $this->ignorePropertyList;
        $this->isCli = strpos(php_sapi_name(), 'cli') !== false;
    }


    /**
     * scan annotation from all class
     * @param string $scanPath
     * @param string $namespace
     * @param callable $callback
     * @return void
     */
    static function scanAnnotation(string $scanPath, string $namespace, callable $callback): void
    {
        if (!$callback instanceof Closure)
            throw new Exception('callback is not instanceof Closure');
        // 扫描控制器
        $directoryList = scandir($scanPath);
        foreach ($directoryList as $directory):
            if ($directory == '.' || $directory == '..')
                continue;
            $fileName = rtrim($directory, '.php');
            $classNamespace = $namespace . '\\' . $fileName;
            $callback($classNamespace);
        endforeach;
    }

    /**
     * parse node information from annotate
     * @param string $annotate
     * @return array
     */
    protected function parseAnnotate(string $annotate): array
    {
        if (empty($annotate))
            return [];
        // json参数格式：({"name": "value", "array": ["value1", "value2"], "object": {"name": "value1", "name2": "value2"}})
        if (preg_match('#^\s*((\{.*\})|(\[.*\]))$#', $annotate, $matches)) {
            $annotateJson = json_decode(trim($annotate), true);
            if (!is_null($annotateJson))
                return $annotateJson;
        }
        // java注解参数格式：({value = "", value2 = xxx})
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


    /**
     * match all method annotation
     * @param callable|null $callable 回调方法
     * @param int $classAccess 类的访问权限类型，在ReflectionMethod获取
     * @return array
     */
    function matchAllMethodAnnotation(?callable $callable = null, int $classAccess = ReflectionMethod::IS_PUBLIC)
    {
        if ($this->reflection->isAbstract())
            return [];
        // 获取类注解
        $this->matchClassAnnotate();
        // 获取方法注解
        $methods = $this->reflection->getMethods($classAccess);
        $methodAnnotationList = [];
        foreach ($methods as $method):
            if (!empty($this->ignoreMethodList) && in_array($method->getName(), $this->ignoreMethodList))
                continue;
            if (!is_null($callable) && $callable instanceof \Closure) {
                if ($callable($method))
                    break;
            } else {
                $annotationList = $this->matchMethodAnnotate($method);
                if (!empty($annotationList))
                    $methodAnnotationList[] = $annotationList;
            }
        endforeach;
        return $methodAnnotationList;
    }

    /**
     * match all property annotation
     * @param callable|null $callable 回调方法
     * @param int $classAccess 类的访问权限类型，在ReflectionProperty获取
     * @return array
     */
    function matchAllPropertyAnnotation(?callable $callable = null, int $classAccess = \ReflectionProperty::IS_PUBLIC)
    {
        if ($this->reflection->isAbstract())
            return [];
        // 获取方法注解
        $properties = $this->reflection->getProperties($classAccess);
        $propertyAnnotationList = [];
        foreach ($properties as $property):
            if (!empty($this->ignorePropertyList) && in_array($property->getName(), $this->ignorePropertyList))
                continue;
            if (!is_null($callable) && $callable instanceof \Closure) {
                if ($callable($property))
                    break;
            } else {
                $annotationList = $this->matchPropertyAnnotate($property);
                if (!empty($annotationList))
                    $propertyAnnotationList[] = $annotationList;
            }
        endforeach;
        return $propertyAnnotationList;
    }

    /**
     * match class annotate
     * @param string $pattern
     * @return AnnotationInterface
     */
    protected function matchClassAnnotate(string $pattern = ''): AnnotationInterface
    {
        $pattern = $pattern ?: $this->pattern;
        if (!$this->classAnnotate && in_array(self::ELEMENT_TYPE, $this->target)) {
            $docComment = $this->reflection->getDocComment();
            preg_match($pattern, $docComment, $matches);
            $this->matchTypeAnnotateName = $matches[1] ?? '';
            $this->classAnnotate = !isset($matches[1]) ? null : $this->parseAnnotate($matches[3] ?? '');
        }
        return $this;
    }

    /**
     * get class match annotate result
     * @return mixed
     */
    function getClassAnnotateResult()
    {
        return $this->classAnnotate['result'] ?? $this->classAnnotate;
    }

    /**
     * match method annotate
     * @param ReflectionMethod $method
     * @param string $pattern
     * @return array
     */
    protected function matchMethodAnnotate(ReflectionMethod $method = null, string $pattern = ''): ?array
    {
        $pattern = $pattern ?: $this->pattern;
        if (in_array(self::ELEMENT_METHOD, $this->target)) {
            $method = $method ?: $this->method;
            $methodComment = $method->getDocComment();
            preg_match($pattern, $methodComment, $matches);
            $this->matchMethodAnnotateName = $matches[1] ?? '';
            return !isset($matches[1]) ? null : $this->parseAnnotate($matches[3] ?? '');
        }
        return null;
    }

    /**
     * match property annotate
     * @param ReflectionProperty $property
     * @param string $pattern
     * @return array
     */
    protected function matchPropertyAnnotate(ReflectionProperty $property, string $pattern = ''): ?array
    {
        $pattern = $pattern ?: $this->pattern;
        if (in_array(self::ELEMENT_PROPERTY, $this->target)) {
            $property = $property ?: $this->property;
            $propertyComment = $property->getDocComment();
            preg_match($pattern, $propertyComment, $matches);
            $this->matchPropertyAnnotateName = $matches[1] ?? '';
            return !isset($matches[1]) ? null : $this->parseAnnotate($matches[3] ?? '');
        }
        return null;
    }
}