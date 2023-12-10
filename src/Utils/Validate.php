<?php


namespace Crastlin\LaravelAnnotation\Utils;


use Crastlin\LaravelAnnotation\Utils\Traits\SingletonTrait;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Validator as Validation;
use Throwable;

/**
 * Class Validate
 * @package App\Validator
 * @author crastlin@163.com
 * @date 2022-01
 */
class Validate implements \Illuminate\Contracts\Validation\Validator
{
    use SingletonTrait;

    /**
     * @var string[] 允许访问的对象
     */
    protected $allowAccessProperties = ['*'];

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
        'digits_between' => ':attribute 必须在 :min 和 :max 位之间',
        'between' => ':attribute不在允许范围',
        'mix' => ':attribute最小值为:value',
        'max' => ':attribute最大值为:value',
        'gt' => ':attribute必须大于:value',
        'lt' => ':attribute必须小于:value',
        'gte' => ':attribute必须大于等于:value',
        'lte' => ':attribute必须小于等于:value',
        'chs' => ':attribute必须为中文',
    ],
        $attributes = [],
        $fails = false,
        $errors;

    /**
     * @var Validation $validator
     */
    protected $validator;


    /**
     * Validate constructor.
     * @param array ...$ruleList
     * @throws Throwable
     */
    function __construct(array ...$ruleList)
    {
        if (!empty($ruleList)) {
            $rules = $ruleList[0] ?? [];
            $messages = $ruleList[1] ?? [];
            $attributes = $ruleList[2] ?? [];
            $this->rules = !empty($rules) ? array_merge($this->rules, $rules) : $this->rules;
            $this->messages = !empty($messages) ? array_merge($this->messages, $messages) : $this->messages;
            $this->attributes = !empty($attributes) ? array_merge($this->attributes, $attributes) : $this->attributes;
        }
        $this->check();
    }


    function __get(string $name)
    {
        if (!empty($this->allowAccessProperties) && (in_array('*', $this->allowAccessProperties) || in_array($name, $this->allowAccessProperties)))
            return !empty($this->{$name}) ? $this->{$name} : null;

        return null;
    }

    function __isset($name)
    {
        if (!empty($this->allowAccessProperties) && (in_array('*', $this->allowAccessProperties) || in_array($name, $this->allowAccessProperties)))
            return isset($this->{$name});
        return false;
    }

    /**
     * set validator's messages
     *
     * @param array $message
     * @param bool $recover
     */
    function setMessage(array $message, bool $recover = false)
    {
        if (!empty($message))
            $this->messages = $recover ? $message : array_merge($this->messages, $message);
    }

    function setCallbackMessage(string $field, string $message): void
    {
        $this->validator->setFallbackMessages(["{$field}.callback" => $message]);
    }

    function append(string $field, $rule, string $attribute, array $message = [])
    {
        $this->rules = array_merge($this->rules, [$field => $rule]);
        $this->attributes[$field] = $attribute;
        $this->setMessage($message);
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
     * @example 错误信息例子2 protected $messages = ['example_field.callback' => 'example 验证不通过'];// 指定字段错误信息
     * @example 自定义方法例子
     * // param $field 字段名称
     * // param $value 验证的数据
     * protected function checkExample(string $field, $value): bool
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
        Validator::extend('callback', function (string $attribute, $value, array $parameters) {
            $action = array_shift($parameters);
            array_unshift($parameters, $value, $attribute);
            return call_user_func_array([$this, $action], $parameters);
        }, ':attribute验证失败');
        $this->validator = Validator::make($this->data, $this->rules, $this->messages, $this->attributes);
        return $this->validator;
    }


    /**
     * make validate instance
     * @param string $ruleClass
     * @param ?array $data
     * @param Validate $validate
     * @return \Illuminate\Contracts\Validation\Validator
     * @throws Throwable
     */
    static function make(string $ruleClass, ?array $data, &$validate = null): \Illuminate\Contracts\Validation\Validator
    {
        $validate = self::singleton($ruleClass)->setData($data);
        return $validate->validate();
    }

    /**
     * make validate instance
     * @param Validate $validator
     * @param ?array $data
     * @return \Illuminate\Contracts\Validation\Validator
     * @throws Throwable
     */
    static function makeByValidator(Validate $validator, ?array $data): \Illuminate\Contracts\Validation\Validator
    {
        return $validator->setData($data)->validate();
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

    protected function checkNumericWithLenRegex(int $min = 0, int $max = 0, bool $isPositive = false): string
    {
        $lenRex = '';
        if ($min > 0)
            $lenRex = "{$min},";
        if ($min > 0 && $max > $min)
            $lenRex .= "{$max}";
        if (!empty($lenRex))
            $lenRex = '{' . $lenRex . '}';
        else
            $lenRex = '+';
        $positiveRex = $isPositive ? '(-)?' : '';
        return '~^' . $positiveRex . '\d' . $lenRex . '$~';
    }

    // 当字段存在时验证整数类型
    protected function checkIntegerWhenExists(...$parameters): bool
    {
        list($value, $field) = $parameters;
        if (!isset($this->data[$field]) || empty($value))
            return true;
        $regex = $this->checkNumericWithLenRegex(!empty($parameters[2]) ? (int)$parameters[2] : 0, !empty($parameters[3]) ? (int)$parameters[3] : 0);
        if (!preg_match($regex, $value))
            return false;

        return true;
    }

    // 当字段存在时验证正整数类型
    protected function checkPositiveIntegerWhenExists(...$parameters): bool
    {
        list($value, $field) = $parameters;
        if (!isset($this->data[$field]) || empty($value))
            return true;
        $regex = $this->checkNumericWithLenRegex(!empty($parameters[2]) ? (int)$parameters[2] : 0, !empty($parameters[3]) ? (int)$parameters[3] : 0, true);
        if (!preg_match($regex, $value))
            return false;
        return true;
    }
}
