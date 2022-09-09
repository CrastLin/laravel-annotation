<?php

namespace Crastlin\LaravelAnnotation\Annotation\Annotations;
/**
 * @Annotation
 * @Target(Target::METHOD)
 */
class Validate
{

    /**
     * @var string $class define a verifier implementation class (class namespace)
     * it must extends Validate,
     */
    public $class;

    /**
     * @var string $name the input field's name that to validating
     */
    public $name;

    /**
     * @var string $rule the rule
     */
    public $rule;

    public $msg;
}