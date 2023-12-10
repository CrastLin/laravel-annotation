<?php

namespace Crastlin\LaravelAnnotation\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @package Inject
 * @mixin \App\Utils\Injection
 * @remark 注入方法，使用bind方法，将需要注入的属性加：@Inject 注解
 * @method static void bind(string $name, $value)
 * @method static void offsetSet(string $name, $value)
 * @method static void bindAll(array $attributes, bool $recover = false)
 * @method static void into(array $attributes, bool $recover = false)
 * @method static mixed take(string $name)
 * @method static mixed offsetGet(string $name)
 * @method static array takeAll()
 * @method static string takeAllToJson()
 * @method static void clearAll()
 * @method static void unbind(string $name)
 * @method static void offsetUnset(string $name)
 * @method static bool exists(string $name)
 * @method static bool offsetExists(string $name)
 * @method static void injectWithObject($instance)
 * @method static object injectWithClass(string $class)
 */
class Injection extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'injection';
    }
}