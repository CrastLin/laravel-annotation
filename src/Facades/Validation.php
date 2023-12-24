<?php

namespace Crastlin\LaravelAnnotation\Facades;

use Illuminate\Support\Facades\Facade;
use phpDocumentor\Reflection\Types\Array_;

/**
 * @package Validation
 * @mixin \Crastlin\LaravelAnnotation\Annotation\Validation
 * @method static string runValidation(string $class, string $action, array $data)
 */
class Validation extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'crast.validation';
    }
}