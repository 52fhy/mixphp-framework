<?php

namespace mix\base;

/**
 * App类
 * @author 刘健 <coder.liu@qq.com>
 *
 * @property \mix\base\Log $log
 * @property \mix\console\Input $input
 * @property \mix\console\Output $output
 * @property \mix\http\Route $route
 * @property \mix\http\Request|\mix\http\compatible\Request $request
 * @property \mix\http\Response|\mix\http\compatible\Response $response
 * @property \mix\http\Error|\mix\console\Error $error
 * @property \mix\http\Token $token
 * @property \mix\http\Session $session
 * @property \mix\http\Cookie $cookie
 * @property \mix\client\PDO $rdb
 * @property \mix\client\Redis $redis
 * @property \mix\websocket\TokenReader $tokenReader
 * @property \mix\websocket\SessionReader $sessionReader
 * @property \mix\websocket\MessageHandler $messageHandler
 */
class Application extends BaseObject
{

    // 基础路径
    public $basePath = '';

    // 组件配置
    public $components = [];

    // 类库配置
    public $libraries = [];

    // 组件容器
    protected $_components;

    // 组件命名空间
    protected $_componentNamespace;

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 快捷引用
        \Mix::setApp($this);
        // 错误注册
        \mix\base\Error::register();
    }

    // 设置组件命名空间
    public function setComponentNamespace($namespace)
    {
        $this->_componentNamespace = $namespace;
    }

    // 创建对象
    public function createObject($name)
    {
        return \Mix::createObject($this->libraries[$name], $name);
    }

    // 装载组件
    public function loadComponent($name, $return = false)
    {
        // 未注册
        if (!isset($this->components[$name])) {
            throw new \mix\exceptions\ComponentException("组件不存在：{$name}");
        }
        // 使用配置创建新对象
        $object = \Mix::createObject($this->components[$name], $name);
        // 组件效验
        if (!($object instanceof Component)) {
            throw new \mix\exceptions\ComponentException("不是组件类型：{$this->components[$name]['class']}");
        }
        if ($return) {
            return $object;
        }
        // 装入容器
        $this->_components[$name] = $object;
    }

    // 获取配置目录路径
    public function getConfigPath()
    {
        return $this->basePath . 'config' . DIRECTORY_SEPARATOR;
    }

    // 获取运行目录路径
    public function getRuntimePath()
    {
        return $this->basePath . 'runtime' . DIRECTORY_SEPARATOR;
    }

}
