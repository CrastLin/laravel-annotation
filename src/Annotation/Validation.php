<?php

namespace Crastlin\LaravelAnnotation\Annotation;

use Crastlin\LaravelAnnotation\Utils\Validate;
use Illuminate\Cache\RedisLock;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Crastlin\LaravelAnnotation\Facades\Injection;
use PhpParser\Node\Expr\BinaryOp;
use ReflectionMethod;
use Exception;

/**
 * @package Validation
 * @author crastlin@163.com
 * @date 2023-12-23
 * @example @\Crastlin\LaravelAnnotation\Annotation\Annotations\Validation(UserValidator::class);
 * @example @\Crastlin\LaravelAnnotation\Annotation\Annotations\Validation(name="username", rule="reqyured|regex:~^\w{5,10}$~")
 * @example @Required(name="username", msg="please input your :attribute")
 * @example @In(name="sex", rule="1,2", msg=":attribute is not in 1,2")
 */
class Validation
{
    protected $attributes = 'class|field|attribute|rule|rules|message|messages';

    protected $defaultValueField = 'field';

    protected $annotateName;

    protected $config;


    public function __construct(?array $config = [])
    {
        $validationClassPath = __DIR__ . '/Annotations/Validation';
        $validationClassList = scandir($validationClassPath);
        $annotationFiledList = ['Validation'];
        foreach ($validationClassList as $validationClass) {
            if ($validationClass == '.' || $validationClass == '..' || is_dir($validationClass))
                continue;
            $annotationClass = substr($validationClass, 0, strpos($validationClass, '.'));
            array_push($annotationFiledList, $annotationClass);
        }
        $this->annotateName = join('|', $annotationFiledList);
        $this->config = $config;
    }


    /**
     * parse and save annotation information
     * @param \ReflectionClass $reflect
     * @return array
     */
    protected function getAnnotationInformation(\ReflectionClass $reflect): array
    {
        $conf = config('annotation');
        $rootPath = $conf && !empty($conf['annotation_path']) ? $conf['annotation_path'] : 'data/';
        $rootPath = base_path("{$rootPath}validation/");
        $class = $reflect->getName();
        $classFile = $reflect->getFileName();
        $namespace = $reflect->getNamespaceName();
        $subList = explode('\\', $class);
        $mtime = filemtime($classFile);
        $name = array_pop($subList);
        $path = $rootPath . join('/', $subList) . '/';
        $hasPath = is_dir($path);
        $file = $path . $name . '.php';
        $hasFile = $hasPath && is_file($file);
        $cacheData = $hasFile ? require_once $file : [];
        if (!$hasFile || empty($cacheData['mtime']) || $cacheData['mtime'] != $mtime) {
            $redis = Redis::connection();
            $locker = new RedisLock($redis, "sync_validation_cache:{$class}", 60);
            try {
                if (!$hasPath)
                    mkdir($path, 0755, true);
                $cacheData = ['mtime' => $mtime, 'maps' => []];
                $validatorPath = $this->config && !empty($this->config['interceptor']['validate']['namespace']) ? $this->config['interceptor']['validate']['namespace'] : '';
                foreach ($reflect->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                    $annotationList = $this->matchAnnotation($method->getDocComment());
                    if (empty($annotationList))
                        continue;
                    $cacbeList = [];
                    $methodName = $method->getName();
                    foreach ($annotationList as $annotation) {
                        $validatorName = $annotation['validator'];
                        $field = !empty($annotation['field']) ? $annotation['field'] : (!empty($annotation['value']) ? $annotation['value'] : '');
                        if (empty($field) && empty($annotation['class']))
                            throw new \Exception("{$class}::{$methodName} validation annotation: field of {$validatorName} not defined");
                        if ($validatorName == 'Validation' && empty($annotation['class']) && empty($annotation['rule']) && empty($annotation['rules']))
                            throw new \Exception("{$class}::{$methodName} validation annotation: rule of {$validatorName} not defined | annotation: " . json_encode($annotation, 256));
                        $cacheList[] = [
                            'validator' => $validatorName,
                            'field' => $field,
                            'class' => !empty($annotation['class']) ? $validatorPath . '\\' . ucfirst(rtrim($annotation['class'], '::class')) : '',
                            'rule' => $annotation['rule'] ?? '',
                            'rules' => $annotation['rules'] ?? [],
                            'attribute' => $annotation['attribute'] ?? $field,
                            'message' => $annotation['message'] ?? '',
                            'messages' => $annotation['messages'] ?? [],
                        ];
                    }
                    $cacheData['maps'][$methodName] = $cacheList;
                }
                if ($locker->acquire()) {
                    file_put_contents($file, "<?php\r\n/**\r\n  * @author crastlin@163.com\r\n  * @date 2022-12-24\r\n  * @example @Validation(field=\"name\", rule=\"required\", attribute=\"user name\", messages={\"required\": \":attribute is empty\"})\r\n  * @example @Validation(class=\"MemberValidator\")\r\n*/\r\nreturn " . var_export($cacheData, true) . ";");
                    $locker->release();
                }
            } catch (\Throwable $exception) {
                echo "file: " . $exception->getFile() . ' -> ' . $exception->getLine() . PHP_EOL;
                echo 'message: ' . $exception->getMessage();
                $locker->release();
                Log::error('sync create route mapping was failed: ' . $exception->getMessage());
                throw new $exception;
            }
        }
        return $cacheData;
    }


    // match validatuin annotations of method
    protected function matchAnnotation(string $content): ?array
    {
        $content = $content ? str_replace('Validation\\', '', $content) : '';
        if (empty($content))
            return null;
        if (!preg_match_all("#@({$this->annotateName})\s*(\(\s*(.*)\))#i", $content, $allMatches)) {
            return null;
        }
        $validatorMatchList = $allMatches[1] ?? [];
        $annotationMatchList = $allMatches[3] ?? [];
        if (empty($validatorMatchList) || empty($annotationMatchList))
            return [];
        if (count($validatorMatchList) != count($annotationMatchList))
            throw new \Exception("验证器注解匹配解析失败", 500);
        $patternList = [
            ['type' => 'object', 'pattern' => "({$this->attributes})\s*=\s*(\{.+})"],
            ['type' => 'array', 'pattern' => "({$this->attributes})\s*=\s*(\[.+])"],
            ['type' => 'any', 'pattern' => "(({$this->attributes})\s*=\s*([^\s]+))+"],
        ];
        $matchList = [];
        foreach ($annotationMatchList as $k => $annotationMatch) {
            $validator = $validatorMatchList[$k];
            $validateDat = ['validator' => $validator, 'class' => '', 'field' => '', 'rule' => '', 'rules' => [], 'attribute' => '', 'message' => '', 'messages' => []];
            if (strpos($annotationMatch, '=') === false) {
                $validateDat[$this->defaultValueField] = $annotationMatch;
                $matchList[] = $validateDat;
                continue;
            }
            // 匹配优化顺序：json object -> json array -> any
            foreach ($patternList as $pattern) {
                if (!preg_match_all("#{$pattern['pattern']}#", $annotationMatch, $matches))
                    continue;
                if (in_array($pattern['type'], ['object', 'array'])) {
                    $annotationMatch = str_replace($matches[0][0], '', $annotationMatch);
                    if (empty($matches[1][0]) || !array_key_exists($matches[1][0], $validateDat))
                        continue;
                    $value = !empty($matches[2][0]) ? (substr($matches[2][0], -1, 1) == ',' ? substr($matches[2][0], 0, strlen($matches[2][0]) - 1) : $matches[2][0]) : '';
                    $value = str_replace('\\', '\\\\', $value);
                    $validateDat[$matches[1][0]] = !empty($value) ? json_decode($value, true) : [];
                } else {
                    if (empty($matches[2]))
                        break;
                    foreach ($matches[2] as $mk => $field) {
                        if (empty($field) || !array_key_exists($field, $validateDat))
                            continue;
                        $value = !empty($matches[3]) && isset($matches[3][$mk]) ? (substr($matches[3][$mk], -1, 1) == ',' ? substr($matches[3][$mk], 0, strlen($matches[3][$mk]) - 1) : $matches[3][$mk]) : '';
                        $validateDat[$field] = !empty($value) ? str_replace(['"', '\''], '', $value) : $validateDat[$field];
                    }
                }
            }
            $matchList[] = $validateDat;
        }
        return $matchList;
    }

    protected function humpToUnderline(string $string, ?bool $toUpper = false): string
    {
        $string = preg_replace('/(?<=[a-z0-9])([A-Z])/', '_${1}', $string);
        return $toUpper ? strtoupper($string) : strtolower($string);
    }

    // create validator
    function runValidation(string $class, string $action, array $data): string
    {
        $reflect = Injection::exists('reflectionClass') ? Injection::take('reflectionClass') : null;
        if (!$reflect || !$reflect instanceof \ReflectionClass || $reflect->getName() != $class) {
            $reflect = new \ReflectionClass($class);
        }
        $methodsCache = $this->getAnnotationInformation($reflect);
        if (empty($methodsCache['maps']) || !array_key_exists($action, $methodsCache['maps']))
            return '';
        if (!empty($methodsCache['maps'][$action])) {
            $aliasSet = [
                'is_array' => 'array',
                'mobile' => 'regex:~^1\d{10}$~',
                'mobile_international' => 'regex:~^(\+\d{2,3}-*)?1\d{10}$~',
                'id_card' => 'regex:~^[0-9]{15,18}(X)?$~i',
                'simple_chinese' => 'regex:~^[\x{4e00}-\x{9fa5}]+$~iu',
            ];
            $rules = $messages = $attributes = [];
            foreach ($methodsCache['maps'][$action] as $validator) {
                if ($validator['validator'] == 'Validation') {
                    if (!empty($validator['class'])) {
                        $class = '\\' . $validator['class'];
                        if (!class_exists($validator['class']))
                            throw new Exception("Validation Class: {$class} is not exists");
                        $validate = new $class();
                        if (!$validate instanceof Validate)
                            throw new Exception("Validation Class: {$class} must instanceof \Crastlin\LaravelAnnotation\Utils\Validate");
                        $validate = $validate->setData($data)->validate();
                        if ($validate->fails())
                            return $validate->errors()->first();
                        continue;
                    } else {
                        $rulesList = $rules[$validator['field']] ?? [];
                        if (!empty($validator['rules'])) {
                            $rulesList = array_merge($rulesList, $validator['rules']);
                        } else {
                            $ruleList = !empty($validator['rule']) ? explode('|', $validator['rule']) : [];
                            $rulesList = !empty($ruleList) ? array_merge($rulesList, $ruleList) : $rulesList;
                        }
                        $rules[$validator['field']] = $rulesList;
                        if (!empty($validator['messages'])) {
                            $messages = array_merge($messages, $validator['messages']);
                        } else {
                            if (!empty($validator['message'])) {
                                $messageList = explode('|', $validator['message']);
                                foreach ($rulesList as $rk => $rule) {
                                    $ruleName = explode(':', $rule)[0];
                                    if (!empty($messageList[$rk]))
                                        $messages["{$validator['field']}.{$ruleName}"] = $messageList[$rk];
                                }
                            }
                        }
                    }
                } else {
                    $rule = $this->humpToUnderline($validator['validator']);
                    $rule = $aliasSet[$rule] ?? $rule;
                    if (!isset($rules[$validator['field']]))
                        $rules[$validator['field']] = [];
                    $rules[$validator['field']][] = $rule;
                    $ruleName = explode(':', $rule)[0];
                    $messages["{$validator['field']}.{$ruleName}"] = $validator['message'];
                }
                $attributes[$validator['field']] = !empty($validator['attribute']) ? $validator['attribute'] : $validator['field'];
            }
            if (!empty($rules)) {
                $messages = array_filter($messages, function ($message) {
                    return !empty($message);
                });
                $validate = new Validate($rules, $messages, $attributes);
                $validate = $validate->setData($data)->validate();
                if ($validate->fails())
                    return $validate->errors()->first();
            }
        }
        return '';
    }

}