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
use \Joomla\CMS\User\User;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\User\UserHelper;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Cache\Cache;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Form\Form;
use \Joomla\Registry\Registry;
use \Joomla\CMS\Authentication\Authentication;
use \Joomla\CMS\Authentication\AuthenticationResponse as JAuthResponse; 

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
	 * User roles based on selected group
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	protected $roles = array();

	/**
	 * User credentials from hitobito
	 *
	 * @var    JUser object
	 * @since  1.0.0
	 */
	protected $hitobito_user = false;

	/**
	 * Error during login process
	 *
	 * @var    bool
	 * @since  1.0.0
	 */
	protected $error = false;

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
	 * @return  void
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
	 * Method to authenticate a user via OAuth and
	 * fetch the user info from hitobito
	 *
	 * @return  void
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

				// Safe user info to credentials
				$this->credentials = json_decode($file, true);

				// Get roles of user based on group
				$this->roles = $this->getRolesOfGroup($this->params->get('hitobito_groupid', 0));

				// Get hitobito id from current user
				$this->hitobito_user = $this->getUserByHitobitoID($this->credentials['id']);

				// prepare the response object
				$response = new JAuthResponse();
				$options  = array();
				$this->onUserAuthenticate($options, $response);

				if($this->params->get('registrationallowed', true) && $this->hitobito_user === true
					&& $response->status == Authentication::STATUS_SUCCESS)
				{
					// perform registration
					$this->registerUser($response);
				}

				if($this->hitobito_user instanceof User && $this->params->get('updateallowed', true)
					&& $response->status == Authentication::STATUS_SUCCESS)
				{
					// update the current joomla user based on hitobito data
					$this->updateUser();
				}

				$options = array('action' => 'core.login.'.(Factory::getApplication()->isSite()?'site':'admin'),
								'autoregister' => false);
				
				$this->login($options, $response);

				// if not redirected on onAfterLogin just go to front page //
				Factory::getApplication()->redirect(Route::_('index.php'));
			}
		}
	}

	/**
	 * Method to add the hitobito login button
	 *
	 * @return  void
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
			$css = '.btn-hitobito,.btn-hitobito:hover,.btn-hitobito:active,.btn-hitobito:focus {margin-left: 5px; background-color: '.$this->params->get('hitobito_bgcolor','#99bf62').'; color: '.$this->params->get('hitobito_color','#fff').'; background-image: linear-gradient(to bottom,'.$this->params->get('hitobito_bgcolor','#99bf62').','.$this->params->get('hitobito_bgcolor','#99bf62').'); text-shadow: initial;}';
			$doc->addStyleDeclaration($css);

			// html button
			$html = '<a id="hitobito_btn" class="btn btn-hitobito" href="#" onclick="getOAuthToken(this)">'.$this->params->get('hitobito_name','Hitobito').'</a>';
			$html = addcslashes($html,"'\"");

			// add button
			$script = 'jQuery(document).ready(function($){$(\'input[name="task"][value="user.login"], form[action*="task=user.login"] > :first-child\').closest(\'form\').find(\'input[type="submit"],button[type="submit"]\').after("'.$html.'");});';
			$doc->addScriptDeclaration($script);
		}
	}

	/**
	 * Add the hitobito id field to the user form
	 *
	 * @param   object  $form The form to be altered
	 * @param   array   $data The associated data for the form
	 * 
	 * @return  bool    True on success, false otherwise
	 */
	public function onContentPrepareForm($form, $data)
	{
		if(!($form instanceof Form))
	    {
	    	$this->_subject->setError(???JERROR_NOT_A_FORM???);
	    	return false;
	    }

	    // Check we are manipulating a valid form
	    $context = $form->getName();
		if (!in_array($context, $this->allowedContext))
		{
			return true;
		}

		Form::addFormPath(__DIR__.DIRECTORY_SEPARATOR.'forms');
		$form->loadFile('user-form', false);

		return true;
	}

	/**
	 * Method to append the required information to the $response object.
	 * 
	 * @param   array           $options       Options array
	 * @param   JAuthResponse   $response      Authentication response object
	 * 
	 * @return  bool            true on success, false otherwise
	 */
	public function onUserAuthenticate($options, &$response)
	{
		if(Factory::getApplication()->input->get('state',null)=='oauth' &&
			Factory::getApplication()->input->get('code',null) != null)
		{
			$response->type = 'JOAuth';

			if(Factory::getApplication()->input->get('state',null) != 'oauth' || $this->hitobito_user === false)
			{
				// authentication failed
				$response->status        = Authentication::STATUS_FAILURE;
				$response->error_message = Text::_('PLG_SYSTEM_HITOBITOAUTH_AUTH_USERNOTFOUND');

				return false;
			}
			elseif(Factory::getApplication()->input->get('state',null) == 'oauth' && $this->hitobito_user === true)
			{
				// authentification successful, joomla user dont exist
				// hitobito_id was not found in #__users params row
				$params = '{"admin_style":"","admin_language":"","language":"","editor":"","timezone":"","hitobito_id":'.$this->credentials['id'].'}';

				// create response
				$response->status        = Authentication::STATUS_SUCCESS;
				$response->username      = $this->credentials['email'];
				$response->email         = $this->credentials['email'];
				$response->fullname      = $this->credentials['first_name'].' '.$this->credentials['last_name'];
				$response->params        = $params;
				$response->error_message = '';

				return true;
			}
			else
			{
				// authentification successful, joomla user exists
				// hitobito_id was found in #__users params row

				// update username
				$this->hitobito_user->name = $this->credentials['first_name'].' '.$this->credentials['last_name'];

				// create response
				$response->status        = Authentication::STATUS_SUCCESS;
				$response->username      = $this->hitobito_user->username;
				$response->email         = $this->hitobito_user->email;
				$response->fullname      = $this->hitobito_user->name;
				$response->params        = $this->hitobito_user->params;
				$response->error_message = '';

				return true;
			}
		}
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
	 * @param   array           $options    Array('action' => core.login.site)
	 * @param   JAuthResponse   $response   Object with user info
	 * 
	 * @return  bool     True on success, false otherwise	 * 
	 */
	protected function login($options, $response)
	{
		PluginHelper::importPlugin('user');

		if($response->status == Authentication::STATUS_SUCCESS && !$this->error)
		{
			$app = Factory::getApplication();
			//$response->password_clear = UserHelper::genRandomPassword();

			// OK, the credentials are authenticated and user is authorised.  Let's fire the onLogin event.
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

			Factory::getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_AUTH_SUCCESS', $response->fullname), 'message');

			return true;
		}

		// Trigger onUserLoginFailure Event.
		Factory::getApplication()->triggerEvent('onUserLoginFailure', array((array) $response));

		// If silent is set, just return false.
		if (isset($options['silent']) && $options['silent'])
		{
			return false;
		}

		// If status is success, any error will have been raised by the user plugin
		if ($response->status !== Authentication::STATUS_SUCCESS)
		{
			Factory::getApplication()->enqueueMessage($response->error_message, 'warning');
		}

		return false;
	}

	/**
	 *  Search for Joomla user by hitobito-id.
	 * 
	 * @param   integer      $hitobito_id   User id fetched from OAuth response
	 * 
	 * @return  JUser|bool   object on success, true if no Joomla user found, false if no Hitobito user	 * 
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

	/**
	 *  Method to create a new CMS user based on hitobito data
	 * 
	 * @param   JAuthResponse   $response   Authentication response object
	 * 
	 * @return  void
	 */
	protected function registerUser($response)
	{
		// user object
		$instance = User::getInstance();
		
		// Get usergroups based on hitobito roles
		$usergroups = $this->getUsergroups();		

		// fill user object
		$instance->id             = 0;
		$instance->name           = $response->fullname;
		$instance->username       = $response->username;
		$instance->password_clear = UserHelper::genRandomPassword();
		$instance->email          = $response->email;
		$instance->groups         = $usergroups;
		$instance->params         = $instance->setParam('hitobito_id', $this->credentials['id']);

		// save user
		if (!$instance->save())
		{
			Factory::getApplication()->enqueueMessage('Error in autoregistration for user: ' . $response->username, 'error');
			JLog::add('Error in autoregistration for user: ' . $response->username . '.', JLog::WARNING, 'error');
		}
	}

	/**
	 *  Method to update a CMS user based on hitobito data
	 * 
	 * @return  void
	 */
	protected function updateUser()
	{
		// update user object
		$this->hitobito_user->name   = $this->credentials['first_name'].' '.$this->credentials['last_name'];
		$this->hitobito_user->groups = $this->getUsergroups();
		$this->hitobito_user->setParam('hitobito_id', $this->credentials['id']);

		// save user
		if (!$this->hitobito_user->save(true))
		{
			Factory::getApplication()->enqueueMessage('Error in updating user data: ' . $this->hitobito_user->username, 'error');
			JLog::add('Error in updating user data: ' . $this->hitobito_user->username . '.', JLog::WARNING, 'error');
		}
	}

	/**
	 *  Get roles of this user based on a Hitobito group.
	 * 
	 * @param   integer   $group_id   ID of the Hitobito group id to be used
	 * 
	 * @return  array     Array with available roles of this user	 * 
	 */
	protected function getRolesOfGroup($group_id)
	{
		$group_roles = array();

		foreach ($this->credentials['roles'] as $key => $role)
		{
			if($role['group_id'] == $group_id)
			{
				array_push($group_roles, $role['role_name']);
			}
		}

		return $group_roles;
	}

	/**
	 *  Get CMS usergroups based on Hitobito roles
	 * 
	 * @return  array     Array with associated usergroups	 * 
	 */
	protected function getUsergroups()
	{
		if($this->params->get('groupmapping', false) == false || empty($this->params->get('groupmapping', false)))
		{
			// Use default usergroup
			$usergroups = array(intval($this->params->get('cms_group_default', 0)));
		}
		else
		{
			// Perform mapping
			$usergroups = array();
			foreach ($this->params->get('groupmapping', false) as $key => $map)
			{
				if(in_array($map->hitobito_group, $this->roles))
				{
					if($this->checkSU($map->cms_group))
					{
						// try to map super user group
						Factory::getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_HITOBITOAUTH_SU_ERROR'), 'error');
						$this->error = true;
					}
					else
					{
						array_push($usergroups, $map->cms_group);
					}
				}
			}

			if(count($usergroups) == 0)
			{
				// Use default usergroup if no matches in mapping
				$usergroups = array(intval($this->params->get('cms_group_default', 0)));
			}
		}

		return $usergroups;
	}

	/**
	 *  Checks if usergroup is super user
	 * 
	 * @param   integer   $group_id   ID of the user group to be checked
	 * 
	 * @return  bool   True if it is super admin, false otherwise
	 */
	protected function checkSU($group_id)
	{
		return Access::checkGroup($group_id, 'core.admin');
	}
}
