<?php
declare(strict_types=1);

namespace Crastlin\LaravelAnnotation\Utils\Traits;

use Crastlin\LaravelAnnotation\Facades\Injection;
use Crastlin\LaravelAnnotation\Facades\Validation;
use ErrorException;
use Swoole\Exception;

trait SingletonTrait
{
    /**
     * @var static[] $singleton
     */
    protected static $singleton;

    /**
     * get singleton instance
     * @param string $name
     * @param mixed $params
     * @return static
     */
    static function singleton(string $name = '', ...$params)
    {
        $baseNameSpace = explode('\\', static::class);
        array_pop($baseNameSpace);
        $baseNameSpace = join('\\', $baseNameSpace);
        $name = $name ? (strpos($name, '\\') !== false ? $name : $baseNameSpace . '\\' . $name) : static::class;
        $key = md5("{$name}_" . serialize($params));
        if (!isset(self::$singleton[$key])) {
            if (!class_exists($name))
                throw new ErrorException("class: {$name} is not exists", 0);
            self::$singleton[$key] = new $name(...$params);
        }
        if (method_exists(self::$singleton[$key], 'init'))
            self::$singleton[$key]->init();
        // 属性依赖注入
        Injection::injectWithObject(self::$singleton[$key]);
        return self::$singleton[$key];
    }

    /**
     * clear singleton instance
     */
    static function clear()
    {
        self::$singleton = null;
    }

    /**
     * call static method
     * @param string $method
     * @param array $args
     */
    static function __callStatus(string $method, array $args = [])
    {
        $object = static::singleton();
        if (!method_exists($object, $method))
            throw new \Exception("method: {$method} is not exists");
        return call_user_func_array([$object, $method], $args);
    }


    // 通用注入属性方法
    function setProperty(string $name, $value)
    {
        if (property_exists($this, $name)) {
            $setMethod = "set" . ucfirst($name);
            if (method_exists($this, $setMethod)) {
                call_user_func_array([$this, $setMethod], [$value]);
            } else {
                $this->{$name} = $value;
            }
        }
    }


    /**
     * invoke methods
     * @param string $method
     * @param mixed $parameters
     */
    function invokeValidation(string $method, $data): void
    {
        if ($errText = Validation::runValidation(static::class, $method, $data))
            throw new Exception($errText, 602);
    }

}
