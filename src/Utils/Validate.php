<?php


namespace Crastlin\LaravelAnnotation\Utils;

use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Class Validate
 * @package App\Validator
 * @author crastlin@163.com
 * @date 2022-01
 */
class Validate implements \Illuminate\Contracts\Validation\Validator
{

    protected $data = [], $rules = [],
        $messages = [
        'required' => ':attribute不能为空',
        'numeric' => ':attribute必须为数字类型',
        'regex' => ':attribute格式不正确',
        'alpha_num' => ':attribute必须为字母或数字类型',
        'in' => ':attribute必须为指定数据',
        'email' => '邮箱格式不正确',
        'callback' => ':attribute为空或不正确',
        'integer' => ':attribute必须为整数',
    ],
        $attributes = [],
        $fails = false,
        $errors,
        $callbackMessage;

    /**
     * Validate constructor.
     * @param array ...$ruleList
     * @throws Throwable
     */
    function __construct(array ...$ruleList)
    {
        if (!empty($ruleList)) {
            list($rules, $messages, $attributes) = $ruleList;
            $this->rules = !empty($rules) ? array_merge($this->rules, $rules) : $this->rules;
            $this->messages = !empty($messages) ? array_merge($this->messages, $messages) : $this->messages;
            $this->attributes = !empty($attributes) ? array_merge($this->attributes, $attributes) : $this->attributes;
        }
        $this->check();
    }


    /**
     * set validator's messages
     *
     * @param array $message
     * @param bool $recover
     */
    function setMessage(array $message, $recover = false)
    {
        if (!empty($message))
            $this->messages = $recover ? $message : array_merge($this->messages, $message);
    }

    /**
     * @throws Throwable
     */
    function check()
    {
        if (empty($this->rules))
            throw new \Exception(static::class . ': 未配置rules数据');
        if (empty($this->messages))
            throw new \Exception(static::class . ': 未配置messages数据');
    }

    function setData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * validate data
     * @return \Illuminate\Contracts\Validation\Validator
     * @example 使用自定义验证说明：
     * @example 在 rules中定义规则 callback:{验证类定义的方法名，注意方法须修饰为protected}
     * @example 在自定义方法中，可以在$this->data中获取输入数据
     * @example 自定义方法返回类型为bool，true为通过验证，false则验证失败
     * @example 报错信息可以在message中定义 callback或 {验证字段名}.callback指定，或在方法中定义$this->callbackMessage
     * @example 验证规则例子  protected $rules = ['example_field' => 'callback:checkExample|...其它验证'];
     * @example 错误信息例子  protected $messages = ['callback' => ':attribute验证不通过'];// 通用错误信息
     * @example 错误信息例子2 protected $messages = ['example_field.callback' => 'example 验证不通过'];// 通用错误信息
     * @example 自定义方法例子
     * protected function checkExample(): bool
     * {
     *     if(!isset($this->data['checkExample']) || ... 其它验证){
     *        $this->callbackMessage = 'xxx验证不通过'; // 在方法中定义错误信息，如果定义了这个信息，则 $this->messages中可以不再定义
     *        return false; // 返回false 验证不通过
     *     }
     *   return true; // 验证通过
     * }
     */
    function validate()
    {
        // 验证自定义回调验证
        foreach ($this->rules as $field => $rule):
            if (is_string($rule) && strpos($rule, 'callback') === false)
                continue;
            $format = is_array($rule) ? 'array' : 'string';
            $ruleList = $format == 'array' ? $rule : explode('|', $rule);
            foreach ($ruleList as $key => $ru):
                if (strpos($ru, 'callback') === false)
                    continue;
                $ruList = explode(':', $ru);
                $result = true;
                if (!empty($ruList[0]) && $ruList[0] == 'callback') {
                    if (empty($ruList[1]) || !method_exists($this, $ruList[1]))
                        throw new \Exception(static::class . '::' . $ruList[1] . ' is not defined');
                    $result = call_user_func_array([$this, $ruList[1]], [$field]);
                    unset($ruleList[$key]);
                    if (isset($this->messages["{$field}.callback"]))
                        unset($this->messages["{$field}.callback"]);
                }
                if (!$result) {
                    $this->fails = true;
                    $name = $this->attributes[$field] ?? $field;
                    $message = $this->callbackMessage ?: ($this->messages["{$field}.callback"] ?? ($this->messages['callback'] ?? 'is not passed'));
                    $message = str_replace(':attribute', $name, $message);
                    $this->errors[] = $message;
                    return $this;
                }
                $this->callbackMessage = '';
            endforeach;
            $this->rules[$field] = $format == 'array' ? $ruleList : join('|', $ruleList);
        endforeach;
        return Validator::make($this->data, $this->rules, $this->messages, $this->attributes);
    }


    /**
     * make validate instance
     * @param string $ruleClass
     * @param array $data
     * @return \Illuminate\Contracts\Validation\Validator
     * @throws Throwable
     */
    static function make(string $ruleClass, ?array $data): \Illuminate\Contracts\Validation\Validator
    {
        return self::singleton($ruleClass)->setData($data)->validate();
    }

    public function getMessageBag()
    {
        // TODO: Implement getMessageBag() method.
    }

    public function validated()
    {
        return empty($this->errors);
    }

    public function fails()
    {
        return $this->fails;
    }

    public function failed()
    {
        return $this->fails;
    }

    public function sometimes($attribute, $rules, callable $callback)
    {
        // TODO: Implement sometimes() method.
    }

    public function after($callback)
    {
        // TODO: Implement after() method.
    }

    public function errors()
    {
        return $this;
    }

    function first()
    {
        return $this->errors[0] ?? '';
    }
}
