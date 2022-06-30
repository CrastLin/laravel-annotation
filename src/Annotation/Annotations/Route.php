<?php


namespace Crastlin\LaravelAnnotation\Annotation\Annotations;

use Crastlin\LaravelAnnotation\Annotation\Annotation;

/**
 * Class Route
 * @package Crastlin\LaravelAnnotation\Annotation\routes
 * @Annotation
 * @Target(Target::METHOD)
 */
class Route extends Annotation
{

    /**
     * @var string $name route name
     */
    public $name;

    /**
     * @var string $url route url
     */
    public $url;

    /**
     * @var string $method method name default get
     */
    public $method = 'GetMapping';

    protected function __construct()
    {
        parent::__construct();
        $class = static::class;
        if ($class != self::class) {
            $classList = explode('\\', $class);
            $this->method = array_pop($classList);
        }
    }
}