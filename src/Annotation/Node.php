<?php


namespace Crastlin\LaravelAnnotation\Annotation;


use Exception;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Closure;
use Throwable;

/**
 * Class Node
 * @package Crastlin\LaravelAnnotation\Annotation
 * @author crastlin@163.com
 * @date 2022-03-15
 * instruction for use see the NodeInterface annotation
 */
class Node extends Annotation
{

    protected
        /**
         * @var array $annotationTypeList 允许注解位置
         */
        $target = [self::ELEMENT_TYPE, self::ELEMENT_METHOD],
        /**
         * @var string $module
         */
        $module,
        /**
         * @var string $controller
         */
        $controller,
        /**
         * @var string $controllerName
         */
        $controllerName,
        /**
         * @var string $action
         */
        $action,
        /**
         * @var array $base
         */
        $base,
        /**
         * @var int $saveMode
         */
        $saveMode,

        /**
         * @var string $defaultValueField
         */
        $defaultValueField = 'name',

        /**
         * @var string[] $defaultFields 属性默认值，没有设置值时，自动匹配
         */
        $defaultFields = ['menu' => '0', 'auth' => '0', 'order' => '0', 'ignore' => '1'];

    /**
     * Node constructor.
     * @param string $class
     * @param string $action
     * @param string $controller
     * @param string $module
     * @throws Exception
     */
    function __construct(string $class, ?string $action = '', string $controller = '', string $module = '')
    {
        parent::__construct($class);
        $this->action = $action;
        $classSpace = explode('\\', $this->reflection->getName());
        $count = count($classSpace);
        $this->controllerName = $classSpace[$count - 1];
        $this->controller = $controller ?: str_replace('Controller', '', $this->controllerName);
        if (empty($this->controller))
            throw new Exception('获取控制器名称失败', 500);
        $this->module = strtolower($module ?: $classSpace[$count - 2] ?? '');
        if (empty($this->module))
            throw new Exception('获取应用名称失败', 500);
        $currentClasses = explode('\\', static::class);
        $currentClass = array_pop($currentClasses);
        if ($currentClass == 'Node') {
            $interfaces = $this->reflection->getInterfaceNames();
            $isImplement = false;
            if (!empty($interfaces)) {
                foreach ($interfaces as $interface):
                    if (strpos($interface, 'NodeInterface') !== false) {
                        $isImplement = true;
                        break;
                    }
                endforeach;
            }
            if (!$isImplement)
                throw new Exception("class::{$class} 没有实现接口: NodeInterface", 500);
            $this->saveMode = $this->reflection->getConstant('NODE_SAVE_MODE') ?: NodeInterface::DEFAULT_NODE_SAVE_MODE;
        }
    }


    /**
     * @var int $maxRepeatTries 生成节点最大递归次数
     */
    protected static $maxRepeatTries = 15;


    /**
     * run create with annotation
     * @param string $scanPath
     * @param string $namespace
     * @param array $params
     * @return void
     * @throws Throwable
     */
    static function runCreateWithAnnotation(string $scanPath, string $namespace, ...$params): void
    {
        $isRepeatCreate = false;
        self::scanAnnotation($scanPath, $namespace, function ($class) use (&$isRepeatCreate) {
            $annotation = new Node($class);
            $annotation->matchAllMethodAnnotation(function (ReflectionMethod $method) use ($annotation, &$isRepeatCreate) {
                try {
                    $annotation->saveNode(null, $method);
                } catch (Throwable $exception) {
                    if (!$isRepeatCreate && $exception->getCode() == 521)
                        $isRepeatCreate = true;
                }
            });
        });
        // when have parent node is not exists then repeat to run self action
        if ($isRepeatCreate && self::$maxRepeatTries > 0) {
            --self::$maxRepeatTries;
            static::runCreateWithAnnotation($scanPath, $namespace, ...$params);
        }
        self::$maxRepeatTries = 15;
    }


    /**
     * save node menu and rule
     * @param callable $callable
     * @param ReflectionMethod $method
     * @throws Exception
     */
    function saveNode(callable $callable = null, ReflectionMethod $method = null): void
    {
        // 获取方法反射对象
        $this->method = $method ?: $this->method;
        if (!$this->method) {
            $this->matchAllMethodAnnotation(function (ReflectionMethod $_method) {
                if ($this->action == $_method->getName()) {
                    $this->method = $_method;
                    return true;
                }
                return false;
            });
        } else {
            $this->action = $method->getName();
        }
        if (empty($this->method))
            throw new Exception("{$this->reflection->getName()}->{$this->action} 方法不存在", 500);

        // 获取方法注解
        $methodAnnotate = $this->matchMethodAnnotate();
        $this->classAnnotate['name'] = $this->classAnnotate['name'] ?? ($this->classAnnotate['value'] ?? '');
        $methodAnnotate['name'] = $methodAnnotate['name'] ?? ($methodAnnotate['value'] ?? '');
        if (!empty($this->classAnnotate['actions'])) {
            $actions = explode(',', $this->classAnnotate['actions']);
            if (in_array($this->action, $actions) || $this->action == 'defaultPage')
                $methodAnnotate['name'] = ($this->classAnnotate['name'] ?? '') . ($methodAnnotate['name'] ?? '');
        }
        if ($this->action == 'defaultPage' && !empty($this->classAnnotate)) {
            $methodAnnotate = array_merge($methodAnnotate, $this->classAnnotate);
        }

        if (empty($methodAnnotate['name'])) {
            if ($this->saveMode == 1)
                return;
            else
                throw new Exception("{$this->module}/{$this->controller}->{$this->action} 没有设置节点注解", 500);
        }
        if (empty($methodAnnotate['ignore'])) {
            $this->base = ['app' => $this->module, 'name' => "{$this->module}/{$this->controller}/{$this->action}"];
            // check and save menu node
            $this->checkAndSaveNode($methodAnnotate, $callable);
            // check and save rule node
            $this->checkAndSaveRule($methodAnnotate, $callable);
        }


    }

    /**
     * handle cmf menu table
     * @param string $act
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function callMenuTab(string $act, array $data = [])
    {
        if (empty($data) && in_array($act, ['insert', 'update', 'delete']))
            return false;
        $model = DB::table('admin_menu');
        switch ($act) {
            case 'find':
                return (array)$model->whereRaw('app = :module and controller = :controller and action = :action', [
                    'module' => $data['app'] ?? $this->module,
                    'controller' => $data['controller'] ?? $this->controller,
                    'action' => $data['action'] ?? $this->action,
                ])->first();
            case 'insert':
                return $model->insert($data);
            case 'update':
                return $model->where('id', $data['id'])->update($data);
            case 'delete':
                // 判断是否存在子节点
                $nodeExists = $model->where('parent_id', $data['id'])->count();
                return empty($nodeExists) ? $model->delete($data['id']) : false;
            default:
                return false;
        }
    }


    /**
     * check and save menu node
     * @param array $annotate
     * @param callable $callable
     * @return bool
     * @throws Exception
     */
    protected function checkAndSaveNode(array $annotate, callable $callable = null): bool
    {
        $data = !is_null($callable) && $callable instanceof Closure ? $callable('menu', 'find', $this->base) : $this->callMenuTab('find');
        if (!empty($data) && !empty($annotate['delete'])) {
            return !is_null($callable) && $callable instanceof Closure ? $callable('menu', 'delete', $data) : $this->callMenuTab('delete', $data);
        }
        $parent = $annotate['parent'] ?? ($this->action != 'defaultPage' ? 'defaultPage' : '');

        $name = $annotate['name'] ?? '';
        // set it to first node when menu set 1 and auth set 0
        if (isset($annotate['menu']) && isset($annotate['auth']) && (int)$annotate['menu'] == 1 && (int)$annotate['auth'] == 0 && $this->action == 'defaultPage' && empty($parent)) {
            $parentId = 0;
        } else {
            // other children's node
            $routeList = explode('/', $parent);
            $count = count($routeList);
            switch ($count) {
                case 2:
                    list($parentModule, $parentController, $parentAction) = [$this->module, $routeList[0], $routeList[1]];
                    break;
                case $count >= 3:
                    list($parentModule, $parentController, $parentAction) = [$routeList[0], $routeList[1], $routeList[2]];
                    break;
                default:
                    list($parentModule, $parentController, $parentAction) = [$this->module, $this->controller, $parent];
            }
            $where = ['app' => $parentModule, 'controller' => $parentController, 'action' => $parentAction];
            $parentRecord = !is_null($callable) && $callable instanceof Closure ? $callable('menu', 'find', $where) : $this->callMenuTab('find', $where);
            $parentId = $parentRecord['id'] ?? 0;
            if (empty($parentId) && $this->action != 'defaultPage') {
                if ($this->isCli) {
                    echo "执行结果：保存节点失败，等待生成父节点后完成\r\n节点名称：{$name}\r\n节点路由：{$this->module}/{$this->controller}/{$this->action}";
                }
                throw new Exception("parent node: {$parentModule}->{$parentController}->{$parentAction} 父节点ID不存在", 521);
            }
        }

        $permissionCodeList = ['query', 'add', 'edit', 'upload', 'download', 'delete', 'export', 'import', 'check', 'uncheck', 'refuse', 'disable', 'enable', 'toggle'];

        $newNode = [
            'app' => $this->module,
            'controller' => $this->controller,
            'action' => $this->action,
            'parent_id' => $parentId,
            'status' => $annotate['menu'] ?? 0,
            'type' => $annotate['auth'] ?? 2,
            'sort' => $annotate['order'] ?? 0,
            'param' => $annotate['param'] ?? '',
            'name' => $name,
            'icon' => $annotate['icon'] ?? '',
            'remark' => $annotate['remark'] ?? '',
            'code' => !empty($annotate['code']) ? $annotate['code'] : (in_array($this->action, $permissionCodeList) ? $this->action : 'query'),
            'rule' => "{$this->module}/{$this->controller}/{$this->action}",

        ];
        // need to insert node into menu table
        if (empty($data)) {
            $result = !is_null($callable) && $callable instanceof Closure ? $callable('menu', 'insert', $newNode) : $this->callMenuTab('insert', $newNode);
        } else {
            // check different node data with old node
            $hasChanged = false;
            foreach ($newNode as $field => $value):
                $dataValue = $data[$field] ?? '';
                if ($value != $dataValue) {
                    $hasChanged = true;
                    break;
                }
            endforeach;
            if (!$hasChanged)
                return false;
            // update new node
            $newNode['id'] = $data['id'];
            $result = !is_null($callable) && $callable instanceof Closure ? $callable('menu', 'update', $newNode) : $this->callMenuTab('update', $newNode);
        }
        if (!$result) {
            $title = !empty($data) ? '生成' : '更新';
            throw new Exception("{$title}菜单节点失败", 500);
        }

        if ($this->isCli)
            echo "执行结果：保存节点成功\r\n节点名称：{$newNode['name']}\r\n节点路由：{$newNode['app']}/{$newNode['controller']}/{$newNode['action']}\r\n";
        return true;
    }

    /**
     * handle cmf rule table
     * @param string $act
     * @param array $data
     * @return mixed
     * @throws Exception
     */
    protected function callRuleTab(string $act, array $data = [])
    {
        if (empty($data) && in_array($act, ['insert', 'update', 'delete']))
            return false;
        $model = Db::table('admin_auth_rule');
        switch ($act) {
            case 'find':
                return (array)$model->whereRaw('app = :module and name = :path', [
                    'module' => $data['app'] ?? $this->module,
                    'path' => $data['name'] ?? "{$this->module}/{$this->controller}/{$this->action}",
                ])->first();
            case 'insert':
                return $model->insert($data);
            case 'update':
                return $model->where('id', $data['id'])->update($data);
            case 'delete':
                return $model->delete($data['id']);
            default:
                return false;
        }
    }

    /**
     * check and save auth rule
     * @param array $annotate
     * @param callable $callable
     * @return bool
     * @throws Exception
     */
    protected function checkAndSaveRule(array $annotate, callable $callable = null): bool
    {
        $data = !is_null($callable) && $callable instanceof Closure ? $callable('rule', 'find', $this->base) : $this->callRuleTab('find');
        if (!empty($data) && !empty($annotate['delete'])) {
            return !is_null($callable) && $callable instanceof Closure ? $callable('rule', 'delete', $data) : $this->callRuleTab('delete', $data);
        }
        $newNode = [
            'status' => 1,
            'app' => $this->module,
            'type' => 'admin_url',
            'name' => $this->base['name'],
            'param' => $annotate['param'] ?? '',
            'title' => $annotate['name'],
            'condition' => '',
        ];
        if (empty($data)) {
            $result = !is_null($callable) && $callable instanceof Closure ? $callable('rule', 'insert', $newNode) : $this->callRuleTab('insert', $newNode);
        } else {
            $hasChanged = false;
            foreach ($newNode as $field => $value):
                $dataValue = $data[$field] ?? '';
                if ($value != $dataValue) {
                    $hasChanged = true;
                    break;
                }
            endforeach;
            if (!$hasChanged)
                return false;
            $newNode['id'] = $data['id'];
            $result = !is_null($callable) && $callable instanceof Closure ? $callable('rule', 'update', $newNode) : $this->callRuleTab('update', $newNode);
        }
        if (!$result) {
            $title = !empty($data) ? '生成' : '更新';
            throw new Exception("{$title}权限节点失败", 500);
        }
        return true;

    }
}