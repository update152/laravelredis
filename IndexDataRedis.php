<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2016/12/28
 * Time: 13:41
 */

namespace App\Model\Cache\Redis;

use App\Model\DB\MemberTask;
use App\Model\DB\Banner;
use App\Model\DB\Product;
use App\Model\DB\ProductImage;
use App\Model\DB\ProductSize;
use App\Model\DB\RushToPurchaseTimeFrame;
use App\Model\DB\RushToPurchaseTimeFrameProduct;
use App\Model\DB\SearchRecord;
use Redis;
use App\Model\DB\MemberSignInRecord;

class IndexDataRedis extends CommonRedis
{
    private $productRedis;
    private $productSizeRedis;
    private $productImageRedis;
    private $rushToPurchaseTimeFrameRedis;
    private $rushToPurchaseTimeFrameProductRedis;
    private $bannerRedis;
    private $taskRedis;
    private $hotSearchRedis;
    private $memberRedis;
    // 索引所有产品相关的数据
    public function indexAllAboutProductData()
    {
        $errors['product'] = $this->indexAllProduct();
        $errors['productSize'] = $this->indexAllProductSize();
        $errors['productImage'] = $this->indexAllProductImage();
        $errors['rushToPurchaseTimeFrame'] = $this->indexAllRushToPurchaseTimeFrame();
        $errors['rushToPurchaseTimeFrameProduct'] = $this->indexAllRushToPurchaseTimeFrameProduct();
        $errors['banner'] = $this->indexAllBanner();
        $errors['memberTask'] = $this->indexAllMemberTask();
        $errors['hotSearch'] = $this->indexAllHotSearch();
       // $errors['members'] = $this->indexAllMembers();
        $error['memberSignInRecode'] = $this->indexAllMemberSignInRecode();
        return $errors;
    }

    /**
     * 索引所有商品
     *
     */
    public function indexAllProduct()
    {
        $this->productRedis = new ProductRedis();
        $this->productRedis->useDB(true);
        $products = Product::get();
        $errors = [];
        foreach ($products as $key => $value) {
            $hashKey = 'p:' . $value['id'];
            $result = Redis::hMset($hashKey, $value->toArray());
            if (!$result) $errors['hash'][] = $value;
            if ($value['product_type'] == 1 && $value['onsell'] == 1) {
                // 每日新上商品按照更新时间的有序集合
                $result = Redis::zAdd('put_daily_new', strtotime($value['updated_at']), 'p:' . $value['id']);
            }
            if (!$result) $errors['zlist']['updated_at'][] = $value;
            $value = null;
        }
        unset($products);
        return $errors;
    }

    // 索引所有产品规格
    public function indexAllProductSize()
    {
        $this->productSizeRedis = new ProductSizeRedis();
        $this->productSizeRedis->useDB(true);
        $productSizes = ProductSize::get();
        $errors = [];
        foreach ($productSizes as $key => $value) {
            $result = Redis::hMset('p:' . $value['product_id'] . '|ps:' . $value['id'], $value->toArray());
            if (!$result) $errors[] = $value;
            $value = null;
        }
        $productSizes = null;
        return $errors;
    }

    // 索引所有产品图片
    public function indexAllProductImage()
    {
        $this->productImageRedis = new ProductImageRedis();
        $this->productImageRedis->useDB(true);
        $productImages = ProductImage::get();
        $errors = [];
        foreach ($productImages as $key => $value) {
            $result = Redis::hMset('p:' . $value['product_id'] . '|pi:' . $value['id'], $value->toArray());
            if (!$result) $errors[] = $value;
            $value = null;
        }
        $productImages = null;
        return $errors;
    }

    // 索引所有抢购时段
    public function indexAllRushToPurchaseTimeFrame()
    {
        $this->rushToPurchaseTimeFrameRedis = new RushToPurchaseTimeFrameRedis();
        $this->rushToPurchaseTimeFrameRedis->useDB(true);
        $rushToPurchaseTimeFrames = RushToPurchaseTimeFrame::get();
        $errors = [];
        foreach ($rushToPurchaseTimeFrames as $key => $value) {
            $result = Redis::hMset('rtptf:' . $value['id'], $value->toArray());
            if (!$result) $errors[] = $value;
            $value = null;
        }
        $rushToPurchaseTimeFrames = null;
        return $errors;
    }

    // 索引所有抢购时段的商品
    public function indexAllRushToPurchaseTimeFrameProduct()
    {
        $this->rushToPurchaseTimeFrameProductRedis = new RushToPurchaseTimeFrameProductRedis();
        $this->rushToPurchaseTimeFrameProductRedis->useDB(true);
        $rushToPurchaseTimeFrameProducts = RushToPurchaseTimeFrameProduct::get();
        $errors = [];
        foreach ($rushToPurchaseTimeFrameProducts as $key => $value) {
            $redisKey = 'p:' . $value['product_id']
                . '|ps:' . $value['product_size_id']
                . '|rtptf:' . $value['rush_to_purchase_time_frame_id']
                . '|rtptfp:' . $value['id'];
            $data = $value->toArray();
            unset($data['time_frame']);
            $result = Redis::hMset(
                $redisKey
                , $data);
            if (!$result) $errors[] = $value;
            $value = null;
        }
        $rushToPurchaseTimeFrameProducts = null;
        return $errors;
    }
    // 索引所有轮换图
    public function indexAllBanner()
    {
        $this->bannerRedis = new BannerRedis();
        $this->bannerRedis->useDB();
        $banners = Banner::select('id','link','image_path','image_base_name','order')
            ->orderBy('order', 'desc')
            ->get();
        $errors = [];
        foreach ($banners as $key => $value) {
            $hashKey = 'bn:' . $value['id'];
            $result = Redis::hMset($hashKey, $value->toArray());
            if (!$result) $errors[] = $value;
            $value = null;
        }
        unset($banners);
        return $errors;
    }
    /**
     * 索引所有任务
     *
     */
    public function indexAllMemberTask()
    {
        $this->taskRedis = new TaskRedis();
        $this->taskRedis->useDB(true);
        $tasks = MemberTask::get();
        $errors = [];
        foreach ($tasks as $key => $value) {
            $hashKey = 'mbtk:' . $value['id'];
            $result = Redis::hMset($hashKey, $value->toArray());
            if (!$result) $errors[] = $value;
            $value = null;
        }
        $tasks = MemberTask::select('id','updated_at')
            ->orderBy('updated_at', 'desc')
            ->get();
        foreach ($tasks as $task) {
            // 索引更新日期
            $result = Redis::zAdd('z_mb_list', strtotime($task['updated_at']), 'mbtk:' . $task['id']);
            if (!$result) $errors['z_mb_list']['updated_at'][] = $task;
        }
        unset($tasks);
        return $errors;
    }
    /**
     * 索引热搜词
     *
     */
    public function indexAllHotSearch()
    {
        $this->hotSearchRedis = new SearchRecordRedis();
        $this->hotSearchRedis->useDB();
        $hotSearchs = SearchRecord::select('id','keyword','number')
            ->orderBy('number', 'desc')->limit(10)
            ->get();
        $errors = [];
        foreach($hotSearchs as $key=>$value){
            $result = Redis::zIncrBy('hotKeywords',$value['number'],$value['keyword']);
        }
        if (!$result) $errors[] = $result;
        $hotSearchs = null;
        return $errors;
    }
    /**
     * 索引所有用户
     *
     */
//    public function indexAllMembers()
//    {
//        $this->memberRedis = new MemberRedis();
//        $this->memberRedis->useDB(true);
//        $members = Member::get();
//        $errors = [];
//        foreach ($members as $key => $value) {
//            $hashKey = 'member:' . $value['id'];
//            $result = Redis::hMset($hashKey, $value->toArray());
//            if (!$result) $errors['hash'][] = $value;
//            $value = null;
//        }
//        unset($members);
//        return $errors;
//    }


    // 索引所有用户签到记录
    public function indexAllMemberSignInRecode()
    {
        $this->memberSignInRecodeRedis = new MemberSignInRecodeRedis();
        $this->memberSignInRecodeRedis->useDB();

        $memberSignInRecords = MemberSignInRecord::groupBy('member_id')->orderBy('date','desc')->get();
        $errors = [];
        $memberSignInRecordModel = new MemberSignInRecord();

        foreach ($memberSignInRecords as $key => $value) {
            $thisMonth = date('Y-m',strtotime($value['date']));
            $nextDate = date("Y-m-d",strtotime($value['date'] . "+1 day"));
            $attendanceTimes = MemberSignInRecord::whereRaw('date_format(`date` , "%y%m") = ' . date('ym'))
                ->where('member_id', $value['member_id'])->count();
            $continuousAttendance = $memberSignInRecordModel->getDaysRedis($value['member_id']);
            $result = Redis::hMset('mb_snird:' . $value['member_id'] . '|' . $thisMonth, array('date' => $value['date'],'this_month' => $thisMonth, 'attendance_times' => $attendanceTimes, 'continuous_attendance' => $continuousAttendance, 'next_date' => $nextDate));
            if (!$result) $errors[] = $value;
            $value = null;
        }

        unset($memberSignInRecords);
        return $errors;
    }
}
