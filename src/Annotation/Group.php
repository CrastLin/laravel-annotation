<?php


namespace Crastlin\LaravelAnnotation\Annotation;


use ReflectionMethod;

/**
 * Class Group
 * @package App\Library\Annotation
 * @author crastlin@163.com
 * @date 2022-03-15
 * @example: (annotation must be a json data of format)
 * @Group({"prefix":"api", "namespace": "Api", "domain": "xxx.com", "middleware": "xxx.xx", "as": "xxx::"})
 * @description:
 * prefix: set route url prefix
 * namespace: set route action who in the namespace
 * domain: route must bind domain address
 * middleware: route must through the middleware
 */
class Group extends Annotation
{

    protected $target = [self::ELEMENT_TYPE, self::ELEMENT_METHOD];


    static function runCreateWithAnnotation(string $scanPath, string $namespace, ...$params)
    {
        // TODO: Implement runCreateWithAnnotation() method.
    }

    /**
     * match class annotate
     * @param string $pattern
     * @return $this|AnnotationInterface
     */
    function matchClassAnnotate(string $pattern = ''): AnnotationInterface
    {
        $pattern = $pattern ?: $this->pattern;
        if (!$this->classAnnotate && in_array(self::ELEMENT_TYPE, $this->target)) {
            $docComment = $this->reflection->getDocComment();
            preg_match_all($pattern, $docComment, $matches);
            $this->matchTypeAnnotateName = isset($matches[1][0]) ? $matches[1][0] : '';
            if (!empty($this->matchTypeAnnotateName)) {
                $this->classAnnotate = ['result' => []];
                foreach ($matches[3] as $match):
                    $this->classAnnotate['result'][] = $this->parseAnnotate($match ?? '');
                endforeach;
            }
        }
        return $this;
    }

    /**
     * match method annotate
     * @param ReflectionMethod|null $method
     * @param string $pattern
     * @return array|null
     */
    function matchMethodAnnotate(ReflectionMethod $method = null, string $pattern = ''): ?array
    {
        $pattern = $pattern ?: $this->pattern;
        if (in_array(self::ELEMENT_METHOD, $this->target)) {
            $method = $method ?: $this->method;
            $methodComment = $method->getDocComment();
            preg_match_all($pattern, $methodComment, $matches);
            $this->matchMethodAnnotateName = isset($matches[1][0]) ? $matches[1][0] : '';
            if (empty($this->matchMethodAnnotateName))
                return null;
            $groupAnnotateList = [];
            foreach ($matches[3] as $match):
                $groupAnnotateList[] = $this->parseAnnotate($match ?? '');
            endforeach;
            return $groupAnnotateList;
        }
        return null;
    }

}