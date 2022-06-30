<?php


namespace Crastlin\LaravelAnnotation\Annotation\Annotations;

use Crastlin\LaravelAnnotation\Annotation\Annotation;

/**
 * Class Node
 * @package Crastlin\LaravelAnnotation\Annotation
 * @Annotation
 * @Target(Target::TYPE | Target::METHOD)
 */
class Node
{

    /**
     * @var string $name 菜单节点名称，供菜单列表显示
     */
    public $name;

    /**
     * @var string $parent 父节点设置
     *
     * @example 完整写法：module/controller/action，当前控制器时，仅写方法名action，当前模块：controller/action)
     *
     * 不配置时，默认为当前控制器的defaultPage方法
     */
    public $parent;

    /**
     * @var bool $menu 配置是否为菜单，不显示菜单时，设置为false
     */
    public $menu = false;

    /**
     * @var bool $auth 配置是否验证访问权限，不限制时设置为false
     */
    public $auth = true;

    /**
     * @var int $order 菜单树状显示排序
     */
    public $order = 0;

    /**
     * @var string $code 功能权限分类代码, 默认为查询（query）
     * 可自定义类别用于权限分类
     */
    public $code;

    /**
     * @var string $icon 菜单显示图标
     */
    public $icon;

    /**
     * @var string $remark 节点备注
     */
    public $remark;

    /**
     * @var string $actions 类注解时有效，用于限制父类方法合并时，指定可合并的方法名，多个用逗号
     */
    public $actions;

    /**
     * @var bool $ignore 是否忽略当前节点
     */
    public $ignore = false;

    /**
     * @var bool $delete 是否删除当前节点
     */
    public $delete = false;

    /**
     * @var array $data
     */
    public $data = [];


}