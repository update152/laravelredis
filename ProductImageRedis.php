<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2016/12/28
 * Time: 12:08
 */

namespace App\Model\Cache\Redis;
use Redis;
use App\Model\DB\ProductImage;

class ProductImageRedis extends CommonRedis
{

    public function __construct()
    {
        $this->dbIndex = 2;
    }

    /**
     * 根据商品ID 获取第一个商品图片
     *
     * @param $id 商品ID
     * @return array
     */
    public function getFirstByProductId($id)
    {
        $this->useDB();
        $keys = Redis::keys('p:' . $id . '*');
        if (empty($keys) || count($keys) == 0) return null;
        return Redis::hGetAll($keys[0]);
    }

    /**
     * 根据商品ID 获取所有商品图片
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryAllByProductId($id)
    {
        $this->useDB();
        $keys = Redis::keys('p:' . $id . '*');
        $productImages = [];
        foreach ($keys as $k => $key) {
            $productImages[] = Redis::hGetAll($key);
        }
        return $productImages;
    }

    /**
     * 更新商品图片
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryEditProductImage($id)
    {
        $this->useDB();
        $productImage = ProductImage::where('product_id', $id)->first();
        $result = Redis::hMset('p:' . $id . '|pi:' . $productImage['id'], $productImage->toArray());
        if (!$result) $this->throwMyException('更新Redis商品图片信息失败');
    }
    /**
     * 删除产品图片redis信息
     *
     * @param $id 商品ID
     * @return array
     */
    public function queryDeleteProductImage($id)
    {
        $this->useDB();
        $productSizes = ProductImage::where('product_id',$id)->get();
        $result = [];
        foreach ($productSizes as $key => $value) {
            $result =  Redis::Del('p:' . $value['product_id'] . '|pi:' . $value['id']);
        }
        if (!$result) $this->throwMyException('删除Redis商品图片信息失败');
    }

}
