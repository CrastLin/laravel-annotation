<?php

namespace Crastlin\LaravelAnnotation\Annotation\Annotations;
/**
 * @Annotation
 * @Target(Target::METHOD)
 */
class SyncLock
{

    /**
     * @var string $prefix the prefix of lock name
     */
    public $prefix;

    /**
     * @var string $name lock name, fullname is: ${prefix}${name}:{$suffix}
     * if suffix is not define, then fullname is: ${prefix}${name}
     */
    public $name;

    /**
     * @var string $suffix lock suffix of lock name
     */
    public $suffix;

    /**
     * @var array $suffixes more suffix of lock name
     */
    public $suffixes;

    /**
     * @var bool $once only enty once when it expired
     */
    public $once = false;

    /**
     * @var int $expire set lock expire time
     */
    public $expire = 86400;

    /**
     * @var array $response when gets locking status, then set response body with json format
     */
    public $response = [];

    /**
     * @var int $code when gets locking status, then set response code
     */
    public $code;

    /**
     * @var string $msg when gets locking status, then set response message
     */
    public $msg;


}