<?php
/**
 * Created by PhpStorm.
 * User: suer
 * Date: 2016/12/28
 * Time: 12:11
 */

namespace App\Model\Cache\Redis;

use Redis;


class RushToPurchaseTimeFrameProductRedis extends CommonRedis
{

    public function __construct()
    {
        $this->dbIndex = 4;
        parent::__construct();
    }

    /**
     * 根据商品规格ID 获取抢购时段
     *
     * @param $productSizeId 商品规格ID
     * @return array 抢购时段数据
     */
    protected function getByProductSizeId($productSizeId)
    {
        $this->toSlave();
        $timeFrameProduct = Redis::hGetAll('*ps:' . $productSizeId . '*');
        if (empty($timeFrameProduct)) return null;
        $rushToPurchaseTimeRedis = new RushToPurchaseTimeFrameRedis();
        return $rushToPurchaseTimeRedis->getById($timeFrameProduct['id']);
    }

    /**
     * 根据抢购时段ID获取所有抢购商品
     *
     * @param $id 抢购时段ID
     * @return array
     */
    public function queryAllByTimeFrameId($id)
    {
        return $this->queryCustomNumberByTimeFrameId($id, 0, '',1);
    }

    /**
     *  自定义获取抢购商品列表
     * @param int $id 时段ID
     * @param int $number 显示数量;为0时不限制
     * @param int $page 当前页;为0时，不分页，大于0时分页
     * @param null $sortFieldName 排序字段名称；为null时不排序
     * @param array $options 附加参数
     * @return array
     */
    protected function queryCustomNumberByTimeFrameId($id, $number = 0, $timeFrame = '' ,$page = 0, $sortFieldName = null, $options = [])
    {
        $this->toSlave();
        $keys = Redis::keys('*rtptf:' . $id.'*');
        if ($number > 0 && count($keys) > $number) $keys = array_slice($keys, 0, $number);
        if ($page > 0) {
            $keysCollection = collect($keys);
            $keys = $keysCollection->forPage($page, $this->perPage)->all();
        }
        $timeFrameProducts = [];
        $productRedis = new ProductRedis();
        foreach ($keys as $k => $key) {
            $this->useDB();
            $timeFrameProduct = Redis::hGetAll($key);
            $timeFrameProducts[] = $productRedis->getDataForDefaultRushToPurchaseList($timeFrameProduct,$timeFrame);
            if (!$timeFrameProducts[$k]['deleted_at']){
                $timeFrameProducts[$k]['deleted_at'] = null;
            }
        }

        if ($page > 0) {
            return $this->toPageData($timeFrameProducts, count($keysCollection), $page, $options);
        }

        return $timeFrameProducts;
    }

    /**
     *  显示所有时段的抢购商品（前三个）
     *
     *
     */
    public function queryForHome()
    {
        $rushToPurchaseTimeFrame = new RushToPurchaseTimeFrameRedis();
        // 所有抢购时段
        $allTimeFrames = $rushToPurchaseTimeFrame->queryAll();
        // 根据抢购时段获取前三个抢购商品

        foreach ($allTimeFrames as $key => $timeFrame) {
            $allTimeFrames[$key]['detail'] = $this->queryCustomNumberByTimeFrameId($allTimeFrames[$key]['id'], 3,$timeFrame);
        }
        return $allTimeFrames;
    }
}
