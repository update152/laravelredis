<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2017/2/4
 * Time: 12:09
 */
namespace App\Model\Cache\Redis;

use Redis;
use App\Model\DB\Banner;

class BannerRedis extends CommonRedis
{

    public function __construct()
    {
        $this->dbIndex = 6;
        $this->bannerModel = new Banner();
    }
    /**
     * 获取banner信息
     * @return array
     */
    public function queryAllBanners()
    {
        $this->useDB();
        $keys = Redis::Keys('bn:*');
        $banners = [];
        foreach ($keys as $k => $key) {
            $banners[] = Redis::hGetAll($key);
        }
        $banner = ['banners'=>$banners];
        return $banner;
    }

    /**
     * 更新banner信息
     * @param id
     * @return array
     */
    public function queryEditBanner($id)
    {
        $this->useDB();
        $banner = $this->bannerModel->where('id',$id)->first();
        $result = Redis::hMset('bn:'.$id, $banner->toArray());
        if (!$result) $this->throwMyException('更新Redis Banner信息失败');
    }
    /**
     * 删除banner redis信息
     * @param id
     * @return array
     */
    public function queryDeleteBanner($id)
    {
        $this->useDB();
        $result = Redis::Del('bn:'.$id);
        if (!$result) $this->throwMyException('删除Redis Banner信息失败');
    }
}
