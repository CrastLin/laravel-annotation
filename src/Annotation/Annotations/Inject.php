<?php

namespace Crastlin\LaravelAnnotation\Annotation\Annotations;

/**
 * @Annotation
 * @Target(Target::PROPERTY)
 * @example bind Injection::bind("xxx", value)
 * @example using @Inject(name="xxx", prefix="xxx")
 * the property's permission must be protected or public otherwise defined set method for property
 */
class Inject
{

    /**
     * @var string $name
     * get injection bind name
     */
    public $name;

    /**
     * @var string $prefix
     * get injection bind name's prefix
     * @example service.name then using service
     */
    public $prefix;

}