# laravel-annotation

#### 介绍
laravel-annotation 是基于PHP反射机制，将注解标记解析成功功能，Route（路由）、Group（路由分组，包含路由中间件定义）、管理后台菜单树和权限结点注解，
基于基类Annotation可以自定更多功能注解类，提高开发效率，减少重复的无用工作。

#### 软件要求
支持laravel版本 >= 5.8，php版本 >= 7.1


#### 安装教程

1. composer require crastlin/laravel-annotation:v2.0.3beta 安装
2. 或在composer.json中的require添加 "crastlin/laravel-annotation":"^v2.0.3beta"

#### 使用说明

1. ##### 路由注解
> 定义规则
* 使用Route/RequestMapping/PostMapping/GetMapping/OptionsMapping注解定义路由
* 生成的路由文件在项目根目录的data/routes/{模块名}/route.php
* 同时会生成自定义路由对应控制器规则的别名文件 alias.php
* 路由注解仅支持方法注解，定义在class无效
* 使用PHP Storm时，在settings / Plugins 中搜索php-annotations，使用时会有相应的提示。
> 注解例子1
````php
 class IndexController
 {
   /**
    * @Route(url="login", method="post")
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
 Route::post('login', 'IndexController@index')->name('index.index');
````

* 说明：如果不定义url或value，则默认为：{当前控制器}（不含Controller）/{方法名}作为url

> 注解例子2
````php
 class IndexController
 {
    /**
     * @PostMapping("article/{cate}")
     */
    function index(){
    
    }
    
    /**
     * @PostMapping("article/{cate}/{id}/{page?}")
     */
    function detail(){
      // todo
    }
 }
````

以上两种注解生成的路由配置结果相同：
````php
 Route::post('article/{cate}', 'IndexController@index')->name('index.index');
 Route::post('article/{cate}/{id}/{page?}', 'IndexController@detail')->name('index.detail');
````

2. ##### 路由分组注解
> 定义规则
* 使用Group() 支持Json格式或按字段传值注解定义路由闭包分组
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
* 以下为注解全局配置
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
* 根路由分组，默认不分组，定义格式为
````php
return [
  'modules' => ['User', 'Admin'],
  'root_group' => [
     'User' => [
        ['prefix' => 'user', 'namespace' => 'User', 'middleware' => 'user.check', 'as' => 'User::'],
        // 更多分组
      ],
     'Admin' => [
        ['prefix' => 'admin', 'namespace' => 'Admin', 'middleware' => 'admin.check', 'as' => 'Admin::'],
        // 更多分组
     ],
   ],
];
````
  ##### auto_create_node
* 请求时自动创建节点，默认关闭，可以配置环境 ANNOTATION_AUTO_CRATE_NODE=true 开启

> 命令说明
  ##### 生成路由
* 使用命令生成所有模块的路由映射文件，生产环境时建议使用此方式，如需指定生成模块，则在命令后输入模块名
````shell script
php artisan annotation:route {module?}
````    

3. ##### 菜单树与权限节点注解
* 在开发后台时，经常会需要使用到功能菜单和角色权限分配的功能，使用注解的好处在于，开发时定义好菜单树和权限节点信息，无需在数据库繁琐添加，只需要使用生成命令，快速将注解的菜单树和权限节点保存到数据库，方便环境切换和移植，为开发者整理菜单节约宝贵的时间。
> 定义规则
* 使用@node(name=菜单名称 ...) 定义为菜单节点
* 支持类注解和方法注解
> 类注解模块
````php
 /**
  * @node (name=“应用名称”, parent=“父节点”, menu=0/1, auth=0/1/2, order=0, params=“xx=yy&cc=ss“, icon=”xxx“, remark=”xxx“, actions=”defaultPage,xxx,yyyy“)
  */
```` 

> 方法注解模块
 ````php
  /**
   * @node (name=”节点名称“, parent=”父节点“, menu=0/1, auth=0/1/2, order=0, params=”xx=yy&cc=ss“, icon=”xxx“, code=”query“, remark=”xxx“, ignore, delete)
   */
 ```` 
> 参数说明
 ##### name
 *（必须）注册节点名称, 如果使用子类名称，则需在子类的类注解定义 @node (name=xxx)，实际名称 = 子类的类注解name + 当前方法name
 ##### parent
 *（可选）注册节点的父节点，如果不定义，则做为一级节点菜单, 默认父节点为当前控制器的defaultPage方法
 ##### menu
 *（可选）注册节点显示类型，0：不在左侧菜单显示(默认)，1：显示左侧菜单；
 ##### auth
 *（可选）注册节点权限类型，0：只作为菜单，其它：验证权限(默认)
 ##### order
 *（可选）菜单排序, menu为1时有效
 ##### params
 （可选）菜单携带参数，格式为url参数格式
 ##### icon
 （可选）作为一级菜单时，定义图标
 ##### code 
 * (可选) 按钮权限控制代码，在admin_menu_permission表定义，默认为query
 ##### remark
 *（可选）备注功能信息
 ##### ignore 
 * (可选) 是否忽略扫描
 ##### delete
 * (可选) 删除节点，存在子节点时无效
 ##### actions
 * (可选) 多态方法继承时，通过此注解指定继承的方法名，name获取组合名={子类name注解}+{父类方法name注解}
> 特殊说明
* 如果值为0，可以只定义属性名称，例如：@node (name=xxx, menu, auth, order)
* 注解标记node和属性括号、参数间可以有空格  

> 注解例子
````php
 class UserController
 {
   
    /**
    * @Node(name="用户管理", menu=1, auth=0)
    */
   function defaultPage()
   {
     // this method only for menu
   }

   /**
    * @Node(name="用户列表", menu=1)
    */
   function index()
   {
     // todo
   }
  
   /**
    * @Node(name="编辑用户", parent="index")
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
 abstract class BaseController implements \LaravelAnnotationNodeInterface
 {
       /**
        * @Node(menu=1, auth=0)
        */
       function defaultPage()
       {
         // this method only for menu
       }
 }
 
 /**
  * @Node(name="用户管理", order=1)
  */
 class UserController extends BaseController
 {
     /**
      * @Node(name="用户列表", menu=1)
      */
     function index()
     {
       // todo
     }
    
     /**
      * @Node(name="编辑用户", parent="index")
      */
     function setUserName()
     {
       // todo
     }

 }
````
* 以上User控制器类注解，会合并到继承defaultPage方法上
````php
   /**
    * @Node(name="用户中心", menu=1, auth=0, order=1)
    */
   function defaultPage()
   {
     // this method only for menu
   }
````
> 多态控制器应用
* 多态应用时，类注解名name叠加
````php
 /**
  * @Node(name="动物园", order=1)
  */
 abstract class Animal extends BaseController
 {
   /**
    * @Node(name="主页", menu=1)
    */
   function index()
   {
     // todo
   }
   
   /**
    * @Node(name="观看时间", menu=1)
    */
   function schedule()
   {
     // todo
   }
 }
 
 /**
  * @Node(name="长颈鹿", actions=index, schedule)
  */
 class GiraffeController extends Animal
 {
 }
 
 /**
  * @Node(name="老虎")
  */
 class TigerController extends Animal
 {  
 }
 
````
* 通过类注解的actions指定继承方法名，访问index时，name等于：长颈鹿主页、老虎主页，访问schedule时，name等于：长颈鹿观看时间、老虎观看时间。
* 生成所有模块的菜单树和权限节点，如果需要指定模块，则在命令后输入模块名称：
````shell script
php artisan annotation:node {module?}
````
> 节点注解demo
* 请查看我的主页laravel-annotation-demo仓库获取，内附使用demo和需要使用的sql


4. ##### 分布式原子锁注解 (2022-8 新增，需要使用: composer require crastlin/laravel-annotation:v2.0.3beta)
* 经常遇到有些情况需要防止并发操作的应用场景，可以使用该注解创建原子操作锁，防止并发访问。
> 使用需要在app/Http/Kernel.php中增加中间件配置
````php
 class Kernel extends \Illuminate\Foundation\Http\Kernel
 {
    protected $middleware = [
       // ...
       Crastlin\LaravelAnnotation\Middleware\InterceptorMiddleware::class,
    ];
 }
````
> 然后在控制器中定义规则
* 使用@SyncLock(expire=锁时间, ...)
* 只支持方法注解

> 参数说明
##### prefix
* （可选）锁key前缀名，默认为：sync_lock_annotation
##### name
*（可选）锁key名，默认为模块名+控制器名+方法名，完整的名称为：{prefix}_{name}
##### suffix
*（可选）锁后缀名，可解析输入参数变量，例如请求get id, 则可以使用: suffix="$id" 或者 suffix="get.$id"  或者 suffix="input.$id"
支持：input/get/post/put/delete/header等，其中input包含get/post/put/delete
##### suffixes
*（可选）多个参数后缀，使用：suffix={"xxx", "yyy"}，同样也支持解析输入变量，例如：suffixes={"$id", "header.$token", "post.name", ...}
##### expire
*（可选）锁有效期，单位秒，默认为86400
##### once
（可选）是否自动释放锁，默认为否，即执行完成自动释放锁
##### response
（可选）拒绝时的响应数据，json格式，也可以单独配置code或msg，或者在config/annotation.php中配置lock => response
##### code
（可选）response中的自定义code
##### msg
（可选）response中的自定义message

> 示例
````php
 class IndexController
 {
    /**
     * @RequestMapping("test")
     * @SyncLock(expire=30, suffix="$id")
     */
    function test()
    {
       //todo
    }
 }
````
* 以上的效果是同一的id请求会限制并发

 #### 代码贡献
 * crastlin@163.com
 
 #### 使用必读
 * 使用此插件请遵守法律法规，请勿来在非法应用中使用，产生的一切后果和法律责任均与作者无关！


