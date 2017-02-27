<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2016/11/25
 * Time: 15:08
 */

namespace App\Model\Cache\Redis;

use App\Model\DB\Product;
use Redis;

class ProductRedis extends CommonRedis
{
    private $indexDataRedis;
    private $productSizeRedis;
    private $productImageRedis;
    public function __construct()
    {
        $this->dbIndex = 0;
    }

    /**
     * 查询商品, 通过collection分页
     * @param int $page 当前页
     * @param string $sortFieldName 排序字段名称
     * @param array $options
     * @return array
     */
    public function queryAllByCollection($page = 1, $sortFieldName = 'updated_at', array $options = [])
    {
        $products = $this->queryAll();
        $collection = collect($products);
        $products = null;
        $data = [];
        $result = [];
        if (!empty($sortFieldName)) {
            $sorted = $collection->sortByDesc($sortFieldName);
            $result = $sorted->forPage($page, $this->perPage)->all();
        } else {
            $result = $collection->forPage($page, $this->perPage)->all();
        }
        $productImageRedis = new ProductImageRedis();
        $productSizeRedis = new ProductSizeRedis();
        foreach ($result as $key => $value) {
            // 获取所有图片
            $value['image'] = $productImageRedis->getFirstByProductId($value['id']);
            // 获取所有规格
            $value['size'] = $productSizeRedis->getFirstByProductId($value['id']);
            $data[] = $value;
        }
        return $this->toPageData($data, count($collection), $page, $options);
    }

    /**
     * 查询商品, 通过Redis分页
     * @param int $page 当前页
     * @param string $sortFieldName 排序字段名称
     * @param array $options
     * @return array
     */
    public function queryAllByRedis($page = 1, $sortFieldName = 'put_daily_new', array $options = [])
    {
        $keys = Redis::zRevRange($sortFieldName, ($page -1)*$this->perPage,$page*$this->perPage-1);
        $len = Redis::zCard($sortFieldName);
        $products = [];
        $productImageRedis = new ProductImageRedis();
        $productSizeRedis = new ProductSizeRedis();
        $productRedis = new ProductRedis();
        foreach ($keys as $key) {
            $productRedis->useDB();
            $data = Redis::hGetAll($key);
            // 获取所有图片
            $data['image'] = $productImageRedis->getFirstByProductId($data['id']);
            // 获取所有规格
            $data['size'] = $productSizeRedis->getFirstByProductId($data['id']);
            $products[] = $data;
        }
        return $this->toPageData($products, $len, $page, $options);
    }
    /**
     * 查询所有商品
     *
     *
     */
    protected function queryAll()
    {
        $this->useDB();
        $keys = Redis::Keys('p:*');
        $products = [];
        foreach ($keys as $k => $key) {
            $products[] = Redis::hGetAll($key);
        }
        return $products;
    }


    /**
     * 根据商品商品ID获取商品详情信息
     *
     * @param $id 商品ID
     * @return array
     */
    public function getDataForDetail($id)
    {
        $product = $this->getById($id);
        $productImageRedis = new ProductImageRedis();
        $productSizeRedis = new ProductSizeRedis();
        $product['images'] = $productImageRedis->queryAllByProductId($product['id']);
        $product['sizes'] = $productSizeRedis->queryAllByProductId($product['id']);
        return $product;
    }

    /**
     * 在展示列表情况，根据商品商品ID获取商品相关信息
     *
     * @param $id 商品ID
     * @return array
     */
    public function getDataForList($id)
    {
        $product = $this->getById($id);
        $productImageRedis = new ProductImageRedis();
        $productSizeRedis = new ProductSizeRedis();
        $product['product'] = $product;
        $product['size'] = $productSizeRedis->getFirstByProductId($id);
        $product['image'] = $productImageRedis->getFirstByProductId($id);
        return $product;
    }
    /**
     * 抢购商品根据商品商品ID获取商品相关信息
     *
     * @param $id 商品ID
     * @return array
     */
    public function getDataForDefaultRushToPurchaseList($timeFrameProduct,$timeFrame)
    {
        $productImageRedis = new ProductImageRedis();
        $productSizeRedis = new ProductSizeRedis();
        $product = $timeFrameProduct;
        $product['product'] = $this->getById($timeFrameProduct['product_id']);
        $product['size'] = $productSizeRedis->getFirstByProductId($timeFrameProduct['product_id']);
        $product['image'] = $productImageRedis->getFirstByProductId($timeFrameProduct['product_id']);
        $product['time_frame'] = $timeFrame;
        return $product;
    }
    /**
     *  根据商品ID 获取商品
     * @param $id 商品ID
     * @return array
     */
    public function getById($id)
    {
        $this->useDB();
        $product = Redis::hGetAll('p:' . $id);
        if (!$product){
            $this->throwMyException('商品不存在');
        }
        return $product;
    }

    /**
     * 更新商品信息
     * @param id
     * @return array
     */
    public function queryEditProduct($id)
    {
        $this->useDB();

        $this->productSizeRedis = new ProductSizeRedis();
        $this->productImageRedis = new ProductImageRedis();

        $product = Product::where('id',$id)->first();
        $resultSet = Redis::hMset('p:' . $id, $product->toArray());
        if (!$resultSet) $this->throwMyException('编辑Redis商品链表信息失败');

        Redis::zAdd('put_daily_new', strtotime($product['updated_at']), 'p:' . $product['id']);

        $this->productSizeRedis->queryEditProductSize($id);//更新产品规则信息redis
        $this->productImageRedis->queryEditProductImage($id);//更新产品图片信息redis
    }

    /**
     * 删除商品redis信息
     * @param id
     * @return array
     */
    public function queryDeleteProduct($id)
    {
        $this->useDB();
        $this->productSizeRedis = new ProductSizeRedis();
        $this->productImageRedis = new ProductImageRedis();

        $resultProduct = Redis::Del('p:'.$id);//删除redis商品信息
        if (!$resultProduct) $this->throwMyException('删除Redis商品规格信息失败');

        Redis::zRem('put_daily_new','p:'.$id);//删除集合 每日新品

        $this->productSizeRedis->queryDeleteProductSize($id);//删除产品规则信息redis
        $this->productImageRedis->queryDeleteProductImage($id);//删除产品图片信息redis
    }
}
