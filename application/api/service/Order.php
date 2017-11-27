<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2017/11/22
 * Time: 11:47
 */

namespace app\api\service;


use app\api\model\Product;
use app\api\model\UserAddress;
use app\lib\exception\OrderException;
use app\lib\exception\UserException;

class Order
{
    // 订单的商品列表，也就是客户端传递过来的products参数
    protected $oProducts;

    // 真实的商品信息（包括库存量）
    protected $products;

    protected $uid;

    public function place($uid, $oProducts){
        // oProducts和products，作对比
        // products从数据库中查询出来
        $this->oProducts = $oProducts;
        $this->products = $this->getProductsByOrder($oProducts);
        $this->uid = $uid;

        $status = $this -> getOrderStatus();
        if(!$status['pass']){
            $status['order_id'] = -1;
            return $status;
        }

        // 开始创建订单
        $orderSnap = $this->snapOrder();
    }

    // 生成订单快照
    private function snpOrder($status){
        $snap = [
          'orderPrice' => 0,
          'totalCount' => 0,
          'pStatus' => [],
          'snapAddress' => null,
          'snapName' => ''
        ];

        $snap['orderPrice'] = $status['orderPrice'];
        $snap['totalCount'] = $status['totalCount'];
        $snap['pStatus'] = $status['pStatusArray'];
        $snap['snapAddress'] = Json_encode($this->getUserAddress());
    }

    private function getUserAddress(){
        $userAddress = UserAddress::where('user_id','=',$this->uid)
            ->find();
        if(!$userAddress){
            throw new UserException([
                'msg' => '用户收货地址不存在，下单失败',
                'errorCode' => 60001,
            ]);
        }
        return $userAddress->toArray();
    }

    private function getOrderStatus()
    {
        $status = [
            'pass' => true,
            'orderPrice' => 0,
            'totalCount' =>0,
            // 历史订单才有用，保存订单里所有商品的详细信息
            'pStatusArray' => []
        ];

        foreach ($this -> oProducts as $oProduct)
        {
            $pStatus = $this -> getProductStatus(
                $oProduct['product_id'],$oProduct['count'],$this->products
            );
            if(!$pStatus['haveStock']){
                $status['pass'] = false;
            }
            $status['orderPrice'] += $pStatus['totalPrice'];
            $status['totalCount'] += $pStatus['count'];
            array_push($status['pStatusArray'],$pStatus);
        }
        return $status;
    }

    private function getProductStatus($oPID,$oCount,$products){
        $pIndex = -1;

        // 初始值 订单里面商品的具体信息
        $pStatus = [
            'id' => null,
            // 库存量
            'haveStock' => false,
            'count' => 0,
            'name' => '',
            // 一类商品的价格乘以数量
            'totalPrice' => 0
        ];

        for($i=0; $i<count($products); $i++){
            if($oPID == $products[$i]['id']){
                $pIndex = $i;
            }
        }

        // 如果
        if($pIndex == -1){
            //客户端传递的product_id可能不存在
            throw new OrderException([
                'msg' => 'id为'.$oPID.'商品不存在，创建订单失败'
            ]);
        }
        else{
            $product = $products[$pIndex];
            $pStatus['id'] = $product['id'];
            $pStatus['count'] = $oCount;
            $pStatus['totalPrice'] = $product['price'] = $oCount;

            if($product['stock'] - $oCount >= 0){
                $pStatus['haveStock'] = true;
            }
        }
        return $pStatus;
    }

    // 根据订单信息查找真实的商品信息
    private function getProductsByOrder($oProducts)
    {
//        foreach ($oProducts as $oProduct){
//            //循环的查询数据库--很严重的错误
//        }
        //先将product_id放到oPIS里，才一次性去查
        $oPIDs = [];
        foreach ($oProducts as $item) {
            array_push($oPIDS, $item['product_id']);
        }
        $products = Product::all($oPIDs)
            ->visible(['id','price','stock','name','main_img_url'])
            //
            ->toArray();
        return $products;
    }
}