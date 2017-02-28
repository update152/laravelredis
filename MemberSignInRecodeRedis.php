<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2017/2/17
 * Time: 17:09
 */
namespace App\Model\Cache\Redis;

use Redis;
use App\Model\DB\MemberSignInRecord;

class MemberSignInRecodeRedis extends CommonRedis
{

    public function __construct()
    {
        $this->dbIndex = 7;
        parent::__construct();
    }

    /**
     * 检测签到信息
     * @param memberId 用户id
     * @param data 签到日期
     * @param attendance_times 签到次数
     * @param continuous_attendance 连续签到次数
     * @param next_date 下次签到日期
     * @param thisMonth 当前月份
     * @return array
     */
    public function queryCheckSignInRecode($memberId)
    {
//        $connection = Redis::connection();
//        var_dump($connection->getConnection());die();
//        $this->useDB();
        //当前日期
        $dayDate = date('Y-m-d');
        //下次签到日期
        $nextDate = date("Y-m-d", strtotime("+1 day"));
        //当前月份
        $thisMonth = date('Y-m');
        $key = 'mb_snird:' . $memberId . '|' . $thisMonth;
        $keys = Redis::keys('mb_snird:' . $memberId . '*');
        $this->toSlave();
        $memberSignInRecode = Redis::hGetAll($key);
        $this->toMaster();
        if ($memberSignInRecode) {
            if ($memberSignInRecode['date'] == $dayDate) {
                $this->throwMyException('今日已签到！');
            } else {
                if ($memberSignInRecode['this_month'] == $thisMonth) {
                    if ($memberSignInRecode['next_date'] == $dayDate) {
                        Redis::hMset($key, array('date' => $dayDate, 'this_month' => $thisMonth, 'attendance_times' => $memberSignInRecode['attendance_times'] + 1, 'continuous_attendance' => $memberSignInRecode['continuous_attendance'] + 1, 'next_date' => $nextDate));
                    } else {
                        Redis::hMset($key, array('date' => $dayDate, 'this_month' => $thisMonth, 'attendance_times' => $memberSignInRecode['attendance_times'] + 1, 'continuous_attendance' => 1, 'next_date' => $nextDate));
                    }
                } else {
                    $this->queryDeleteMemberSignRecodeRedis($keys, $key, $dayDate, $nextDate, $thisMonth);
                }
            }
        } else {
            $this->queryDeleteMemberSignRecodeRedis($keys, $key, $dayDate, $nextDate, $thisMonth);
        }
    }

    /**
     * 获取签到信息
     * @param memberId 用户id
     * @return array
     */
    public function queryGetSignInInfo($memberId)
    {
        $this->useDB();
        //当前月份
        $thisMonth = date('Y-m');
        //当前日期
        $dayDate = date('Y-m-d');
        $memberSignInRecode = Redis::hGetAll('mb_snird:' . $memberId . '|' . $thisMonth);
        if ($memberSignInRecode) {
            $memberSignInRecordModel = new MemberSignInRecord();
            return [
                'sign_in_number' => $memberSignInRecode['attendance_times'],
                'sign_in_continuous_number' => $memberSignInRecode['continuous_attendance'],
                'today_get_gold' => $memberSignInRecordModel->getGold($memberSignInRecode['continuous_attendance'] - 1),
                'is_sign_in' => ($memberSignInRecode['date'] == $dayDate) ? true : false
            ];
        } else {
            return [
                'sign_in_number' => 0,
                'sign_in_continuous_number' => 0,
                'today_get_gold' => 0,
                'is_sign_in' => false
            ];
        }
    }

    /**
     * 删除签到无用信息
     * @param keys 用户所有签到记录key
     * @param key 用户当月签到记录key
     * @param attendance_times 签到次数
     * @param continuous_attendance 连续签到次数
     * @param next_date 下次签到日期
     * @param thisMonth 当前月份
     * @param dayDate 当前日期
     */
    private function queryDeleteMemberSignRecodeRedis($keys, $key, $dayDate, $thisMonth, $nextDate)
    {
        $this->useDB();
        Redis::Del($keys);
        Redis::hMset($key, array('date' => $dayDate, 'this_month' => $thisMonth, 'attendance_times' => 1, 'continuous_attendance' => 1, 'next_date' => $nextDate));
    }
}
