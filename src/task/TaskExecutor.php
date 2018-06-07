<?php

namespace mix\task;

use mix\base\BaseObject;
use mix\helpers\ProcessHelper;

/**
 * 任务执行器类
 * @author 刘健 <coder.liu@qq.com>
 */
class TaskExecutor extends BaseObject
{

    // 流水线模式
    const MODE_ASSEMBLY_LINE = 0;

    // 推送模式
    const MODE_PUSH = 1;

    // 采集模式
    const  MODE_ACQUISITION = 2;

    // 程序名称
    public $name = '';

    // 执行模式
    public $mode = self::MODE_ASSEMBLY_LINE;

    // 左进程数
    public $leftProcess = 0;

    // 中进程数
    public $centerProcess = 0;

    // 右进程数
    public $rightProcess = 0;

    // POP退出等待时间 (秒)
    public $popExitWait = 3;

    // 左进程启动事件回调函数
    protected $_onLeftStart;

    // 中进程启动事件回调函数
    protected $_onCenterStart;

    // 右进程启动事件回调函数
    protected $_onRightStart;

    // 左进程集合
    protected $_leftProcesses = [];

    // 中进程集合
    protected $_centerProcesses = [];

    // 右进程集合
    protected $_rightProcesses = [];

    // 工作进程集合
    protected $_workers = [];

    // 消息队列键名
    protected $_messageKey;

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 初始化
        $this->_messageKey = crc32(uniqid('', true));
    }

    // 启动
    public function start()
    {
        ProcessHelper::setTitle("{$this->name} master");
        $this->createProcesses();
        $this->subProcessWait();
    }

    // 注册Server的事件回调函数
    public function on($event, callable $callback)
    {
        switch ($event) {
            case 'LeftStart':
                $this->_onLeftStart = $callback;
                break;
            case 'CenterStart':
                $this->_onCenterStart = $callback;
                break;
            case 'RightStart':
                $this->_onRightStart = $callback;
                break;
        }
    }

    // 创建全部进程
    protected function createProcesses()
    {
        // 右中左，创建顺序不能更换
        for ($i = 0; $i < $this->rightProcess; $i++) {
            $this->createProcess('right', $i);
        }
        for ($i = 0; $i < $this->centerProcess; $i++) {
            $this->createProcess('center', $i);
        }
        for ($i = 0; $i < $this->leftProcess; $i++) {
            $this->createProcess('left', $i);
        }
    }

    // 创建进程
    protected function createProcess($processType, $number)
    {
        // 定义变量
        switch ($processType) {
            case 'right':
                $callback  = $this->_onRightStart;
                $taskClass = '\mix\task\RightProcess';
                $next      = null;
                break;
            case 'center':
                $callback  = $this->_onCenterStart;
                $taskClass = '\mix\task\CenterProcess';
                $next      = null;
                if (!empty($temp = $this->_rightProcesses)) {
                    $next = array_pop($temp);
                }
                break;
            case 'left':
                $callback  = $this->_onLeftStart;
                $taskClass = '\mix\task\LeftProcess';
                $temp      = $this->_centerProcesses;
                $next      = array_pop($temp);
                break;
        }
        $mode        = $this->mode;
        $mpid        = ProcessHelper::getPid();
        $popExitWait = $this->popExitWait;
        // 创建进程对象
        $process = new \Swoole\Process(function ($worker) use ($callback, $taskClass, $next, $mode, $mpid, $popExitWait, $processType, $number) {
            try {
                ProcessHelper::setTitle("{$this->name} {$processType} #{$number}");
                $taskProcess = new $taskClass([
                    'mode'        => $mode,
                    'number'      => $number,
                    'mpid'        => $mpid,
                    'pid'         => $worker->pid,
                    'popExitWait' => $popExitWait,
                    'current'     => $worker,
                    'next'        => $next,
                ]);
                call_user_func($callback, $taskProcess);
            } catch (\Exception $e) {
                \Mix::app()->error->handleException($e);
            }
        }, false, false);
        // 开启进程消息队列
        switch ($processType) {
            case 'right':
                $process->useQueue($this->_messageKey + 2, 2);
                break;
            case 'center':
                $process->useQueue($this->_messageKey + 1, 2);
                break;
        }
        // 启动
        $pid = $process->start();
        // 保存实例
        $this->_workers[$pid] = [$processType, $number];
        switch ($processType) {
            case 'right':
                $this->_rightProcesses[$pid] = $process;
                break;
            case 'center':
                $this->_centerProcesses[$pid] = $process;
                break;
            case 'left':
                $this->_leftProcesses[$pid] = $process;
                break;
        }
    }

    // 重启进程
    protected function rebootProcess($ret)
    {
        $pid = $ret['pid'];
        if (isset($this->_workers[$pid])) {
            // 重建进程
            list($processType, $number) = $this->_workers[$pid];
            $this->createProcess($processType, $number);
            // 删除旧引用
            unset($this->_workers[$pid]);
            unset($this->_rightProcesses[$pid]);
            unset($this->_centerProcesses[$pid]);
            unset($this->_leftProcesses[$pid]);
            // 返回
            return;
        }
        throw new \mix\exceptions\TaskException('RebootProcess Error: no pid.');
    }

    // 回收结束运行的子进程，并重启子进程
    protected function subProcessWait()
    {
        while (true) {
            $ret = \Swoole\Process::wait();
            if ($ret) {
                $this->rebootProcess($ret);
            }
        }
    }

}
