<?php
/**
 * Created by PhpStorm.
 * User: suer
 * Date: 2016/12/28
 * Time: 12:05
 */

namespace App\Model\Cache\Redis;

use Illuminate\Pagination\LengthAwarePaginator;
use Redis;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CommonRedis
{
    // 每页显示条数
    protected $perPage = 6;
    // 库索引
    protected $dbIndex = 0;

    /**
     * 抛出异常
     * @param $message 异常信息
     */
    protected function throwMyException($message)
    {
        throw new ConflictHttpException($message);
    }

    /**
     * 使用商品库
     * @param bool $isFlushDB
     */
    protected function useDB($isFlushDB = false)
    {
        $this->selectRedisDB($this->dbIndex, $isFlushDB);
    }

    /**
     *  选择redis 库
     * @param int $index 索引
     * @param boolean $isFlushDB 是否刷新库
     */
    protected function selectRedisDB($index = 0, $isFlushDB = false)
    {
        Redis::select($index);
        if ($isFlushDB) Redis::flushDB();
    }

    /**
     * 转换为分页数据
     * @param $data 当前页显示的数据
     * @param $length 所有数据条数
     * @param $page 当前页
     * @param $options 附加参数
     * @return array
     */
    protected function toPageData($data, $length, $page, $options)
    {
        $request = app('request');
        $lengthAwarePaginator = new LengthAwarePaginator($data, $length, $this->perPage, $page, $options);
        $lengthAwarePaginator->setPath($request->url());
        $lengthAwarePaginator->appends($request->all());
        return $lengthAwarePaginator->toArray();
    }

    //    /**
//     * 检查key
//     * @param $keys
//     * @param $errMsg 错误信息
//     */
//    private function checkKey($keys, $errMsg)
//    {
//        if (is_array($keys) && count($keys) == 0) {
//            $this->throwMyException($errMsg);
//        }
//    }
}