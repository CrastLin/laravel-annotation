<?php


namespace Crastlin\LaravelAnnotation\Annotation\Annotations;

use Crastlin\LaravelAnnotation\Annotation\Annotation;

/**
 * Class Resource
 * @package Crastlin\LaravelAnnotation\Annotation
 * @Annotation
 * @Target(Target::PROPERTY)
 */
class Resource extends Annotation
{
    public $required = true;
}