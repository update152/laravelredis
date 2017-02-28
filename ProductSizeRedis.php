<?php
/**
 * Created by PhpStorm.
 * User: suer
 * Date: 2016/12/28
 * Time: 12:08
 */

namespace App\Model\Cache\Redis;

use Redis;
use App\Model\DB\ProductSize;


class ProductSizeRedis extends CommonRedis
{
    public function __construct()
    {
        $this->dbIndex = 1;
        parent::__construct();
    }

    /**
     * 根据商品ID 获取第一个商品规格
     *
     * @param $id 商品ID
     * @return array
     */
    public function getFirstByProductId($id)
    {
        $this->toSlave();
        $keys = Redis::keys('p:' . $id . '*');
        if (empty($keys) || count($keys) == 0) return null;
        return Redis::hGetAll($keys[0]);
    }

    /**
     * 根据商品ID 获取所有商品规格
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryAllByProductId($id)
    {
        $this->toSlave();
        $keys = Redis::keys('p:' . $id . '*');
        $productSizes = [];
        foreach ($keys as $k => $key) {
            $productSizes[] = Redis::hGetAll($key);
        }
        return $productSizes;
    }

    /**
     * 更新商品规格
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryEditProductSize($id)
    {
        $this->toMaster();
        $productSizes = ProductSize::where('product_id', $id)->get();
        $result = [];
        foreach ($productSizes as $key => $value) {
            $result = Redis::hMset('p:' . $value['product_id'] . '|ps:' . $value['id'], $value->toArray());
        }
        if (!$result) $this->throwMyException('更新Redis商品规格信息失败');
    }

    /**
     * 更新删除
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryDeleteProductSize($id)
    {
        $this->toMaster();
        $productSizes = ProductSize::where('product_id', $id)->get();
        $result = [];
        foreach ($productSizes as $key => $value) {
            $result = Redis::Del('p:' . $value['product_id'] . '|ps:' . $value['id']);
        }
        if (!$result) $this->throwMyException('删除Redis商品规格信息失败');
    }
}
