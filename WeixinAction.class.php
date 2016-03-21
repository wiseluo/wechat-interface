<?php
class WeixinAction {
	private $token;
	private $appid;
	private $appsecret;
	private $errmysql;
	public function __construct()
	{
		$this->token = '消息验证token';
		$this->errmysql = "本公众号正在维护中......";
	}
	public function index(){
		if(!isset($_GET['echostr'])) {
			$this->responseMsg();
		}else{
			$this->valid();
		}
	}
	//验证消息真实性，用于公众号平台服务器配置绑定到这个程序时的提交验证
	public function valid(){
		$echostr = $_GET['echostr'];
		if($this->checkSignature()) {
			echo $echostr;
			exit;
		}
	}

	//验证签名
	private function checkSignature(){
		$token = $this->token;
		$timestamp = $_GET['timestamp'];
		$nonce = $_GET['nonce'];
		$signature = $_GET['signature'];
		
		$tmpArr = array($token,$timestamp,$nonce);
		sort($tmpArr,SORT_STRING);
		$tmpStr = implode($tmpArr);
		$tmpStr = sha1($tmpStr);
		
		if($tmpStr == $signature) {
			return true;
		} else {
			return false;
		}
	}
	//响应消息
	public function responseMsg()
	{
		$postStr = $GLOBALS['HTTP_RAW_POST_DATA'];
		if(!empty($postStr)) {
			$postObj = simplexml_load_string($postStr,'SimpleXMLElement',LIBXML_NOCDATA);
			$RX_TYPE = trim($postObj->MsgType);
			$fromUsername = trim($postObj->FromUserName);
			$toUsername = trim($postObj->ToUserName);
			switch($RX_TYPE)
			{
				case 'text':
					$result = $this->receiveText($postObj);
				break;
				case 'event':
					$result = $this->receiveEvent($postObj);
				break;
				default:
					$result = "unknown message type: " . $RX_TYPE;
				break;
			}
			echo $result;
		} else {
			echo 'postStr empty';
			return;
			exit;
		}
	}

	//接收文本消息
	private function receiveText($object)
	{
		switch($object->Content)
		{
			case '报名入口':
				$content = $this->bmrk($object->FromUserName, $object->ToUserName);
			break;
			case '首页':
				$content[] = array('Title' => "活动报名传送门，点击开始报名",'Description' => "",'PicUrl' => "http://www.XXX.cn/upload_img",
					'Url' => '');
			break;
			default:
				//$content = $object->ToUserName;
				$content = '暂时没有相应回复！';
			break;
		}
		if(is_array($content)) {
			if(isset($content[0]['PicUrl'])) {
				$result = $this->transmitInfo($object,$content);
			}
		} else {
			$result = $this->transmitText($object,$content);
		}
		return $result;
	}

	//接收事件推送
	private function receiveEvent($object)
	{
		$content = "";
		$fromUsername = trim($object->FromUserName);//object转换为string
		$toUsername = trim($object->ToUserName);
		switch($object->Event)
		{
			case 'subscribe':
				$res = $this->wx_login($fromUsername, $toUsername);
				if($res == 1){
					$content = "欢迎关注！";
				}else{
					$content = $res;
				}
			break;
			case 'unsubscribe':
				$content = "取消关注";
			break;
			case 'CLICK':
				switch($object->EventKey)
				{
					case 'clickkey1': //我是谁
						$oauth = new Oauth();
						$data = $oauth->getAppidByToUserName($toUsername);
						if($data === 0){
							return 0;
						}
						$appid = $data['appid'];
						$appsecret = $data['appsecret'];
						$access_token = $oauth->get_access_token($appid, $appsecret);
						//获取用户信息
						$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=" .$access_token . "&openid=" . $object->FromUserName . "&lang=zh_CN";
						$output = $oauth->https_request($url);
						$jsoninfo = json_decode($output,true);
						if($jsoninfo['sex'] == 1) {
							$sex = "男";
						} else if($jsoninfo['sex'] == 2) {
							$sex = "女";
						} else {
							$sex = "未知";
						}
						$content = array();
						$content[] = array('Title' => "我知道你是谁！",'Description' => "昵称：" . $jsoninfo['nickname'] .
							"\r\n" . "性别：" . $sex . "\r\n" . "国家：" . $jsoninfo['country'] . "\r\n" . "省份：" .
							$jsoninfo['province'] . "\r\n" . "城市：" . $jsoninfo['city'],'PicUrl' => $jsoninfo['headimgurl'],
							'Url' => '');
					break;
					default:
						$content = "该按钮暂时尚未添加事件！";
					break;
				}
			break;
			default:
				//$content = "对不起，目前暂不受理此事件！";
				//$content = $object->Event;
			break;
		}
		if(is_array($content)) {
			if(isset($content[0]['PicUrl'])) {
				$result = $this->transmitInfo($object,$content);
			}
		} else {
			$result = $this->transmitText($object,$content);
		}
		return $result;
	}
	
	//发送文本消息
	private function transmitText($object,$content)
	{
		$textTpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[text]]></MsgType>
			<Content><![CDATA[%s]]></Content>
			</xml>";
		$result = sprintf($textTpl, $object->FromUserName, $object->ToUserName, time(), $content);
		return $result;
	}

	//发送单图文消息
	private function transmitInfo($object,$infoArray)
	{
		if(!is_array($infoArray)) {
			return;
		}
		$itemTpl = "<item>
			<Title><![CDATA[%s]]></Title>
			<Description><![CDATA[%s]]></Description>
			<PicUrl><![CDATA[%s]]></PicUrl>
			<Url><![CDATA[%s]]></Url>
			</item> ";
		$item_str = "";
		foreach ($infoArray as $item){
			$item_str .= sprintf($itemTpl, $item['Title'], $item['Description'], $item['PicUrl'],
			$item['Url']);
		}
		$infoTpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[news]]></MsgType>
			<Content><![CDATA[]]></Content>
			<ArticleCount>%s</ArticleCount>
			<Articles> $item_str</Articles>
			</xml>";
		$result = sprintf($infoTpl, $object->FromUserName, $object->ToUserName, time(),
		count($infoArray));
		return $result;
	}
	
	//创建自定义菜单
	public function create_menu($area){
		$oauth = new Oauth();
		$data = $oauth->getAppidByArea($area);
		if($data === 0){
			return 0;
		}
		$appid = $data['appid'];
		$appsecret = $data['appsecret'];
		$access_token = $oauth->get_access_token($appid, $appsecret);
		var_dump($access_token);
		$url = "https://api.weixin.qq.com/cgi-bin/menu/create?access_token=".$access_token;

		$jsonmenu = '{"button": [{"type": "view","name": "viewname1","url": ""},{"name": "buttonname","sub_button": [{"type": "click",
		"name": "clickname1","key": "clickkey1"},{"type": "view","name": "viewname2","url": ""},{"type": "view","name": "viewname3","url": ""},{
        "type": "view","name": "viewname4","url": ""}]},{"type": "click","name": "clickname2","key": "clickkey2"}]}';
        $result = $oauth->https_request($url, $jsonmenu);
		$jsoninfo = json_decode($result,true);
		$errcode = $jsoninfo['errcode'];
		if($errcode != 0){
			var_dump($jsoninfo);
		}else{
			echo "自定义菜单提交成功";
		}
	}
	//获取永久素材列表
	public function batchget_material($area){
		$oauth = new Oauth();
		$data = $oauth->getAppidByArea($area);
		if($data === 0){
			return 0;
		}
		$appid = $data['appid'];
		$appsecret = $data['appsecret'];
		$access_token = $oauth->get_access_token($appid, $appsecret);
		var_dump($access_token);
		$url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=".$access_token;
		$jsonmaterial = '{"type": "news","offset": 0,"count": 20}';
		$res = $oauth->https_request($url, $jsonmaterial);
		var_dump($res);
	}
	//保存微信用户的unionid和它在本公众号下的openid, $openid, $toUserName参数须为字符串
	private function wx_login($openid, $toUserName){
		$oauth = new Oauth();
		$apidata = $oauth->getAppidByToUserName($toUserName);
		if($apidata === 0){
			return 0;
		}
		$appid = $apidata['appid'];
		$appsecret = $apidata['appsecret'];
		$access_token = $oauth->get_access_token($appid, $appsecret);
		//获取用户信息里的unionid
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
		$output = $oauth->https_request($url);
		$jsoninfo = json_decode($output,true);
		if (isset($jsoninfo["errcode"])) {//刷新access_token，重新获取用户信息
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
			$output = $oauth->https_request($url);
			$res = json_decode($output, true);
			if($res['errcode']){
				S($appid, NULL);// 删除缓存数据
				exit($res["errmsg"]);
			}else{
				$cache = array('authorizer_access_token' => $res['access_token'], 'authorizer_expires' => $res['expires_in']);
				S($appid, $cache, $res['expires_in']);
				$access_token = $res['access_token'];
				$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token=".$access_token."&openid=".$openid."&lang=zh_CN";
				$output = $oauth->https_request($url);
				$jsoninfo = json_decode($output,true);
			}
		}
		$unionid = $jsoninfo["unionid"];
		$data['unionid'] = $unionid; //订阅号须绑定到服务号开放平台的公众账号下才能获取unionid
		$data['openid'] = $jsoninfo['openid'];
		$data['nickname'] = $jsoninfo['nickname'];
		$data['gzhid'] = $toUserName; //string类型
		$wxuser_info = M("Wxuser")->where("unionid='".$unionid."' and openid='".$jsoninfo['openid']."'")->limit("0,1")->find();
		if($wxuser_info === false){
			return 'mysqlerr';
		}else if($wxuser_info === NULL){
			$res1 = M("Wxuser")->add($data);
			if($res1 === false){
				return 'mysqlerr';
			}else{
				return 1;
			}
			//return 'res1:'.$res1;
		}else{
			$res2 = M("Wxuser")->where("unionid='".$unionid."' and openid='".$jsoninfo['openid']."'")->save($data);
			if($res2 === false){
				return 'mysqlerr';
			}else{
				return 1;
			}
			//return 'res2:'.$res2;
		}
	}
}
?>