<?php


namespace Crastlin\LaravelAnnotation\Annotation;

/**
 * Interface NodeInterface
 * @package app\common\controller
 * @author crastlin@163.com
 * @date 2022-03-15
 * @node (name=应用名称, parent=父节点, menu=0/1, auth=0/1/2, order=0, params=xx=yy&cc=ss, icon=xxx, path=pagePath, component=demoComponent, remark=xxx, actions=defaultPage,xxx,yyyy)
 * @description 此处理注解用于父类方法获取, 除name定义外,其它注解会覆盖父类中的default注解
 * @description actions 需要叠加名称在方法名, 多个方法用英文逗号分隔, 不定义时, 默认为defaultPage方法
 */
interface NodeInterface
{

    /**
     * 保存模式
     * 1：兼容模式，存在注解时，则保存
     * 2：强制模式，检查注解，如果没有定义则报错
     * @remark 如果需要修改模式，请在实现类定义 NODE_SAVE_MODE 常量
     */
    const DEFAULT_NODE_SAVE_MODE = 1;

    /**
     * 主菜单方法定义
     * @node (name=节点名称, parent=父节点, menu=0/1, auth=0/1/2, order=0, params=xx=yy&cc=ss, icon=xxx, code=query, remark=xxx, ignore, delete)
     * @description name （*）注册节点名称, 如果使用子类名称，则需在子类的类注解定义 @node (name=xxx)，实际名称 = 子类的类注解name + 当前方法name
     * @description parent（可选）注册节点的父节点，如果不定义，则做为一级节点菜单, 默认父节点为当前控制器的defaultPage方法
     * @description menu（可选）注册节点显示类型，0：不在左侧菜单显示(默认)，1：显示左侧菜单；
     * @description auth（可选）注册节点权限类型，0：只作为菜单，1：有界面权限，2：无界面权限(默认)
     * @description order（可选）菜单排序, menu为1时有效
     * @description params（可选）菜单携带参数，格式为url参数格式
     * @description icon（可选）作为一级菜单时，定义图标，不带前缀：fa- ，图标请打开 https://www.thinkcmf.com/font/font_awesome/icons.html
     * @desctiption code (可选) 前后端分离，按钮权限控制代码，在admin_menu_permission表定义
     * @description remark（可选）备注功能信息
     * @description ignore (可选) 是否忽略扫描
     * @descriotion delete (可选) 删除节点
     * @example 说明：如果值为0，可以只定义属性名称，例如：@node (name=xxx, menu, auth, order, ignore)
     * @example 注解标记node和属性间可以有空格
     */
    function defaultPage();
}