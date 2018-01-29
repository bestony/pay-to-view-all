<?php
/*
Plugin Name: Pay to view all
Plugin URI: https://github.com/bestony/pay-to-view-all
Description: 本插件(Pay to view all)可以隐藏文章中的任意部分内容，当访客支付后即可浏览隐藏的内容
Version: 0.0.1
Author: Bestony
Author URI: https://www.ixiqin.com
Function Prefix: ptva_
*/
/*  Copyright 2018  Bestony  xiqingongzi@gmail.com

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Include Payjs Class
 */
include_once "payjs/Payjs.php";
use \Payjs\Payjs;

/**
 * add submenu to general menu
 */
add_action('admin_menu', 'ptva_add_submenu_page');
function ptva_add_submenu_page(){
	add_submenu_page( 'options-general.php','付费阅读', '付费阅读设置', 'manage_options', 'pay-to-view-all-setting', 'ptva_submenu_page_callback' );
}

/**
 * submenu page callback, use to show options
 */
function ptva_submenu_page_callback(){
    ?>	<div class="wrap">
<h2>付费阅读配置</h2>
<?php
if ($_POST['update_options']=='true') {//若提交了表单，则保存变量
    update_option('ptva_merchant_id', $_POST['ptva_merchant_id']);
    update_option('ptva_merchant_key', $_POST['ptva_merchant_key']);
    update_option('ptva_post_fee', $_POST['ptva_post_fee']);
    update_option('ptva_summary_number', $_POST['ptva_summary_number']);
    update_option('ptva_mode', $_POST['ptva_mode']);
    echo '<div id="message" class="updated below-h2"><p>设置保存成功!</p></div>';//保存完毕显示文字提示
}
$mode = get_option('ptva_mode')?get_option('ptva_mode'):"white";
//下面开始界面表单
?>
<form method="POST" action="">
    <input type="hidden" name="update_options" value="true" />
    <table class="form-table">
            <tr>
                <th scope="row">模式:</th>
                <td>
                <select name="ptva_mode">
                    <option value="white" <?php selected($mode,'white') ?>>「白名单模式」：全站付费，指定文章免费</option>
                    <option value="black" <?php selected($mode,'black') ?>>「黑名单模式」：全站免费，指定文章付费</option>
                </select>
                </td>
            </tr>
            <tr>
                <th scope="row">商户ID:</th>
                <td><input type="text" name="ptva_merchant_id" id="ptva_merchant_id" value="<?php echo get_option('ptva_merchant_id'); ?>" /></td>
            </tr>
            <tr>
                <th scope="row">商户 Key:</th>
                <td><input type="text" name="ptva_merchant_key" id="ptva_merchant_key" value="<?php echo get_option('ptva_merchant_key'); ?>" /></td>
            </tr>
            <tr>
                <th scope="row">文章单价:</th>
                <td><input type="text" name="ptva_post_fee" id="ptva_post_fee" value="<?php echo get_option('ptva_post_fee'); ?>" /></td>
            </tr>
            <tr>
                <th scope="row">截断长短:</th>
                <td><input type="text" name="ptva_summary_number" id="ptva_summary_number" value="<?php echo get_option('ptva_summary_number'); ?>" /></td>
            </tr>
    </table>
    <p><input type="submit" class="button-primary" name="admin_options" value="保存配置"/></p>
</form>
</div>
<?php
//add_action('admin_menu', 'customSetting');
}

/**
 * Core Function , Use to protect post form unpay visitor
 */
function ptva_prevent_unpay_user( $content ) {
    $summary_number = get_option('ptva_summary_number');
    $mode = get_option('ptva_mode');
    if ($mode == 'white'){
        if(is_home()){
            return wp_trim_words($content,$summary_number,"...<hr>请<strong><a href='/wp-login.php'>登陆</a></strong>并支付后查看更多内容");
        }else{
            if(is_user_logged_in()){
                if(ptva_check_user_pay()){
                    return $content;
                }else{
                    return wp_trim_words($content,$summary_number,"...<hr>".ptva_get_qrcode()."");
                }
            }else{
                return wp_trim_words($content,$summary_number,"...<hr>请<strong><a href='/wp-login.php'>登陆</a></strong>并支付后查看更多内容");
            }

        }
    }else{
        return $content;
    }
}
add_filter( 'the_content', 'ptva_prevent_unpay_user' );

/**
 * Core Function : Check user pay record
 */
function ptva_check_user_pay(){

    $id = get_the_ID();
    $user = get_current_user_id();
    $postData = get_post_meta($id,'post_paid_user',true);
    $userArray = explode(",",$postData);

    if (in_array($user,$userArray)){
        return true;
    }else{
        return false;
    }

}

/**
 * Generate QRCode From Payjs
 */
function ptva_request_qrcode($id,$user){
    $mid = get_option('ptva_merchant_id');
    $mkey = get_option('ptva_merchant_key');
    $amount = get_option('ptva_post_fee');
    $path = get_site_url()."/pay_to_view_all";
    $payjs = new Payjs([
        'merchantid' => $mid,
        'merchantkey' => $mkey,
        'notifyurl' => $path,
        'toobject' => true
    ]);

    $order_id = 'pay_to_view_all'.$id.'_'.time();
    $order_title ='付费阅读_'.get_the_title($id);
    $attach = $id ."+".$user;

    $image = $payjs->QRPay($order_id,$amount,$order_title,$attach)->qrcode;
    return $image;
}

/**
 * Get QRCode from Post Meta.
 * Use Post meta to save QRCode
 */
function ptva_get_qrcode(){
    $id = get_the_ID();
    $user = get_current_user_id();

    $cacheData = get_post_meta($id,'paid_qrcode_cache',true);
    if($cacheData === '0'){
        $image = ptva_request_qrcode($id,$user);
        update_post_meta($id,'paid_qrcode_cache',$image.",".time());
    }else{
        $data = explode(",",$cacheData);
        if ((time()-$data[1]) > 3600){
            $image = ptva_request_qrcode($id,$user);
            update_post_meta($id,'paid_qrcode_cache',$image.",".time());
        }else{
            $image = $data[0];
        }
    }


    return '<div style="text-align:center"><P>支付查看剩下的内容</p><img src="'.$image.'" alt="付费码"><P>支付完成后，刷新页面即可查看全文。</p></div>';
}

/**
 * get options from request to check pay status
 */
add_action('init', 'ptva_pay_check');
function ptva_pay_check() {
   if($_SERVER["REQUEST_URI"] == '/pay_to_view_all') {
      $query_string =  $_POST['attach'];
      $array = explode("+",$query_string);

      $postData = get_post_meta($array[0],'post_paid_user',true);
      $postData = $postData.",".$array[1];
      update_post_meta($array[0],'post_paid_user',$postData);
      update_post_meta($array[0],'paid_qrcode_cache','0');
      echo "ok";
      exit();
   }
}

/**
 * Add Init Meta To every post
 */
function ptva_add_meta_to_all_post() {
    $args = array(
        'posts_per_page'   => -1,
        'post_type'        => 'post',
        'suppress_filters' => true
    );
    $posts_array = get_posts( $args );
    foreach($posts_array as $post_array)
    {
        add_post_meta($post_array->ID, 'post_paid_user', '0',true);
        add_post_meta($post_array->ID, 'paid_qrcode_cache', '0',true);
    }
}
register_activation_hook( __FILE__, 'ptva_add_meta_to_all_post' );
