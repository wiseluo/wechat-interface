<?php
class BmAction {
	//5分钟失效
	public function validity_time($t){
		$gzhid = $_GET['gzhid'];
		$t = $t + 300;
		if($t < time()){//5分钟的有效期
			header('location:http://'.$_SERVER['SERVER_NAME'].'/bm/bmsx');
		}else{
			header('location:http://'.$_SERVER['SERVER_NAME'].'/bm/index/gzhid/'.$gzhid);
		}
	}
	public function index() {
		//var_dump($_POST);
		//var_dump($_SESSION);
		//var_dump($_GET);
		//var_dump('http://'.$_SERVER['SERVER_NAME'].$_SERVER["REQUEST_URI"]);
		//微信授权登录
		$nowtime = time();
		$oauth = new Oauth();
		if(!empty($_GET['code']) && !empty($_GET['state'])){//用户允许授权
			$code = $_GET['code'];
			if($_GET['state'] == $_SESSION['state'] && empty($_POST)){
				$userInfo_json = $oauth->get_user_info($code);
				//var_dump($userInfo_json);
				if($userInfo_json == 0){//code只能用一次，刷新和返回时，code已失效，不能再用于获取用户信息,直接获取session中的数据
					$this->assign("returnstatus", 5);
				}else{//给session赋值
					$_SESSION['wx_gzhid'] = $_GET['gzhid']; //公众号id
					$_SESSION['wx_unionid'] = $userInfo_json->unionid;//不同用户在不同公众号下有不同openid，但unionid唯一
					//$_SESSION['wx_openid'] = $userInfo_json->openid;//用户在服务号下的openid
					$_SESSION['wx_nickname'] = $userInfo_json->nickname;
					$_SESSION['wx_sex'] = $userInfo_json->sex;
					$_SESSION['wx_province'] = $userInfo_json->province;
					$_SESSION['wx_city'] = $userInfo_json->city;
					$_SESSION['wx_country'] = $userInfo_json->country;
					$_SESSION['wx_headimgurl'] = $userInfo_json->headimgurl;
					$this->assign("returnstatus", 0);
				}
			}
		}else if(empty($_GET['code']) && !empty($_GET['state'])){//用户禁止授权
			$this->assign("returnstatus", 5);
		}else{//请求微信授权
			//$redirectUri = 'http://www.aiyougood.cn/index.php/bm';
			$redirectUri = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER["REQUEST_URI"];
			$snsapi = 'snsapi_userinfo';
			$state = 'aiyougood'.$nowtime;
			$_SESSION['state'] = $state;
			$oauth->get_code($redirectUri, $snsapi, $state);
			//echo '请求授权';
		}
		
		$this->display("index");
	}
	//微信分享
	public function fx($gzhid){
		$oauth = new Oauth();
		$data = $oauth->getAppidByToUserName($gzhid);
		if($data === 0){
			return 0;
		}
		$appid = $data['appid'];
		$appsecret = $data['appsecret'];
		$url = $oauth->getUrlBygzhid($gzhid);
		if($url != ''){
			$Hd_info['url'] = $url;
		}
		include("jssdk.php");
		$jssdk = new JSSDK($appid, $appsecret);
		$signPackage = $jssdk->GetSignPackage();
		$appid = $signPackage["appId"];
		$timestamp = $signPackage["timestamp"];
		$nonceStr = $signPackage["nonceStr"];
		$signature = $signPackage["signature"];
		$this->assign("appid",$appid);
		$this->assign("timestamp",$timestamp);
		$this->assign("nonceStr",$nonceStr);
		$this->assign("signature",$signature);
		$this->display();
	}
	//获取永久素材列表，匹配出当前活动的素材，获取素材的url
	public function batchget_material($appid, $appsecret, $qihao){
		$oauth = new Oauth();
		$access_token = $oauth->get_access_token($appid, $appsecret);
		$url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=".$access_token;
		$jsonmaterial = '{"type": "news","offset": 0,"count": 20}';
		$output = $oauth->https_request($url, $jsonmaterial);
		$res = json_decode($output, true);
		//var_dump($res);
		if($res['errcode']){
			return '';
		}else{
			$item = $res['item'];
			$url = '';
			foreach($item as $value){
				if(preg_match("/$qihao/", $value['content']['news_item'][0]['title'])){
					$url = $value['content']['news_item'][0]['url'];
				}
			}
			if($url == ''){
				return '';
			}else{
				return $url;
			}
		}
	}
}
?>