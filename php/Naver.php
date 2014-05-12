<?
/**
*	Naver �α��� Api Class 0.2
*   class : NaverAPI
*   Author : Rawady corp. Jung Jintae
*   date : 2014.5.11 
*	https://github.com/rawady/NaverLogin


	! required PHP 5.x Higher
	! required curl enable

*   
*   �� Ŭ������ ���̹� ���� ���̺귯���� �ƴմϴ�. 
*  NHN API Reference : http://developer.naver.com/wiki/pages/NaverLogin_Web
*/



/**

 0.2 ���Ե� 

	- ���� ��û 
	- ��������ū ȹ��
	- ����� ���� ���
	- �α׾ƿ� 

*/


define( NAVER_OAUTH_URL, "https://nid.naver.com/oauth2.0/" );
define( NAVER_SESSION_NAME, "NHN_SESSION" );
@session_start();


class Naver{

	private $tokenDatas	=	array();

	private $access_token			= '';			// oauth ������ ��ū
	private $reefresh_token			= '';			// oauth ���� ��ū
	private $access_token_type		= '';			// oauth ��ū Ÿ��
	private $access_token_expire	= '';			// oauth ��ū ����


	private $client_id		= '';			// ���̹����� �߱޹��� Ŭ���̾�Ʈ ���̵�
	private $client_secret	= '';			// ���̹����� �߱޹��� Ŭ���̾�Ʈ ��ũ��Ű

	private $returnURL		= '';			// �ݹ� ���� URL ( ���̹��� ��ϵ� �ݹ� URI�� �켱��)
	private $state			= '';			// ���̹� ���� �ʿ��� ���� Ű (���� ���� ���̺귯������ �̰���)

	
	private $loginMode		= 'request';	// ���̺귯�� �۵� ����

	private $returnCode		= '';			// ���̹����� ���� ���� ���� �ڵ�
	private $returnState	 = '';			// ���̹����� ���� ���� ���� �ڵ�

	private $nhnConnectState	= 'empty';


	private $curl = NULL; 

	function __construct($argv = array()) {

		

		if  ( ! in_array  ('curl', get_loaded_extensions())) {
			echo 'curl required';
			return false;
		}

		
		if($argv['CLIENT_ID']){
			$this->client_id = trim($argv['CLIENT_ID']);
		}

		if($argv['CLIENT_SECRET']){
			$this->client_secret = trim($argv['CLIENT_SECRET']);
		}

		if($argv['RETURN_URL']){
			$this->returnURL = trim(urlencode($argv['RETURN_URL']));
		}

		$this->loadSession();

		if(isset($_GET['nhnMode']) && $_GET['nhnMode'] != ''){
			$this->loginMode = 'logout';
			$this->logout();
		}

		if($this->getConnectState() == 'empty'){
			$this->generate_state();

			if($_GET['state'] && $_GET['code']){
				$this->loginMode = 'request_token';
				$this->returnCode = $_GET['code'];
				$this->returnState = $_GET['state'];
			}
		}
	}



	function login(){


		if($this->loginMode == 'request' && ($this->getConnectState() != "connected")){
			echo '<a href="javascript:loginNaver();"><img src="https://www.rawady.com:5014/open/idn/naver_login.png" alt="���̹� ���̵�� �α���" width="200"></a>';
			echo '
			<script>
			function loginNaver(){
				var win = window.open(\''.NAVER_OAUTH_URL.'authorize?client_id='.$this->client_id.'&response_type=code&redirect_uri=&state='.$this->state.'\', \'���̹� ���̵�� �α���\',\'width=320, height=480, toolbar=no, location=no\');   
				var timer = setInterval(function() {   
					if(win.closed) {  
						window.location.reload();
					}  
				}, 500); 
			}
			</script>
			';
		}else if($this->getConnectState() == "connected"){
			echo '<a href="https://'.$_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"].'?nhnMode=logout"><img src="https://www.rawady.com:5014/open/idn/naver_logout.png" width="120" alt="���̹� ���̵� �α׾ƿ�"/></a>';
		}

		if($this->loginMode == 'request_token'){
			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_URL, NAVER_OAUTH_URL.'token?client_id='.$this->client_id.'&client_secret='.$this->client_secret.'&grant_type=authorization_code&code='.$this->returnCode.'&state='.$this->returnState);
			curl_setopt($this->curl, CURLOPT_POST, 1); 
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data); 
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER,true); 
			$retVar = curl_exec($this->curl); 
			curl_close($this->curl);
			$NHNreturns = json_decode($retVar);

			if(isset($NHNreturns->access_token)){
				$this->access_token			= $NHNreturns->access_token;
				$this->access_token_type	= $NHNreturns->token_type;
				$this->reefresh_token		= $NHNreturns->refresh_token;
				$this->access_token_expire	= $NHNreturns->expires_in;

				$this->updateConnectState("connected");

				$this->saveSession();

				echo "<script>window.close();</script>";

			}
		}
	}

	function logout(){

		$this->curl = curl_init();
		curl_setopt($this->curl, CURLOPT_URL, NAVER_OAUTH_URL.'token?client_id='.$this->client_id.'&client_secret='.$this->client_secret.'&grant_type=delete&refresh_token='.$this->reefresh_token.'&sercive_provider=NAVER');
		curl_setopt($this->curl, CURLOPT_POST, 1); 
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data); 
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER,true); 
		$retVar = curl_exec($this->curl); 
		curl_close($this->curl);
		
		$this->deleteSession();
		

		echo "<script>window.location.href = 'http://".$_SERVER["HTTP_HOST"] . $_SERVER['PHP_SELF']."';</script>";
	}


	function getUserProfile(){
		if($this->getConnectState() == "connected"){
			$data = array();
			$data['Authorization'] = $this->access_token_type.' '.$this->access_token;

			$this->curl = curl_init();
			curl_setopt($this->curl, CURLOPT_URL, 'https://apis.naver.com/nidlogin/nid/getUserProfile.xml');
			curl_setopt($this->curl, CURLOPT_POST, 1); 
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data); 
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array(
				'Authorization: '.$data['Authorization']
			));

			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER,true); 
			$retVar = curl_exec($this->curl); 
			curl_close($this->curl);

			$xml = new SimpleXMLElement($retVar);
  
			$xmlJSON = array();
			$xmlJSON['result']['resultcode'] = (string) $xml->result[0]->resultcode[0];
			$xmlJSON['result']['message'] = (string) $xml->result[0]->message[0];

			if($xml->result[0]->resultcode == '00'){
				foreach($xml->response->children() as $response => $k){
					$xmlJSON['response'][(string)$response] = (string) $k;
				}
			}

			return json_encode($xmlJSON);
		}else{

		}
	}



	/**
	*	Get AccessToken 
	*	�߱޵� ������ ��ū�� ��ȯ�մϴ�. ������ ��ū �߱��� �α��� �� �ڵ����� �̷�����ϴ�.
	*/
	function getAccess_token(){
		if($this->access_token){
			return $this->access_token;
		}
	}

	/**
	*	 ���̹� ������¸� ��ȯ�մϴ�.
	*    ������ ��ū �߱�/������ �̷���� �� connected ���°� �˴ϴ�.
	*/
	function getConnectState(){
		return $this->nhnConnectState;
	}



	private function updateConnectState($strState = ''){
		$this->nhnConnectState = $strState;
	}


	/**
	*	����� ���ǿ� ����մϴ�.
	*/
	private function saveSession(){
		
		if(isset($_SESSION) && is_array($_SESSION)){
			$_saveSession = array();
			$_saveSession['access_token']		=	$this->access_token;
			$_saveSession['access_token_type']	=	$this->access_token_type;
			$_saveSession['reefresh_token']		=	$this->reefresh_token;
			$_saveSession['access_token_expire']	=	$this->access_token_expire;
			
			$this->tokenDatas = $_saveSession;

			foreach($_saveSession as $k=>$v){
				$_SESSION[NAVER_SESSION_NAME][$k] = $v;
			}
		}
	}


	private function deleteSession(){
		if(isset($_SESSION) && is_array($_SESSION) && $_SESSION[NAVER_SESSION_NAME]){
			$_loadSession = array();
			$this->tokenDatas = $_loadSession;

			unset($_SESSION[NAVER_SESSION_NAME]);

			$this->access_token			= '';
			$this->access_token_type	= '';
			$this->reefresh_token		= '';
			$this->access_token_expire	= '';
			$this->updateConnectState("empty");
		}
	}


	/**
	*	����� ��ū�� �����մϴ�.
	*/
	private function loadSession(){

		if(isset($_SESSION) && is_array($_SESSION) && $_SESSION[NAVER_SESSION_NAME]){
			$_loadSession = array();
			$_loadSession['access_token']		=	$_SESSION[NAVER_SESSION_NAME]['access_token'] ? $_SESSION[NAVER_SESSION_NAME]['access_token'] : '';
			$_loadSession['access_token_type']	=	$_SESSION[NAVER_SESSION_NAME]['access_token_type'] ? $_SESSION[NAVER_SESSION_NAME]['access_token_type'] : '';
			$_loadSession['reefresh_token']		=	$_SESSION[NAVER_SESSION_NAME]['reefresh_token'] ? $_SESSION[NAVER_SESSION_NAME]['reefresh_token'] : '';
			$_loadSession['access_token_expire']	=	$_SESSION[NAVER_SESSION_NAME]['access_token_expire'] ? $_SESSION[NAVER_SESSION_NAME]['access_token_expire']:'';
			
			$this->tokenDatas = $_loadSession;

			$this->access_token			= $this->tokenDatas['access_token'];
			$this->access_token_type	= $this->tokenDatas['access_token_type'];
			$this->reefresh_token		= $this->tokenDatas['refresh_token'];
			$this->access_token_expire	= $this->tokenDatas['expires_in'];

			$this->updateConnectState("connected");

			$this->saveSession();
		}
	}



	private function generate_state() {
        $mt = microtime();
		$rand = mt_rand();
		$this->state = md5( $mt . $rand );
    }
}