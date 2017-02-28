<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2017/2/4
 * Time: 14:09
 */
namespace App\Model\Cache\Redis;

use Redis;
use App\Model\DB\MemberTask;

class TaskRedis extends CommonRedis
{

    private $taskRedis;

    public function __construct()
    {
        $this->dbIndex = 7;
        parent::__construct();
    }

    /**
     * 获取所有任务
     * @return array
     */
    public function queryAllTasks($request)
    {
        $this->toSlave();
        $memberTasks = $this->queryAllByRedis($request->get('page', 1));
        return $memberTasks;
    }

    /**
     * 查询任务, 通过Redis分页
     * @param int $page 当前页
     * @param string $sortFieldName 排序字段名称
     * @param array $options
     * @return array
     */
    public function queryAllByRedis($page = 1, $sortFieldName = 'z_mb_list', array $options = [])
    {
        $keys = Redis::zRevRange($sortFieldName, ($page - 1) * $this->perPage, $page * $this->perPage - 1);
        $len = Redis::zCard($sortFieldName);
        $tasks = [];
        $this->taskRedis = new TaskRedis();

        foreach ($keys as $key) {
            $this->taskRedis->useDB();
            $data = Redis::hGetAll($key);
            $tasks[] = $data;
        }
        return $this->toPageData($tasks, $len, $page, $options);
    }

    /**
     * 根据任务ID获取任务详情信息
     *
     * @param $id 任务id
     * @return array
     */
    public function getDataForDetail($id)
    {
        $this->toSlave();
        $task = Redis::hGetall('mbtk:' . $id);
        if (!$task) $this->throwMyException('任务已被删除或者不存在');
        return $task;
    }

    /**
     * 更新任务信息
     * @param id
     * @return array
     */
    public function queryEditTask($id)
    {
        $this->toMaster();
        $task = MemberTask::where('id', $id)->first();
        $resultSet = Redis::hMset('mbtk:' . $id, $task->toArray());
        if (!$resultSet) $this->throwMyException('编辑Redis任务信息失败');
        Redis::zAdd('z_mb_list', strtotime($task['updated_at']), 'mbtk:' . $id);
    }

    /**
     * 删除任务信息
     * @param id
     * @return array
     */
    public function queryDeleteTask($id)
    {
        $this->toMaster();
        $resultMbt = Redis::Del('mbtk:' . $id); //删除任务redis信息
        if (!$resultMbt) $this->throwMyException('删除Redis任务信息失败');

        $resultMb = Redis::zRem('z_mb_list', 'mbtk:' . $id);//删除链表 每日新品
        if (!$resultMb) $this->throwMyException('删除Redis有序集合任务失败');
    }

    /**
     * 完成任务
     * @param id 任务id
     * @param memberId 用户id
     * @return array
     */
    public function queryTaskFinished($id, $memberId)
    {
        $this->toMaster();
        //当前日期
        $dayDate = date('Y-m-d');
        $key = 'mb_tk_finish:' . $id . '|' . $memberId;
        $memberTask = Redis::hGetAll($key);

        if ($memberTask) {
            if ($memberTask['date'] == $dayDate) {
                $this->throwMyException('已完成该任务！');
            } else {
                Redis::hMset($key, array('date' => $dayDate));
            }
        } else {
            Redis::hMset($key, array('date' => $dayDate));
        }
    }

    /**
     * 检测任务是否完成
     * @param id 任务id
     * @param memberId 用户id
     * @return array
     */
    public function queryTaskIsFinished($id, $memberId)
    {
        $this->toSlave();
        //当前日期
        $dayDate = date('Y-m-d');
        $memberTask = Redis::hGetAll('mb_tk_finish:' . $id . '|' . $memberId);

        if ($memberTask) {
            if ($memberTask['date'] == $dayDate) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
