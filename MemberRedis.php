<?php
/**
 * Created by PhpStorm.
 * User: wanghb
 * Date: 2017/2/7
 * Time: 18:19
 */
namespace App\Model\Cache\Redis;

use Redis;
use App\Model\DB\Member;

class MemberRedis extends CommonRedis
{
    public function __construct()
    {
        $this->dbIndex = 6;
        $this->memberModel = new Member();
    }
    /**
     * 根据用户ID获取用户信息
     *
     * @param $memberId  用户id
     * @return array
     */
    public function getDataForDetail($memberId)
    {
        $this->useDB();
        $member = Redis::hGetall('member:'.$memberId);
        if (!$member) $this->throwMyException('用户不存在');
        return $member;
    }
    /**
     * 更新用户信息
     * @param $memberId 用户id
     * @return array
     */
    public function queryEditMember($memberId)
    {
        $this->useDB();
        $member = $this->memberModel->where('id',$memberId)->first();
        $result = Redis::hMset('member:'.$memberId, $member->toArray());
        if (!$result) $this->throwMyException('更新Redis用户信息失败');

    }
    /**
     * 删除用户redis信息
     * @param $memberId 用户id
     * @return bool
     */
    public function queryDeleteMember($memberId)
    {
        $this->useDB();
        $result = Redis::Del('member:'.$memberId);
        if (!$result) $this->throwMyException('删除Redis用户信息失败');
    }

}
