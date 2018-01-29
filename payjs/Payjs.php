<?php
/**
 * Created by PhpStorm.
 * User: lixia
 * Date: 2017/12/25
 * Time: 17:53
 */

namespace Payjs;

class Payjs
{
    private $MerchantID;
    private $MerchantKey;
    private $NotifyURL = null;
    private $AutoSign = true;
    private $ToObject = true;

    /**
     * Payjs constructor.
     * @param $config
     */
    public function __construct($config = null){
        if(!is_array($config)){
            return false;
        }
        if(isset($config['merchantid'])){
            $this->MerchantID = $config['merchantid'];
        }
        if(isset($config['merchantkey'])){
            $this->MerchantKey = $config['merchantkey'];
        }
        if(isset($config['notifyurl'])){
            $this->NotifyURL = $config['notifyurl'];
        }
        if(isset($config['toobject'])){
            $this->ToObject = $config['toobject'];
        }
    }

    /*
     * 扫码支付
     */
    public function QRPay($OrderID = null,$Amount = 1,$Products = '订单',$Attach =null){
        if (is_null($OrderID)){
            $OrderID = self::SetOrderID();
        }
        $RetURL = 'https://payjs.cn/api/native';

        return $this->Submit($RetURL,[
            'total_fee' => $Amount,
            'body' => $Products,
            'attach' => $Attach,
            'out_trade_no' => $OrderID
        ]);
    }

    /*
     * 收银台模式
     */
    public function Cashier($OrderID = null,$Amount = 1,$Products = '订单',$JumpURL = '',$Attach = null){
        if (is_null($OrderID)){
            $OrderID = self::SetOrderID();
        }
        $RetURL = 'https://payjs.cn/api/cashier';

        return $this->Submit($RetURL,[
            'total_fee' => $Amount,
            'body' => $Products,
            'attach' => $Attach,
            'out_trade_no' => $OrderID,
            'callback_url' => $JumpURL
        ]);
    }

    /*
     * 订单查询
     */
    public function Query($OrderID = null){
        if (is_null($OrderID)){
            return '必须指定payjs订单id';
        }
        $RetURL = 'https://payjs.cn/api/check';

        return $this->Submit($RetURL,[
            'payjs_order_id' => $OrderID
        ]);
    }

    /*
     * 生成随机数字
     */
    protected static function SetOrderID($Length = 6){
        $Rand =  rand(pow(10,($Length - 1)), pow(10,$Length) -1);
        return time() . $Rand;
    }

    /*
     * 签名
     */
    protected function Sign(array $Data) {
        ksort($Data);
        return strtoupper(md5(urldecode(http_build_query($Data)).'&key='.$this->MerchantKey));
    }

    /*
     * 预处理数据
     */
    protected function Submit($Url,$Arrry){
        if($this->AutoSign){
            if(!array_key_exists('payjs_order_id',$Arrry)){
                $Arrry['mchid'] = $this->MerchantID;
                if (!empty($this->NotifyURL)){
                    $Arrry['notify_url'] = $this->NotifyURL;
                }
                if (is_null($Arrry['attach'])){
                    unset($Arrry['attach']);
                }
            }
            $Arrry['sign'] = $this->Sign($Arrry);
        }
        return self::Curl($Url,$Arrry,$this->ToObject);
    }

    /*
     * curl
    */
    protected static function Curl($Url,$Arrry,$Coded){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $Url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $Arrry);
        $cexecute = curl_exec($ch);
        curl_close($ch);

        if($cexecute){
            if($Coded){
                return json_decode($cexecute);
            }else{
                return $cexecute;
            }
        }else{
            return false;
        }
    }
}
