<?php

namespace Crastlin\LaravelAnnotation\Annotation\Annotations;
/**
 * @Annotation
 * @Target(Target::METHOD)
 */
class SyncLockByToken extends SyncLock
{

    /**
     * @var string $body the parameter of request body, default from header body
     */
    public $body = 'header';

    /**
     * @var string $token the field of jwt token, default is suffix="header.$token"
     */
    public $token = 'token';
}