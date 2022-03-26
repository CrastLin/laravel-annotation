# laravel-annotation

#### 介绍
laravel-annotation 是基于PHP反射机制，将注解标记解析成功功能，Route（路由）、Group（路由分组，包含路由中间件定义）、管理后台菜单树和权限结点注解，
基于基类Annotation可以自定更多功能注解类，提高开发效率，减少重复的无用工作。

#### 软件要求
支持laravel版本 >= 5.8，php版本 >= 7.0


#### 安装教程

1. composer require crastlin/laravel-annotation 安装
2. 或在composer.json中的require添加 "crastlin/laravel-annotation"

#### 使用说明

1. ##### 路由注解
> 定义规则
* 使用Route/RequestMapping/PostMapping/GetMapping/OptionsMapping注解定义路由
* 生成的路由文件在项目根目录的data/routes/{模块名}/route.php
* 同时会生成自定义路由对应控制器规则的别名文件 alias.php
* 路由注解仅支持方法注解，定义在class无效
> 注解例子
````php
 class IndexController
 {
   /**
    * @Route(url=login, method=post)
    */
   function index()
   {
     // todo
   }
 }
````
或者
````php
 class IndexController
 {
   /**
    * @PostMapping("login")
    */
   function index(){
     // todo
   }
 }
````
以上两种注解生成的路由配置结果相同：
````php
 Route::post('login', 'IndexController@index');
````
* 说明：如果不定义url或value，则默认为：{当前控制器}（不含Controller）/{方法名}作为url

2. ##### 路由分组注解
> 定义规则
* 使用Group(Json数据)注解定义路由闭包分组
* 路由分组注解支持类注解和方法注解
> 注解例子
````php
 /**
  * @Group({"prefix":"home", "namespace":"Home", "middleware": "user.check", "as": "User::"})
  */
 class IndexController
 {
   /**
    * @Group({"limit": true})
    * @Route(url=login, method=post|get)
    */
   function index()
   {
     // todo
   }
   
   /**
    * @Group({"limit": true})
    * @RequestMapping("reg")
    */
   function register()
   {
     // todo
   }
   
   /** 
    * @GetMapping
    */
   function userCenter()
   {
     // todo
   }
 }
````
* 说明：
* 使用类定义Group注解时，则当前控制器的所有公共带路由注解的方法都会在这个闭包内；
* 而在方法定义Group注解时，仅当前方法在闭包内；
* 所有定义相同的Group参数的路由，会自动放在同一个Group闭包内
以上定义生成的路由为：
````php
 Route::group(["preifx" => "home", "namespace" => "Home", "middleware" => "user.check", "as" => "User::"], function(){
   Route::group(["limit" => true], function(){
      Route::match(['GET', 'POST'], 'login', 'IndexController@index');
      Route::match(['GET', 'POST'], 'reg','IndexController@register');
   });
   Route::get('Index/userCenter','IndexController@userCenter');
 });
  // ...
````
3. ##### 配置和命令
* 可以通过命令生成配置文件，config/annotation
````shell script
php artisan annotation:config
 ````
> 配置说明
  ##### controller_base
* 控制器目录，默认为：app/Http/Controllers
  ##### controller_namespace
* 根命名空间，默认为：App\Http\Controllers
  ##### modules
* 需要扫描的模块目录名称数组，默认为：Admin
  ##### annotation_path
* 生成文件根目录，默认为：data
  ##### auto_create_case
* 是否开启自动生成（建议debug模式下开启）请求时将自动创建新增加的注解到路由表，默认为：env('APP_DEBUG')
  ##### root_group
* 根路由分组，默认不分组，需要根分组，请生成配置文件定义分组参数数组
  ##### auto_create_node
* 请求时自动创建节点，默认关闭，可以配置环境 ANNOTATION_AUTO_CRATE_NODE=true 开启

> 命令说明
  ##### 生成路由
````shell script
php artisan annotation:route
````    

3. ##### 菜单树与权限节点注解
* 在开发后台时，经常会需要使用到功能菜单和角色权限分配的功能，使用注解的好处在于，开发时定义好菜单树和权限节点信息，无需在数据库繁琐添加，只需要使用生成命令，快速将注解的菜单树和权限节点保存到数据库，方便环境切换和移植，为开发者整理菜单节约宝贵的时间。
> 定义规则
* 使用@node(name=菜单名称 ...) 定义为菜单节点
* 支持类注解和方法注解
> 注解例子
````php
 class UserController
 {
   
    /**
    * @node(name=用户列表, menu=1, auth=0)
    */
   function defaultPage()
   {
     // this method only for menu
   }

   /**
    * @node(name=用户列表, menu=1)
    */
   function index()
   {
     // todo
   }
  
   /**
    * @node(name=用户列表, parent=index)
    */
   function setUserName()
   {
     // todo
   }

 }
````
* 说明
* defaultPage方法名为菜单根节点，他在父级ID parent_id=0
* 除defaultPage方法名，其它方法名都有父节点，当前控制器内其它方法不定义父节点时，则默认父节点为: defaultPage, index方法的父节点隐式parent=defaultPage
* setUserName方法为index的子节点，需要定义他的parent注解，默认为当前控制器，所以parent=index 等于 parent=User/index（tips: 如果方法父节点为其它控制器时，则需要定义控制器名，不含Controller后缀）
> 继承类使用
````php
 class BaseController implements \LaravelAnnotationNodeInterface
 {
       /**
        * @node(menu=1, auth=0)
        */
       function defaultPage()
       {
         // this method only for menu
       }
 }
 
 /**
  * @node(name=用户中心, order=1)
  */
 class UserController extends BaseController
 {
     /**
      * @node(name=用户列表, menu=1)
      */
     function index()
     {
       // todo
     }
    
     /**
      * @node(name=用户列表, parent=index)
      */
     function setUserName()
     {
       // todo
     }

 }
````
* 以上User控制器类注解，会合并到继承defaultPage方法上
 ##### 类注解模块
````php
 /**
  * @node (name=应用名称, parent=父节点, menu=0/1, auth=0/1/2, order=0, params=xx=yy&cc=ss, icon=xxx, remark=xxx, actions=defaultPage,xxx,yyyy)
  */
```` 
 ##### 方法注解模块
 ````php
  /**
   * @node (name=节点名称, parent=父节点, menu=0/1, auth=0/1/2, order=0, params=xx=yy&cc=ss, icon=xxx, code=query, remark=xxx, ignore, delete)
   */
 ```` 
> 参数说明


 #### 代码贡献
 * Crastlin@163.com


