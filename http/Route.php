<?php

namespace mix\http;

use mix\base\Component;

/**
 * Route组件
 * @author 刘健 <coder.liu@qq.com>
 */
class Route extends Component
{

    // 默认变量规则
    public $defaultPattern = '[\w-]+';

    // 路由变量规则
    public $patterns = [];

    // 路由规则
    public $rules = [];

    // URL后缀
    public $suffix = '';

    // 转化后的路由规则
    protected $_rules = [];

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize();
        // 初始化
        $this->initialize();
    }

    // 初始化，生成路由数据，将路由规则转换为正则表达式，并提取路由参数名
    public function initialize()
    {
        // URL 目录处理
        foreach ($this->rules as $rule => $route) {
            if (strpos($rule, ':controller') !== false && strpos($rule, ':action') !== false) {
                $controller = dirname($rule);
                $prefix     = dirname($controller);
                $prefix     = $prefix == '.' ? '' : $prefix;
                // 增加上两级的路由
                $rules = [
                    $controller => [$controller, 'Index'],
                    $prefix     => [($prefix == '' ? '' : "{$prefix}/") . 'Index', 'Index'],
                ];
                // 附上中间件
                if (isset($route['middleware'])) {
                    $rules[$controller]['middleware'] = $route['middleware'];
                    $rules[$prefix]['middleware']     = $route['middleware'];
                }
                $this->rules += $rules;
            }
        }
        // 转正则
        foreach ($this->rules as $rule => $route) {
            // method
            if ($blank = strpos($rule, ' ')) {
                $method = substr($rule, 0, $blank);
                $method = "(?:{$method}) ";
                $rule   = substr($rule, $blank + 1);
            } else {
                $method = '(?:CLI|GET|POST|PUT|PATCH|DELETE|HEAD|OPTIONS) ';
            }
            // path
            $fragment = explode('/', $rule);
            $names    = [];
            foreach ($fragment as $k => $v) {
                $prefix = substr($v, 0, 1);
                $fname  = substr($v, 1);
                if ($prefix == ':') {
                    if (isset($this->patterns[$fname])) {
                        $fragment[$k] = '(' . $this->patterns[$fname] . ')';
                    } else {
                        $fragment[$k] = '(' . $this->defaultPattern . ')';
                    }
                    $names[] = $fname;
                }
            }
            $this->_rules['/^' . $method . implode('\/', $fragment) . '\/*$/i'] = [$route, $names];
        }
    }

    // 匹配功能，由于路由歧义，会存在多条路由规则都可匹配的情况
    public function match($action)
    {
        // 去除 URL 后缀
        if ($position = strpos($action, $this->suffix)) {
            $action = substr($action, 0, $position);
        }
        // 匹配
        $result = [];
        foreach ($this->_rules as $pattern => $item) {
            if (preg_match($pattern, $action, $matches)) {
                list($route, $names) = $item;
                $queryParams = [];
                // 提取路由查询参数
                foreach ($names as $k => $v) {
                    $queryParams[$v] = $matches[$k + 1];
                }
                // 替换路由中的变量
                $fragments   = explode('/', $route[0]);
                $fragments[] = $route[1];
                foreach ($fragments as $k => $v) {
                    $prefix = substr($v, 0, 1);
                    $fname  = substr($v, 1);
                    if ($prefix == ':') {
                        if (isset($queryParams[$fname])) {
                            $fragments[$k] = $queryParams[$fname];
                        }
                    }
                }
                // 记录参数
                $shortAction = array_pop($fragments);
                $shortClass  = implode('\\', $fragments);
                $result[]    = [[$shortClass, $shortAction, 'middleware' => isset($route['middleware']) ? $route['middleware'] : []], $queryParams];
            }
        }
        return $result;
    }

}