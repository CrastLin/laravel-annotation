<?php

namespace Crastlin\LaravelAnnotation\Annotation;

use ReflectionMethod;

/**
 * Interface AnnotationInterface
 * @package Crastlin\LaravelAnnotation\Annotation
 * @author crastlin@163.com
 * @date 2022-08-30
 * @example: (annotation parameters example: key=value, key2=value2,...)
 * @description:
 * [prefix] the prefix of the lock name, a string put
 *
 * [name] the lock name, a string put
 *
 * [suffix] the suffix of the lock name, a string put, Support request parameter variables, using suffix as '$var' corresponding request parameter 'var', If you need to specify the request parameter type, please configure the request method, using as :  suffix="get.$id" or suffix="header.$id", default is input
 *
 * [suffixes] many of suffix as an array
 *
 * [expire] the lock's expire time unit: second, default 3600s
 *
 * [once] unlock when the lock was expired
 */
class SyncLock extends Annotation
{

    protected $target = [self::ELEMENT_METHOD];

    protected $attributes = 'response|suffixes';

    protected $defaultValueField = 'expire';

    /**
     * parse node information from annotate
     * @param string $annotate
     * @return array
     */
    protected function parseAnnotate(string $annotate): array
    {
        $annotationResult = [];
        if (preg_match_all('~((' . $this->attributes . ')\s*=\s*(\{.*?\}))~', $annotate, $matches) && count($matches) == 4) {
            $annotate = str_replace($matches[1], '', $annotate);
            foreach ($matches[2] as $k => $name):
                $value = $matches[3][$k] ?? [];
                if ($value && strpos($value, ':') === false) {
                    $annotationResult[$name] = explode(',', str_replace(['{', '}', ' '], '', $value));
                } else {
                    $annotationResult[$name] = json_decode($value, true);
                }
            endforeach;
        }
        $annotationOthers = parent::parseAnnotate($annotate);
        return !empty($annotationResult) ? array_merge($annotationOthers, $annotationResult) : $annotationOthers;
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

        // 获取方法注解
        $methods = $this->reflection->getMethods($classAccess);
        $methodAnnotationList = [];
        $class = $this->reflection->getName();
        $classes = explode('\\', $class);
        $classes = array_slice($classes, -2);
        $shortName = $classes[0] . '_' . str_replace('Controller', '', $classes[1]);
        $classes = explode('\\', static::class);
        $annotationClass = array_pop($classes);
        $annotationReflect = new \ReflectionClass(__NAMESPACE__ . '\\Annotations\\' . $annotationClass);

        $annotationInstance = $annotationReflect->newInstance();
        $defaultBody = '';
        $defaultToken = '';
        if ($annotationInstance instanceof \Crastlin\LaravelAnnotation\Annotation\Annotations\SyncLockByToken)
            list($defaultBody, $defaultToken) = [$annotationInstance->body, $annotationInstance->token];

        foreach ($methods as $method):
            if (!empty($this->ignoreMethodList) && in_array($method->getName(), $this->ignoreMethodList))
                continue;
            $annotation = $this->matchMethodAnnotate($method);
            if (empty($annotation))
                continue;
            $annotation['action'] = $method->name;
            $annotation[$this->defaultValueField] = $annotation[$this->defaultValueField] ?? ($annotation['value'] ?? $annotationInstance->{$this->defaultValueField});
            list($defaultBody, $defaultToken) = [
                !empty($annotation['body']) ? $annotation['body'] : $defaultBody,
                !empty($annotation['token']) ? $annotation['token'] : $defaultToken,
            ];
            list($annotation['name'], $annotation['prefix'], $annotation['suffix'], $annotation['suffixes'], $annotation['expire'], $annotation['once'], $annotation['response']) = [
                strtolower($shortName . '_' . (!empty($annotation['name']) ? $annotation['name'] : $method->name)),
                !empty($annotation['prefix']) ? rtrim($annotation['prefix'], '_') . '_' : 'sync_lock_annotation_',
                !empty($annotation['suffix']) && is_string($annotation['suffix']) ? ltrim($annotation['suffix'], ':') : (!empty($defaultBody) && !empty($defaultToken) ? $defaultBody . '.$' . $defaultToken : ''),
                !empty($annotation['suffixes']) && is_array($annotation['suffixes']) ? array_map(function ($value) {
                    return preg_replace('~\"([\w\$\.]+)\"~', '$1', $value);
                }, $annotation['suffixes']) : [],

                !empty($annotation['expire']) && is_numeric($annotation['expire']) && $annotation['expire'] > 0 ? (int)$annotation['expire'] : 86400,
                !empty($annotation['once']) && $annotation['once'] != 'false' ? 1 : 0,
                !empty($annotation['response']) ? $annotation['response'] : (isset($annotation['code']) ? ['code' => (int)$annotation['code'], 'msg' => $annotation['msg'] ?? ''] : []),
            ];
            if (!is_null($callable) && $callable instanceof \Closure) {
                $methodAnnotationList[] = $callable($annotation, $method);
            } else {
                $methodAnnotationList[] = $annotation;
            }
        endforeach;
        return $methodAnnotationList;
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
        // todo scanAnnotation
    }


    /**
     * run create with annotation
     * @param string $scanPath
     * @param string $namespace
     * @param array $params
     * @return mixed
     */
    static function runCreateWithAnnotation(string $scanPath, string $namespace, ...$params)
    {
        // todo scanAnnotation
    }
}