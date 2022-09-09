<?php

namespace Crastlin\LaravelAnnotation\Annotation\Annotations;
/**
 * @Annotation
 * @Target(Target::METHOD)
 */
class Validation
{

    /**
     * @var string $class define a verifier implementation class (class namespace)
     * it must extends Validate,
     */
    public $class;

    /**
     * @var string $name the attribute's name that to validating
     */
    public $name;

    /**
     * @var string $rule the rule that to validating
     */
    public $rule;


    /**
     * @var string $msg message prompt of validation result error
     */
    public $msg;


    /**
     * contructor
     */
    function __construct()
    {
        if (static::class != __CLASS__) {
            $classNamespaceList = explode('\\', static::class);
            $rule = array_pop($classNamespaceList);
            $this->rule = preg_replace('/(?<=[a-z0-9])([A-Z])/', '_${1}', $rule);
        }
    }
}