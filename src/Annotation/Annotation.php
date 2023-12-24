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
     * @var ReflectionClass $reflection Class reflection object
     */
    protected $reflection;
    /**
     * @var ReflectionMethod $method Method reflection object
     */
    protected $method;
    /**
     * @var ReflectionProperty $property Property reflection object
     */
    protected $property;

    // Annotation on class
    const ELEMENT_TYPE = 'class';
    // Annotation on property
    const ELEMENT_PROPERTY = 'property';
    // Annotation on method
    const ELEMENT_METHOD = 'method';

    protected
        /**
         * @var bool $isCli Command line mode
         */
        $isCli = false,
        /**
         * @var array $annotationTypeList Allowed annotation location
         */
        $target,
        /**
         * @var string $pattern General matching rule template
         */
        $pattern = '#@({annotateName})\s*(\(\s*(.*)\))?#i',
        /**
         * @var string $annotateName Annotation name
         */
        $annotateName,
        /**
         * @var array $annotateNameTypeBatchSet Batch matching class annotation rule settings
         */
        $annotateNameTypeBatchSet,
        /**
         * @var array $annotateNamePropertyBatchSet Batch matching attribute annotation rule settings
         */
        $annotateNamePropertyBatchSet,
        /**
         * @var array $annotateNameMethodBatchSet Batch matching method annotation rule settings
         */
        $annotateNameMethodBatchSet,
        /**
         * @var array $matchAnnotateType Matched class annotation name
         */
        $matchTypeAnnotateName,
        /**
         * @var array $matchPropertyAnnotateName Matched attribute annotation name
         */
        $matchPropertyAnnotateName,
        /**
         * @var array matchMethodAnnotateName Matched method annotation name
         */
        $matchMethodAnnotateName,
        /**
         * @var array $classAnnotate Annotation parsing data
         */
        $classAnnotate,
        /**
         * @var string[] $defaultFields Property default value. If no value is set, it will match automatically
         */
        $defaultFields,
        /**
         * @var string $defaultValueField Missing attribute annotation by default
         */
        $defaultValueField = 'value',
        /**
         * @var string[] $ignoreMethodList Ignored public method
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
            if ($directory == '.' || $directory == '..' || is_dir($directory))
                continue;
            $fileName = rtrim($directory, '.php');
            $file = "{$scanPath}/{$directory}";
            $mtime = filemtime($file);
            $classNamespace = $namespace . '\\' . $fileName;
            $callback($classNamespace, $mtime);
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
        // parameter as json format：({"name": "value", "array": ["value1", "value2"], "object": {"name": "value1", "name2": "value2"}})
        if (preg_match('#^\s*((\{.*\})|(\[.*\]))$#', $annotate, $matches)) {
            $annotateJson = json_decode(trim($annotate), true);
            if (!is_null($annotateJson))
                return $annotateJson;
        }
        // parameter as map format：({value = "", value2 = xxx})
        if (preg_match('#^\s*\{(.*)\}\s*$#', $annotate, $matches))
            $annotate = $matches[1];
        // default format (xxx=yyy, zzz=mmm)
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
     * @param callable|null $callable
     * @param int $classAccess
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
     * @param callable|null $callable
     * @param int $classAccess
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
    protected function matchMethodAnnotate(ReflectionMethod $method = null, bool $isMatchAll = false, string $pattern = ''): ?array
    {
        $pattern = $pattern ?: $this->pattern;
        if (in_array(self::ELEMENT_METHOD, $this->target)) {
            $method = $method ?: $this->method;
            $methodComment = $method->getDocComment();
            if ($isMatchAll) {
                preg_match_all($pattern, $methodComment, $matches);
                if (empty($matches[2]))
                    return null;
                $matchAnnotationList = [];
                foreach ($matches[2] as $match) {
                    $matchAnnotationList[] = $this->parseAnnotate($match);
                }
                return $matchAnnotationList;
            } else {
                preg_match($pattern, $methodComment, $matches);
                $this->matchMethodAnnotateName = $matches[1] ?? '';
                return !isset($matches[1]) ? null : $this->parseAnnotate($matches[3] ?? '');
            }
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