<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2017/2/7
 * Time: 11:01
 */
namespace App\Model\Cache\Redis;

use Redis;

class SearchRecordRedis extends CommonRedis
{
    public function __construct()
    {
        $this->dbIndex = 5;
        parent::__construct();
    }

    /**
     * 获取热搜词
     * @return array
     */
    public function queryAllSearchRecord()
    {
        $this->toSlave();
        $searchRecords = Redis::zRevRange('hotKeywords',0,9);
        foreach ($searchRecords as $key => $value) {
             $searchRecord[$key]['keyword'] = $value;
             $searchRecord[$key]['number'] = 0;
             $searchRecord[$key]['id'] = 0;
        }
        return ['search_records'=>$searchRecord];
    }
    /**
     * 储存热搜词
     * @return array
     */
    public function queryRecordKeyword($keywords)
    {

        $this->toMaster();
        $result = Redis::zIncrBy('hotKeywords',1,$keywords);
        if (!$result) $this->throwMyException('添加redis热搜词失败');
    }
}
