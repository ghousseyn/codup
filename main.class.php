<?php
/**
 * Configuration class.
 * @version 	1.0
 * @author 	Hussein Guettaf <ghussein@coda-dz.com>
 * @package 	codup
 */
class main {
	
	private $route = array();
	private $path = null;
	
	protected $_layoutEnabled = true;
	protected $_requestVars = array();	
	protected $_view = null;
	protected $vars = array();
	protected $bootstrap = null;
	protected $plugins = null;
	protected $tools = null;
	protected $config = null;
	
	protected function __construct(){
		$this->config = $this->load('config');
		$this->_layoutEnabled = $this->config->layoutEnabled;
		if(!isset($_SESSION)){
			session_start();
		}
				
	}
	
	static function getInstance(){
		return new self();
	}
	function run(){
		$this->checkSession();
		$this->bootstrap = $this->load('bootstrap');
		$this->register('post',false);
		$this->register('get',true);
		$this->register('ajax',false);
		$this->router();
		$this->getView();
		$this->dispatch();
		$this->_view->showTime();
		$_SESSION['user']['activity'] = time();
	
	}
	function _redirect($url, $replace = true, $code = 307){
	
		header("Location: $url",$replace,$code);
	}
	function plugins(){
	
		foreach($this->bootstrap->plugins as $plugin){
			$this->load($plugin,null,"./plugins/".$plugin."/")->run();
		}
	}
	
	function checkSession(){
		if (isset($_SESSION['user']['activity']) && (time() - $_SESSION['user']['activity'] > 1800)) {
			 
			session_unset();
			session_destroy();
		}
	
		if (isset($_SESSION['user']['created']) && (time() - $_SESSION['user']['created'] > 1800)) {
			session_regenerate_id(true);
			$_SESSION['user']['created'] = time();
		}
		//$this->stack("Inactivity: ".($this->load('tools')->convertTime(time() - $_SESSION['user']['activity'])));
	}
	function getView(){	
			$this->_view = $this->load('view');
			$path[] = $this->route['module'];
			$path[] = $this->route['controller'];
			$path[] = $this->route['action'];
			if($path[0] == 'default'){
				array_shift($path);
				$path = "./views/".implode("/",$path).".php";
			}else{
				$path = "./modules/".array_shift($path)."/views/".implode("/",$path).".php";
			}
			
 			
			$tpl = file_get_contents($path);
			$layout = null;

			if($this->_layoutEnabled){
				$layout = "./layouts/layout.php";

				if(file_exists($layout)){
					$layout = file_get_contents($layout);
				}
			}
			

		

		$this->_view->viewPath = $path;	

		$this->register('view',$this->_view);
		//var_dump($path);
	}
	function _view(){
		return $this->get('view');
	} 
	function renderLayout($layout = null){
		if(null != $layout){
			include_once $layout;
		}else{
			include_once "./layouts/layout.php";
		}
	}
	function router(){
		$uri  = urldecode($_SERVER['REQUEST_URI']);

		if($this->isValidURI($uri)){
			$parts = explode("/",$uri);
			array_shift($parts);
			
			
			if(!empty($parts[0]) && $this->bootstrap->isModule($parts[0])){
				
				$module = array_shift($parts);
				
				if($this->hasController($parts,$module)){
					$controller = array_shift($parts);

				}else{
					$controller = "index";
					array_shift($parts);
				}

				if($this->hasAction($parts, $controller)){
					$action = array_shift($parts);
				}else{
					$action = "index";
					array_shift($parts);
				}
			}else{
				$module = "default";
				array_shift($parts);
				$controller = "index";
				array_shift($parts);
				$action = "index";
				array_shift($parts);
			}
			
			
			
			if(count($parts)) {
				$this->setVars($parts);
			}
			if ($_POST) {
  				$this->register('post',true);
				$this->register('get',false);
		 		$this->_requestVars = $_POST;	
			}

			$this->register('_request',$this->_requestVars);

			$this->route = array("module" => $module, "controller" => $controller, "action" => $action, "vars" => $this->get('_request'));
			if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
				$this->register('ajax', true);
			}
		}else{		
			$route = array("module" => "default", "controller" => "index", "action" => "index"); 
			$this->route = $route;	
			$this->errorStack(__class__.":".__line__.": Error: Not a valid URI");
					
		}
	
	}
	function isValidURI($uri){
		$uri = str_replace("/?","/",$uri);
		$uri = str_replace("?","/?",$uri);
		$uri = str_replace("&","/",$uri);
		$uri = str_replace("=","/",$uri);
		
		if (preg_match("~^(?:[/\w\s-]+)+/?$~", $uri) ) {
    			return true;
		}
		return false;
	}
	function isPost(){
		return $this->get('post');
	}
	function isGet(){
		return $this->get('get');
	}
	function getRoute(){
		return $this->route;
	}
	function dispatch(){

		$mod = $this->route["module"];
		$controller = $this->route["controller"];
		$action = $this->route["action"];
	
		

		$instance = $this->load($controller,null,$this->path);
	
		//$instance->init();
		if(array_search($action, get_class_methods($instance))){
			return $instance->{$action}();
		}else{

			$this->errorStack(__class__.":".__line__.": Error: Action ".$action." does not exist!");
			return $instance->index();
		}
		
		
	}
	function _request($var){
		$vars = $this->get('_request');
		
		return $vars[$var];
	}
	function setVars($parts){
		
		foreach($parts as $k => $val){
			if($k == 0 || ($k%2) == 0){
				$this->_requestVars[$parts[$k]] = $parts[$k+1];
			}
		}
		
	}
	private function hasController($parts, $module){
		if($module == "default"){
			$this->path = "./";
		}else{
			$this->path = "./modules/".$module."/";		
		}
		
		if(!empty($parts[0]) && file_exists($this->path."class.".$parts[0].".php")){
			return true;
		}

		return false;
	} 
	private function hasAction($parts, $controller){
		
		if(!empty($parts[0])){
			return true;
		}
		return false;
	} 
	function register($name, $value){
		$_SESSION[$name] = $value;
	}
	
	protected function stack($msg){
		$_SESSION['stack'][] = $msg;
	}

	protected function stackFlush(){
		unset($_SESSION['stack']);
	}

	protected function errorStack($msg){
		$_SESSION['error'][] = $msg;
		$_SESSION['error'] = array_merge($_SESSION['error'], $_SESSION['stack']);
		
		$this->stackFlush();
		
	}

	protected function errorStackFlush(){
		
		unset($_SESSION['error']);
	}

	function get($index){
		if(array_key_exists($index, $_SESSION)){
			return $_SESSION[$index];
		}	
	}

	function load($class, $params = null, $path = ""){
		include_once $path."class.".$class.".php";
		
		$parameters = "";
		if(null != $params && is_array($params) && !is_object($params)){
			$parameters = implode(",",$params);
		}else{
			$parameters = $params;
		}
		
		return $class::getInstance($parameters);
	}
	
	function __set($var, $val){
		$this->vars[$var] = $val;
	}
	function __get($var){
		if($var == 'view'){
			return $this->get('view');
		}
		if($var == 'content'){
			return $this->viewPath;
		}
		return $this->vars[$var];
	}
}
?>