<?php


namespace Crastlin\LaravelAnnotation\Annotation;

/**
 * Interface AnnotationInterface
 * @package Crastlin\LaravelAnnotation\Annotation
 * @author crastlin@163.com
 * @date 2022-03-15
 */
interface AnnotationInterface
{

    /**
     * scan annotation from all class
     * @param string $scanPath
     * @param string $namespace
     * @param callable $callback
     * @return void
     */
    static function scanAnnotation(string $scanPath, string $namespace, callable $callback): void;


    /**
     * run create with annotation
     * @param string $scanPath
     * @param string $namespace
     * @param array $params
     * @return mixed
     */
    static function runCreateWithAnnotation(string $scanPath, string $namespace, ...$params);
}