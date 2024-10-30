<?php
/**
 * @package informa
 * @version 0.6.5
 */
/*
 * Plugin Name: informa<sup>&reg;</sup>
 * Plugin URI: http://wordpress.org/plugins/informa
 * Description: Wordpress integration to the <strong>informa<sup>&reg;</sup> DQS</strong> marketing database service
 * Version: 0.6.5
 * Author: Alchemetrics Ltd
 * License: GPLv2 or later
 * 
 */
/*
 This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

/** \mainpage Wordpress Informa DQS Integration
 * 
 * @author Francis Wallinger
 *
 */

include "InformaSettingsPage.php";

class informaQSet {
	public $name = NULL;
	public $option = NULL;
	public $QSet = NULL;
	public $QSetID = NULL;
	
	public $defKey = NULL;
	public $keyType = NULL;
	
	private $botLock = FALSE;

	public function __construct($attrs){
		global $informa;
		$this->keyType = $informa->option['defKey'];
			
		if ($attrs && $attrs['qs']) {
		
			$this->options = Array();
			foreach ($attrs as $key => $value) {
				switch ($key) {
					case 'defaults':
					case 'overrides':
					case 'formaction':
					case 'testData':
						$this->options[$key] = $value;
						break;
					case 'qs':
						$this->name = $value;
						break;
					case 'key':
						switch ($value) {
							case 'session':
							case 'cookie':
								$this->keyType = $value;
								break;
							default:
								throw new Exception("Unknown key '{$value}' in dqs shortcode");
						}
						break;
					case 'botlock':
						$this->botLock = $value;
						break;
					default:
						throw new Exception("Invalid option '{$key}={$value}' in dqs shortcode");
				}
			}
			if (!count($this->options)) $this->options = NULL;
		}
	}
	
	/*
	 * If we don't already have the QSet then get it
	 * If this page is the result of a submit, then we will already have it
	 * 		(and it may include server-side generated validation errors)
	 */
	public function build($dqs){
		global $informa;
		if (!$dqs) throw new Exception('No connection to informa');
		if (!isset($this->QSet)) {
			if (isset($this->keyType)){
				$this->QSet = $dqs->getQSetFieldset($informa->option['user'],$informa->option['pass'],$this->name,
						$this->keyType, $informa->getKeyValue($this->keyType), $this->options);
			} else {
				$this->QSet = $dqs->getQSetNoIdentFieldset($this->option['user'],$this->option['pass'],$this->name,
						$this->options);
			}
		}
		return ($this->QSet);
	}

	public function getQSAnswer($dqs){
		global $informa;
		if (!$dqs) throw new Exception('No connection to informa');
		if (isset($this->keyType)){
			return $dqs->getQSAnswers($informa->option['user'],$informa->option['pass'],$this->name,
					$this->keyType, $informa->getKeyValue($this->keyType), 'json');
		} else throw new Exception('No key to identify person');
	}
	/*
	 * Add anything needed to the HTML generated
	 * 
	 */
	public function getHTML(){
		/*
		 * add some javascript to set the botlock field's value to be its name
		* The field should be a hidden question that has a validation forcing the length of the field
		* better bot locker would be to attach this code to a human event such as 'focus'
		* For sites with heavy bot attacks, a calculated value may also give better bot rejection
		*/
		$html = $this->QSet->html;
		if (isset($this->botLock)){
			$html .= "
<!-- botlocker from alchemetrics -->
<script type='text/javascript'>
var dqs_fields = document.getElementsByTagName('INPUT');
for (var i=0; i<dqs_fields.length; i++) {
	if (dqs_fields[i].type == 'hidden' && dqs_fields[i].value == '$this->botLock') {
		dqs_fields[i].value = 'OK';
	};
}
</script>
";
		}
		return $html;
	}
}

class Informa {
	
	private $base = NULL;
	private $fe_base  = NULL;
	protected $wsdl = NULL;
	
	protected $QSets = NULL;
	
	private $dqs = NULL;
	
	private $cookieId = NULL;
	private $sessionId = NULL;
	
	private $errorCnt = 0;
	private $errorMsgs = NULL;
	
	
	/** Initialisation - Parameters specified here override options set in wordpress
	 * @param String $scope Default scope for informa system, should be 'live', 'dev' or 'uat'.
	 * @param String $client Client area within informa
	 * @param String $user API user within informa
	 * @param String $pass API user secret password within informa
	 */
	public function __construct(){
		
		$this->option = get_option('informa');
		
		$this->base = "https://dqs.alchemetrics.co.uk/{$this->option['platform']}/informa/{$this->option['client']}";
		$this->fe_base = "https://www.alchemetrics.co.uk/{$this->option['platform']}/informa/{$this->option['client']}/res";
		$this->wsdl = "{$this->base}/dqs/api/DQSWebService.php?wsdl";

		$this->QSets = array();
		
		/*
		 * Tell wordpress where we want to be plugged in (if we are not in admin screens)
		 */
		
		add_action('widgets_init', array($this,'registerInformaWidget'));

		if (!is_admin()) {
			add_action('init',array($this,'init'));
			
			add_action('wp_head',array($this,'getHeadHTML')); // TODO: Should enqueue apparently
			
			add_action('parse_request',array($this,'putQSet'));
			add_action('parse_request',array($this,'putAppQSet'));
			add_action('template_redirect',array($this,'doRedirect'));
				
			// If the admin parameter is set, queue up the page view DQS put
			if (!empty($this->option['pageHistoryQS'])) {
				add_action('parse_request',array($this,'putClickStream'));
			}

			// This is how we show errors, as the errors can occur before the page is rendered
			add_action( 'wp_before_admin_bar_render', array($this,'errorNoticeMenu') );
			
			// Here is the shortcode user interface(s) to informa
			add_shortcode('dqs',array($this,'getQSet'));
			// add_shortcode('dqsAnswer',array($this,'getQSAnswers')); // For testing function, but not for live
		}
	}
	
	/**
	 * Initialise the SOAP interface and cookies
	 */
	public function init(){
		try {
			$this->dqs = new SoapClient($this->wsdl);
			
			if (isset($_COOKIE['informaUserId'])){
				$this->cookieId = $_COOKIE['informaUserId'];
			} else {
				$this->cookieId = $this->guidv4();
			}
			// This refreshes the cookie timeout if it existed previously
			setcookie('informaUserId',$this->cookieId,time()+60*60*24*$this->option['cookieExpire']);
			
			if (isset($_COOKIE['informaSessionId'])){
				$this->sessionId = $_COOKIE['informaSessionId'];
			} else {
				$this->sessionId = $this->guidv4();
			}
			setcookie('informaSessionId',$this->sessionId,0);
				
		} catch (Exception $e) {
			if (class_exists("SoapClient")) {
				$this->addError("Initialising DQS: ".$e->getMessage());
			} else {
				$this->addError("PHP needs to have the php_soap extension installed");
			}
		}
	}
	
	/** Include validation and i18n javascript
	 * @return String HTML to include required files from www.alchemetrics.co.uk
	 */
	public function getHeadHTML(){
		if ($this->dqs) {
			$url = get_site_url();
			echo "<script language='javascript' type='text/javascript' src='{$this->fe_base}/Informa/Validate.js'></script>".
			 "<script language='javascript' type='text/javascript' src='{$this->fe_base}/Informa/TransEN.js'></script>".
			 "<script language='javascript' type='text/javascript' src='{$this->fe_base}/Informa/EffectDQS.js'></script>".
			 "<link rel='stylesheet' id='informa-css' href='{$url}/wp-content/plugins/informa/informa.css' type='text/css' media='all'>";
		}		
	}
	
	/** Get and a DQS QuestionSet
	 * @param String $qs The name of the Question Set
	 */
	public function getQSet($attrs){
		try {
			if (isset($attrs) && isset($attrs['qs'])){
				if (!isset($this->QSets[$attrs['qs']])){
					$QSet = new informaQSet($attrs);
					$this->QSets[$attrs['qs']] = $QSet;
				}
				$QSet->build($this->dqs);
				return  $QSet->getHTML();
			} else throw new Exception('No Question Set specified in dqs shortcode');
		} catch (Exception $e) {
			$this->addError($e);
			return "<div class='dqs error'>Invalid dqs shortcode</div>";
		}
	}
	/** Get and a DQS QuestionSet
	 * @param String $qs The name of the Question Set
	 */
	public function getQSAnswer($attrs){
		try {
			if (isset($attrs) && isset($attrs['qs'])){
				if (!isset($this->QSets[$attrs['qs']])){
					$QSet = new informaQSet($attrs);
					$this->QSets[$attrs['qs']] = $QSet;
				}
				$json = $QSet->getQSAnswer($this->dqs);	
				$A =  json_decode($json->response);
				$B = (array)$A[0][0];
				return $B;
			} else throw new Exception('No Question Set specified in dqs shortcode');
		} catch (Exception $e) {
			$this->addError($e);
			return NULL;
		}
	}
	/** Retrieve a QuestionSet's values
	 *
	 * Note overlay questions don't currently work, only original questions should be used here
	 */
	public function getQSAnswers($attrs){
		try {
			$this->keyType = $this->option['defKey'];
			
			if ($attrs && $attrs['qs']) {
				
				$options = Array();
				foreach ($attrs as $key => $value) {
					switch ($key) {
						case 'qs':
							$qsname = $value;
							break;
						case 'key':
							switch ($value) {
								case 'session':
								case 'cookie':
									$this->keyType = $value;
									break;
								default: 
									throw new Exception("Unknown key '{$value}' in dqsAnswer shortcode");							}
							break;
						default: 
							throw new Exception("Invalid option '{$key}={$value}' in dqsAnswer shortcode");
					}
				}
				if (!count($options)) $options = NULL;
				
				/*
				 * If we don't already have the QSet then get it
				 * If this page is the result of a submit, then we will already have it 
				 * 		(and it may include server-side generated validation errors)
				 */
				if (isset($this->keyType)){
					$resp = $this->dqs->getQSAnswers($this->option['user'],$this->option['pass'],$qsname,
								$this->keyType, $this->getKeyValue($this->keyType), 'json');
				} 
//				$ret =  "<pre style='display: block; width: 100%;'>" .print_r($resp, true) .print_r($_SERVER, true) . "</pre>"; 
//				$ret = $this->QSet->html; 
				return  $resp;
			}
			throw new Exception('No Question Set specified in dqs shortcode');
		} catch (Exception $e) {
			$this->addError($e);
			return "<div class='dqs error'>Invalid dqs shortcode</div>";
		}
	}

	/** Redirect based on DQS variables
	 * 
	 * Settings required: 
	 * 		redirect_src is the partial url which if matches will execute the redirect,
	 * 		dqs_redirect_q is the DQS Question name containing the url to redirect to
	 * 
	 * Redirect if our page matches redirect_src unless the redirect API put is being set.
	 * Therefore, on a redirect page, passing dqs=redirForm&dqs_redireQuestion=newpage will stop the redirection happening until
	 * the page is called without those parameters.
	 */
	public function doRedirect(){
		try {
			if (!is_admin() && !empty($this->option['redirect_src'])) {
				if (empty($this->option['dqs_redirect_q']))
					throw new Exception("Redirection pattern set, but no 'redirection to' question is configured");
				$page = strtolower($_SERVER['REQUEST_URI']);
				$key = strtolower($this->option['redirect_src']);
				if ($page[strlen($page)-1]=='/') $page = substr($page,0,strlen($page)-1);
				$subpage = substr($page,strlen($page)-strlen($key));
				if ($key == $subpage) {
					// Get the QS and redirect to it
					if (isset($_COOKIE[$this->option['dqs_redirect_q']]) && !isset($_REQUEST['dqs_'.$this->option['dqs_redirect_q']])){
						$dest = $_COOKIE[$this->option['dqs_redirect_q']];
						wp_redirect(home_url("/{$dest}/"));
					}
				}
			}
		} catch (Exception $e) {
			$this->addError("Redirect failed: ".$e->getMessage());
		}
	}
	
	/** Get the appropriate key value
	 * 
	 */
	public function getKeyValue($key=NULL){
		if (!$key) $key = $this->option['defKey'];
		switch ($key) {
			case 'session':
				return $this->sessionId;
				break;
			case 'cookie':
				return $this->cookieId;
		}
		
	}
	/** Put the QuestionSet
	 * 
	 * If a DQS form has been submitted, process it
	 * Store the result so that it can be redisplayed on the page if desired
	 */
	public function putQSet(){
		try {
			if (!is_admin() && !empty($_POST) && isset($_POST['dqs_id'])) {
				if (!$this->dqs) throw new Exception('DQS Not initialised');
				$this->QSet = $this->dqs->putQSet($this->option['user'],$this->option['pass'],$_POST);
				$this->QSetID = $_POST['dqs_id'];
			}
		} catch (Exception $e) {
				$this->addError("Sending form: ".$e->getMessage());
		}
	}

	/** Put the Clickstream QuestionSet
	 *
	 * If a DQS form has been submitted, process it
	 * Store the result so that it can be redisplayed on the page if desired
	 */
	public function putClickStream(){
		try {
			if (!is_admin() && !empty($this->option['pageHistoryQS'])) {
				if (!$this->dqs) throw new Exception('DQS Not initialised');
				$form = array(
					'dqs_keyType' => $this->option['defKey'],
					'dqs_keyValue' => $this->getKeyValue(),
					'dqs_id' => $this->option['pageHistoryQS'],
					'REQUEST_URI' => $_SERVER['REQUEST_URI'],
					'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT']
				);
				$this->dqs->putQSet($this->option['user'],$this->option['pass'],$form);
			}
		} catch (Exception $e) {
			$this->addError("Clickstream: ".$e->getMessage());
		}
	}
	
	/** Put the QuestionSet in App mode
	 *
	 * If there is a dqs GET/POST parameter, submit it with any other GET/POST parameters
	 * that start 'dqs_' having removed the 'dqs_'.
	 * 
	 * If one of the parameters is the DQS question for redirection, store that in a cookie for speed
	 */
	public function putAppQSet(){
		try {
			if (!is_admin() && !empty($_REQUEST) && !empty($_REQUEST['dqs'])) {
				if (!$this->dqs) throw new Exception('DQS Not initialised');
				$form = array(
					'dqs_keyType' => $this->option['defKey'],
					'dqs_keyValue' => $this->getKeyValue(),
					'dqs_id' => $_REQUEST['dqs']
				);
				foreach ($_REQUEST as $key => $val) {
					if (stristr($key,'dqs_')) {
						$key = strtolower(substr($key,4));
						$form[$key] = $val;
						
						// If this is the redirect question, store it in a cookie 
						if (isset($this->option['dqs_redirect_q']) && $key == $this->option['dqs_redirect_q']) {
							$_COOKIE[$this->option['dqs_redirect_q']] = $val;
							setcookie($this->option['dqs_redirect_q'],$val,time()+60*60*24*$this->option['cookieExpire']);
						}
					}
				}					
				$this->dqs->putQSet($this->option['user'],$this->option['pass'],$form);
			}
		} catch (Exception $e) {
			$this->addError("App Mode DQS Put: ".$e->getMessage());
		}
	}

	// register the widget
	function registerInformaWidget() {
		register_widget( 'InformaWidget' );
	}
	
	/**
	 * Generate a UUID to the V4 spec
	 */
	private function guidv4()
	{
	    $data = openssl_random_pseudo_bytes(16);
	
	    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0010
	    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
	
	    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	
	/** Error handling
	 * if passed an object, assume it is an Exception
	 */
	private function addError($msg){
		if (is_object($msg)) $msg = $msg->getMessage();
		$this->errorMsgs[] = $msg;
	}
	public function hasErrors() {
		return (count($this->errorMsgs));
	}
	public function errorNoticeMenu(){
		global $wp_admin_bar;
				
		if ($this->hasErrors()) {
			$wp_admin_bar->add_node(array(
				'id'    => 'dqs-error',
				'title' => '<span class="error">DQS Error</span>'
			));
			$i=1;
			foreach ($this->errorMsgs as $msg) {
				$wp_admin_bar->add_menu( array(
					'id'    => 'dqs-error-'.$i++,
					'title' => $msg,
					'parent'=>'dqs-error'
				));
			}				
		}
	}
}

class InformaWidget extends WP_Widget {
	/**
	 * Register widget with WordPress.
	 */
	function __construct() {
		parent::__construct(
				'InformaWidget', // Base ID
				__('Informa DQS', 'text_domain'), // Name
				array( 'description' => __( 'A Widget that presents a  DQS Question Set', 'text_domain' ), ) // Args
		);
	}
	
	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		global $informa;
		
		$QSetName = apply_filters( 'Informa DQS', $instance['QSet'] );
		try {
			$QSet = $informa->getQSet(array('qs'=>$QSetName));
			echo $args['before_widget']; 
			echo $QSet;
			echo $args['after_widget'];
		} catch (Exception $e) {
			$informa->addError('Widget: '.$e->getMessage());
		}
	}
	
	/**
	 * Back-end widget form.
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
	if ( isset( $instance[ 'QSet' ] ) ) {
		$QSet = $instance[ 'QSet' ];
	}
	else {
		$QSet = __( 'QS Name', 'text_domain' );
	}
	?>
		<p>
		<label for="<?php echo $this->get_field_id( 'QSet' ); ?>"><?php _e( 'Question Set:' ); ?></label> 
		<input class="widefat" id="<?php echo $this->get_field_id( 'QSet' ); ?>" name="<?php echo $this->get_field_name( 'QSet' ); ?>" type="text" value="<?php echo esc_attr( $QSet ); ?>" />
		</p>
		<?php 
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['QSet'] = ( ! empty( $new_instance['QSet'] ) ) ? strip_tags( $new_instance['QSet'] ) : '';

		return $instance;
	}
}
$informa = new Informa ();

