<?php


namespace Crastlin\LaravelAnnotation\Annotation\Annotations;

/**
 * Class Target
 * @package Crastlin\LaravelAnnotation\Annotation
 * @Annotation
 */
class Target
{

    /**
     * @var string $value set target value
     */
    private $value;

    const TYPE = 'class';
    const METHOD = 'method';
    const PROPERTY = 'property';
}