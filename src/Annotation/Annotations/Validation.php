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
     * @var string $attribute the attribute name that to validating
     */
    public $attribute;

    /**
     * @var string $field the field of parameters
     */
    public $field;

    /**
     * @var string $rule the rule that to validating
     */
    public $rule;

    /**
     * @var string $rules json array example: ["required", "in:1,2"]
     */
    public $rules;

    /**
     * @var string $message prompt of validation result error
     */
    public $message;
    /**
     * @var string $messages json object message prompt of validation result error
     */
    public $messages;


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