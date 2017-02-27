<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2016/12/28
 * Time: 12:10
 */

namespace App\Model\Cache\Redis;

use Redis;


class RushToPurchaseTimeFrameRedis extends CommonRedis
{

    public function __construct()
    {
        $this->dbIndex = 3;
    }

    /**
     * 获取所有抢购时段
     *
     * @return array
     */
    public function queryAll()
    {
        $this->useDB();
        $keys = Redis::keys('*');
        $rushToPurchaseTimeFrames = [];
        foreach ($keys as $k => $key) {
            $rushToPurchaseTimeFrames[] = Redis::hGetAll($key);
        }
        return $rushToPurchaseTimeFrames;
    }

    /**
     * 根据商品获取抢购时段
     *
     * @param $id 抢购时段ID
     * @return array 时段信息
     */
    public function getById($id)
    {
        $this->useDB();
        return Redis::hGetAll('rtptf:' . $id);
    }
}
