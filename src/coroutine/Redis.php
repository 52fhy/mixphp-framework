<?php

namespace mix\coroutine;

use mix\base\Component;

/**
 * Redis组件
 * @author 刘健 <coder.liu@qq.com>
 */
class Redis extends Component
{

    // 主机
    public $host = '';

    // 端口
    public $port = '';

    // 数据库
    public $database = '';

    // 密码
    public $password = '';

    /**
     * 连接池
     * @var \mix\coroutine\PoolManager
     */
    public $pool;

    /**
     * redis对象
     * @var \Swoole\Coroutine\Redis
     */
    protected $_redis;


    // 析构事件
    public function onDestruct()
    {
        parent::onDestruct();
        // 关闭连接
        $this->disconnect();
    }

    // 创建连接
    protected function createConnection()
    {
        $redis = new \Swoole\Coroutine\Redis();
        if (!$redis->connect($this->host, $this->port)) {
            throw new \mix\exceptions\ConnectionException('redis connection failed');
        }
        $redis->auth($this->password);
        $redis->select($this->database);
        $this->pool->activeCountIncrement();
        return $redis;
    }

    // 获取连接
    protected function getConnection()
    {
        if ($this->pool->getQueueCount() > 0) {
            var_dump('getQueueCount > 0');
            return $this->pool->pop();
        }
        if ($this->pool->getCurrentCount() >= $this->pool->max) {
            var_dump('getCurrentCount >= max');
            return $this->pool->pop();
        }
        var_dump('createConnection');
        return $this->createConnection();
    }

    // 连接
    protected function connect()
    {
        $this->_redis = $this->getConnection();
    }

    // 关闭连接
    public function disconnect()
    {
        if (isset($this->_redis)) {


            $this->pool->push($this->_redis);
            $this->pool->activeCountDecrement();
            $this->_redis = null;

            var_dump('disconnect');
            var_dump("QueueCount: " . $this->pool->getQueueCount());
            var_dump("ActiveCount: " . $this->pool->getActiveCount());
            var_dump("CurrentCount: " . $this->pool->getCurrentCount());
            var_dump('----------');

        }
    }

    // 自动连接
    protected function autoConnect()
    {
        if (!isset($this->_redis)) {
            $this->connect();
        }
    }

    // 执行命令
    public function __call($name, $arguments)
    {
        // 自动连接
        $this->autoConnect();
        // 执行命令
        return call_user_func_array([$this->_redis, $name], $arguments);
    }

}
