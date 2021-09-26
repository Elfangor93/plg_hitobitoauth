<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\User\UserHelper;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Cache\Cache;
use \Joomla\CMS\Uri\Uri;

/**
 * Plugin class for login/register with hitobito account.
 *
 * @since  1.0.0
 */
class PlgSystemHitobitoauth extends JPlugin
{
	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * JOAuth2Client class
	 *
	 * @var    object
	 * @since  1.0.0
	 */
	protected $oauth_client;

	/**
	 * OAuth token
	 *
	 * @var    string
	 * @since  1.0.0
	 */
	protected $token = '';

	/**
	 * User credentials from hitobito
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	protected $credentials = array();

	/**
	 * User credentials from hitobito
	 *
	 * @var    JUser object
	 * @since  1.0.0
	 */
	protected $hitobito_user = false;

	/**
	 * Allowed contexts
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	private $allowedContext = array(
		'com_users.profile',
		'com_users.user',
		'com_users.registration',
		'com_admin.profile',
	);

	/**
	 * Constructor.
	 *
	 * @param   object  &$subject  The object to observe -- event dispatcher.
	 * @param   object  $config    An optional associative array of configuration settings.
	 *
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config)
	{
		parent::__construct($subject, $config);
		if( !$this->params->get('clientid',false)
			||
			!$this->params->get('clientsecret',false)
			||
			!$this->params->get('redirecturi',false)
			||
			!$this->params->get('clienthost',false)
		  )
		{
			return false;
		}
	}

	/**
	 * Method to get the authentication from hitobito
	 *
	 * @return  -
	 *
	 * @since   1.0.0
	 */
	public function onAfterRoute()
	{
		if((Factory::getApplication()->input->get('task',null)=='oauth' && 
			Factory::getApplication()->input->get('app',null)=='hitobito') ||
			Factory::getApplication()->input->get('state',null)=='oauth' &&
			Factory::getApplication()->input->get('code',null) != null)
		{
			jimport('joomla.oauth2.client');

			$oauth_client = new JOAuth2Client();
			$oauth_client->setOption('sendheaders',true);
			$oauth_client->setOption('client_id','token');
			$oauth_client->setOption('scope',array('with_roles'));
			$oauth_client->setOption('requestparams',array('state'=>'oauth','task'=>Factory::getApplication()->input->get('task',null),'access_type'=>'offline'));
			$oauth_client->setOption('clientid',$this->params->get('clientid',false));
			$oauth_client->setOption('clientsecret',$this->params->get('clientsecret',false));
			$oauth_client->setOption('redirecturi',$this->params->get('redirecturi',false));
			$oauth_client->setOption('authurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/authorize');
			$oauth_client->setOption('tokenurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/token');
			$oauth_client->authenticate();
			$this->token = $oauth_client->getToken()['access_token'];
			$this->oauth_client = $oauth_client;
			if($oauth_client->isAuthenticated())
			{
				// Fetch authenticated user info
	            $opts = array(
				  'http'=>array(
				    'method'=>'GET',
				    'header'=>"Authorization: Bearer $this->token\r\n" .
				              "X-Scope: with_roles\r\n"
				  )
				);
				$context = stream_context_create($opts);
				$file = file_get_contents($this->params->get('clienthost','https://demo.hitobito.com').'/oauth/profile', false, $context);

				// safe user info to credentials
				$this->credentials = json_decode($file, true,);

				$response = new stdClass();
				$options  = array();
				$this->onUserAuthenticate($this->credentials,$options,$response);

				if ($this->params->get('implicitloginallowed', false) && $this->hitobito_user->id > 0 ||
					$this->params->get('registrationallowed', false) && $this->hitobito_user->id == 0)
				{
					// user exist -> Login.
					$options = array('action'=>'core.login.'.(Factory::getApplication()->isSite()?'site':'admin'));
					if($this->login($options, $response))
					{
						// if not redirected on onAfterLogin just go to front page //
						Factory::getApplication()->redirect(Route::_('index.php'));
					}
				}
				else
				{
					$this->hitobito_user->id = false;
					Factory::getApplication()->enqueueMessage('User from Hitobito does not exist in Joomla. Please register first.', 'error');
					Factory::getApplication()->redirect(Route::_('index.php'));
				}
			}
		}
	}

	/**
	 * Method to add the hitobito login button
	 *
	 * @return  -
	 *
	 * @since   1.0.0
	 */
	public function onBeforeRender()
	{
		if(Factory::getApplication()->isSite())
		{
			$doc = Factory::getApplication()->getDocument();

			// script for button click
			$script = 'let getOAuthToken = function(element){
				window.open("'. Uri::root().'?task=oauth&app=hitobito&state=unknown","_self");
                /*var winl = ( screen.width - 400 ) / 2;
                var wint = ( screen.height - 800 ) / 2;
                var winprops = "height=600,width=600,top=wint,left=winl,scrollbars=1,resizable";
                var myWindow = window.open("'. Uri::root().'?task=oauth&app=hitobito", "Hitobito OAuth2", winprops);*/
			};';
			$doc->addScriptDeclaration($script);

			// css button
			$css = '.btn-hitobito,.btn-hitobito:hover,.btn-hitobito:active,.btn-hitobito:focus {margin-left: 5px; background-color: '.$this->params->get('hitobito_bgcolor','#99bf62').'; color: '.$this->params->get('hitobito_color','#fff').'; background-image: linear-gradient(to bottom,'.$this->params->get('hitobito_bgcolor','#99bf62').','.$this->params->get('hitobito_bgcolor','#99bf62').');}';
			$doc->addStyleDeclaration($css);

			// html button
			$html = '<a id="hitobito_btn" class="btn btn-hitobito" href="#" onclick="getOAuthToken(this)">'.$this->params->get('hitobito_name','Hitobito').'</a>';
			$html = addcslashes($html,"'\"");

			// add button
			$script = 'jQuery(document).ready(function($){$(\'input[name="task"][value="user.login"], form[action*="task=user.login"] > :first-child\').closest(\'form\').find(\'input[type="submit"],button[type="submit"]\').after("'.$html.'");});';
			$doc->addScriptDeclaration($script);
		}
	}

	public function onUserAuthenticate($credentials, $options, &$response)
	{
		if(Factory::getApplication()->input->get('state',null)=='oauth' &&
			Factory::getApplication()->input->get('code',null) != null)
		{
			$this->hitobito_user = $this->getUserByHitobitoID($credentials['id']);

			jimport('joomla.authentication.authentication');
			jimport('joomla.user.authentication');
			$response->type = 'JOAuth';

			if( (Factory::getApplication()->input->get('state',null) != 'oauth') ||
				 $this->hitobito_user === false)
			{
				// authentification failed
				$response->status = JAuthentication::STATUS_FAILURE;
				return;
			}
			elseif(Factory::getApplication()->input->get('state',null) == 'oauth' &&
				    $this->hitobito_user === true)
			{
				// authentification successful, joomla user dont exist
				// hitobito_id was not found in #__users params row
				$params = '{"admin_style":"","admin_language":"","language":"","editor":"","timezone":"","hitobito_id":'.$credentials['id'].'}';

				$response->username = $credentials['email'];
				$response->email    = $credentials['email'];
				$response->fullname = $credentials['first_name'].' '.$credentials['last_name'];
				$response->params   = $params;

				$response->status        = JAuthentication::STATUS_SUCCESS;
				$response->error_message = '';
			}
			else
			{
				// authentification successful, joomla user exists
				// hitobito_id was found in #__users params row
				$response->username = $this->hitobito_user->username;
				$response->email    = $this->hitobito_user->email;
				$response->fullname = $this->hitobito_user->name;
				$response->params   = $this->hitobito_user->params;

				$response->status        = JAuthentication::STATUS_SUCCESS;
				$response->error_message = '';
			}
		}
	}

	/**
	 * Method is called after a form was instantiated
	 *
	 * @param   object  $form The form to be altered
	 * @param   array   $data The associated data for the form
	 * 
	 * @return  boolean True on success, false otherwise
	 * 
	 * @since   1.0.0
	 */
	public function onContentPrepareForm($form, $data)
	{
		if(!($form instanceof JForm))
	    {
	    	$this->_subject->setError(′JERROR_NOT_A_FORM′);
	    	return false;
	    }

	    // Check we are manipulating a valid form
	    $context = $form->getName();
		if (!in_array($context, $this->allowedContext))
		{
			return true;
		}

		JForm::addFormPath(__DIR__);
		$form->loadFile('user-form', false);

		return true;
	}


	/**
	 * Login authentication function for OAuth.
	 *
	 * Username and encoded password are passed the onUserLogin event which
	 * is responsible for the user validation. A successful validation updates
	 * the current session record with the user's details.
	 *
	 * Username and encoded password are sent as credentials (along with other
	 * possibilities) to each observer (authentication plugin) for user
	 * validation.  Successful validation will update the current session with
	 * the user details.
	 *
	 * @param   array    $options    Array('action' => core.login.site)
	 * @param   object   $response   Object with ??
	 */
	protected function login($options,$response)
	{
		//$response = new stdClass();
		//$this->onUserAuthenticate($this->hitobito_user,$options,$response);

		if($response->status == JAuthentication::STATUS_SUCCESS)
		{
			PluginHelper::importPlugin('user');
			// OK, the credentials are authenticated and user is authorised.  Let's fire the onLogin event.
			$app = Factory::getApplication();
			$response->password_clear = UserHelper::genRandomPassword();

			if($this->params->get('registrationallowed',true) && $this->hitobito_user === true)
			{
				$options['autoregister'] = true;
			}
			$results = $app->triggerEvent('onUserLogin', array((array) $response, $options));

			/*
			 * If any of the user plugins did not successfully complete the login routine
			 * then the whole method fails.
			 *
			 * Any errors raised should be done in the plugin as this provides the ability
			 * to provide much more information about why the routine may have failed.
			 */
			$user = Factory::getUser();

			if ($response->type == 'Cookie')
			{
				$user->set('cookieLogin', true);
			}

			if (in_array(false, $results, true) == false)
			{
				$options['user'] = $user;
				$options['responseType'] = $response->type;

				// The user is successfully logged in. Run the after login events
				Factory::getApplication()->triggerEvent('onUserAfterLogin', array($options));
			}

			Factory::getApplication()->enqueueMessage('Hitobito user "'.$response->fullname.'" successfully signed in.', 'message');

			return true;
		}

		return false;
	}

	/**
	 *  Search for Joomla user by hitobito-id.
	 * 
	 * @return  JUser object on success, true if no Joomla user found, false if no Hitobito user
	 * 
	 * @param   integer       $hitobito_id
	 */
	protected function getUserByHitobitoID($hitobito_id)
	{
		if(!isset($hitobito_id) || $hitobito_id < 0)
		{
			return false;
		}

		$db = Factory::getDbo();
		$query = $db->getQuery(true);

		$query->select($db->quoteName(array('id', 'params')));
		$query->from($db->quoteName('#__users'));
		$query->where($db->quoteName('params') . ' LIKE ' . $db->quote('%'.$hitobito_id.'%'));

		$db->setQuery($query);
		$users = $db->loadObjectList();

		$id = false;
		foreach($users as $user)
		{
			$params = json_decode($user->params);

			if( isset($params->hitobito_id) && intval($params->hitobito_id) === $hitobito_id)
			{
				$id = $user->id;
			}
		}

		if(!$id)
		{
			return true;
		}

		return Factory::getUser($id);
	}
}
