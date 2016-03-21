<?php
/*
* 网站应用微信登录授权验证
*/
class Oauth {
	private $fwappid;
	private $fwappsecret;
	function __construct(){
		parent::__construct();
		$this->fwappid = '';//服务号appid
		$this->fwappsecret = '';
		
	}
	//作用：发起微信网页授权登录请求
	public function get_code($redirectUri = '', $scope = 'snsapi_login', $state = ''){
		$encodeRedirectUri = urlencode($redirectUri);
		//var_dump($encodeRedirectUri);
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$this->fwappid.'&redirect_uri='.$encodeRedirectUri.'&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect';
		header('Location: '.$url);
		//header('location:https://open.weixin.qq.com/connect/qrconnect?appid='.$this->appid.'&redirect_uri='.$encodeRedirectUri.'&response_type=code&scope='.$scope.'&state='.$state.'#wechat_redirect');
	}
	public function get_user_info($code){
		if (empty($code)) $this->error('get code failed');
		$token_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$this->fwappid.'&secret='.$this->fwappsecret.'&code='.$code.'&grant_type=authorization_code';
		$access_token = json_decode(file_get_contents($token_url));
		if (isset($access_token->errcode)) {
			//echo '<h1>err1:</h1>'.$token->errcode;
			//echo '<br/><h2>errMsg1:</h2>'.$token->errmsg;
			return 0;
			exit;
		}

		/*$access_token_url = 'https://api.weixin.qq.com/sns/oauth2/refresh_token?appid='.$this->fwappid.'&grant_type=refresh_token&refresh_token='.$token->refresh_token;
		//转成对象
		$access_token = json_decode(file_get_contents($access_token_url));
		if (isset($access_token->errcode)) {
			return 0;
			exit;
		}*/
		$user_info_url = 'https://api.weixin.qq.com/sns/userinfo?access_token='.$access_token->access_token.'&openid='.$access_token->openid.'&lang=zh_CN';
		//转成对象
		$user_info = json_decode(file_get_contents($user_info_url));
		if (isset($user_info->errcode)) {
			return 0;
			exit;
		}
		return $user_info;
	}
	//获取全局变量access_token 应该全局存储与更新
	public function get_access_token($appid, $appsecret){
		$cache_token = S($appid);
		//var_dump($cache_token);
		if (!$cache_token['authorizer_access_token']) {
			$url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=".$appid."&secret=".$appsecret;
			$output = $this->https_request($url);
			$res = json_decode($output, true);
			if($res['errcode']){
				S($appid, NULL);// 删除缓存数据
				exit('error token');
			}else{
				$cache = array('authorizer_access_token' => $res['access_token'], 'authorizer_expires' => $res['expires_in']);
				S($appid, $cache, $res['expires_in']); //根据appid保存access_token
				$cache_token = $res['access_token'];
			}
			return $cache_token;
		}else{
			return $cache_token['authorizer_access_token'];
		}
	}
	//获取群发出去后的软文url，用于分享到朋友圈
	public function getUrlBygzhid($gzhid){
		$url = '';
		switch($gzhid){
			case '': //公众号id
				$url = '';
			break;
			default:
				return '';
			break;
		}
		return $url;
	}
	//公众号消息交互时通过ToUserName即公众号原始ID找appid
	public function getAppidByToUserName($toUserName){
		$data = array();
		switch($toUserName){
			case '': //公众号id
				$data['appid'] = "";
				$data['appsecret'] = "";
			break;
			default:
				return '';
			break;
		}
		return $data;
	}
	//通过url传参area找appid
	public function getAppidByArea($area){
		$data = array();
		switch($area){
			case '':
				$data['appid'] = "";
				$data['appsecret'] = "";
			break;
			default:
				return '';
			break;
		}
		return $data;
	}
	//微信jssdk
    // jsapi_ticket 应该全局存储与更新
	public function get_jsapi_ticket($appid, $access_token){
		// 如果是企业号用以下 URL 获取 ticket
		// $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
		$api_ticket = S($appid.'ticket');
		if (!$api_ticket['ticket']) {
			$url = 'https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token='.$access_token.'&type=jsapi';
			$output = $this->https_request($url);
			$res = json_decode($output, true);
			if (0 < $res['errcode']) {
				exit('error ticket');
			}else{
				$cache = array('ticket' => $res['ticket'], 'expires_in' => $res['expires_in']);
				S($appid.'ticket', $cache, $res['expires_in']);
				$ticket = $res['ticket'];
			}
			return $ticket;
		}else{
			return $api_ticket['ticket'];
		}
	}
	public function https_request($url,$data = null){
	    $curl = curl_init();
	    $header = 'Accept-Charset: utf-8';
	    curl_setopt($curl, CURLOPT_URL, $url);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
	    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
	    if (!empty($data)){
	        curl_setopt($curl, CURLOPT_POST, 1);
	        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
	    }
	    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
	    $output = curl_exec($curl);
	    curl_close($curl);
	    return $output;
	}
	//获取头像code
	/*public function get_headerimg_code($redirect_uri, $scope='snsapi_base',$state=1){//snsapi_userinfo
		if($redirect_uri[0] == '/'){
			$redirect_uri = substr($redirect_uri, 1);
		}
		$redirect_uri = urlencode($redirect_uri);
		$response_type = 'code';
		$appid = $this->appId;
		$url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.$appid.'&redirect_uri='.$redirect_uri.'&response_type='.$response_type.'&scope='.$scope.'&state='.$state.'#wechat_redirect';
		header('Location: '.$url, true, 301);
	}
		
	public function get_openid($code){
		$grant_type = 'authorization_code';
		$appid = $this->appId;
		$appsn= $this->appSecret;
		$url = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='.$appid.'&secret='.$appsn.'&code='.$code.'&grant_type='.$grant_type.'';
		$data =json_decode(file_get_contents($url),1);
		return $data;
	}
	public function get_user($openid){
		$accessToken = $this->get_access_token();
		$url = "https://api.weixin.qq.com/cgi-bin/user/info?access_token={$accessToken}&openid={$openid}&lang=zh_CN";
		$data  = json_decode(file_get_contents($url),1);
		return $data;
	}
	public function get_user1($accessToken,$openid){
		$url = 'https://api.weixin.qq.com/sns/userinfo?access_token='. $accessToken . '&openid='. $openid .'&lang=zh_CN';
		$data  =json_decode(file_get_contents($url),1);
		return $data;
	}
	
	
	private function get_php_file($filename) {
		$filename = ROOT.'/'.$filename;
		return trim(substr(file_get_contents($filename), 15));
	}
	private function set_php_file($filename, $content) {
		$filename = ROOT.'/'.$filename;
		$fp = fopen($filename, "w");
		fwrite($fp, "<?php exit();?>" . $content);
		fclose($fp);
	}*/
}
?>