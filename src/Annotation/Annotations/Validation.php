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
     * it must instanceof Validate
     * configuration of namespaces in configuration files
     * @example class="User"
     */
    public $class;

    /**
     * @var string $attribute the attribute is named for field
     * the name of the configuration parameter display for viewing in error messages
     * @example attribute="user nickname"
     */
    public $attribute;

    /**
     * @var string $field the field of parameters
     * request key names in parameter objects
     * @example field="nickname"
     */
    public $field;

    /**
     * @var string $rule Validation Rule Definition
     * many rules using "|"
     * @example rule="required|alpha|..."
     */
    public $rule;

    /**
     * @var string $rules Validation Rules Definition, datatype is json array
     * @example rules=["required", "alpha"]
     */
    public $rules;

    /**
     * @var string $message prompt of validation result error
     * many messages using "|"
     * @example message="nickname must required|nickname type must be letter"
     */
    public $message;
    /**
     * @var string $messages json object message prompt of validation result error
     * @example messages={"required": "nickname must required", "alpha": "nickname type must be letter"}
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