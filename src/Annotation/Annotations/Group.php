<?php


namespace Crastlin\LaravelAnnotation\Annotation\Annotations;


use Crastlin\LaravelAnnotation\Annotation\Annotation;

/**
 * Class Group
 * @package Crastlin\LaravelAnnotation\Annotation\routes
 * @Annotation
 * @Target(Target::TYPE | Target::METHOD)
 */
class Group extends Annotation
{

    /**
     * @var string $prefix route prefix
     */
    public $prefix;

    /**
     * @var string $namespace the controller namespace (only set parent namespace name)
     */
    public $namespace;

    /**
     * @var string $domain set route domian
     */
    public $domain;

    /**
     * @var string $middleware route middleware name, it's must be set in \App\Http\Kernel by $routeMiddleware
     */
    public $middleware;

    /**
     * @var string $as route alias name, default is controller name::action name
     */
    public $as;
}