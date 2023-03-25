<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Schlumpf\Plugin\System\Hitobitoauth\Extension;

\defined('_JEXEC') or die;

use \Joomla\Event\DispatcherInterface;
use \Joomla\Event\SubscriberInterface;
use \Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;

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
use \Joomla\CMS\Filesystem\Path;
use \Joomla\CMS\Form\Form;
use \Joomla\Registry\Registry;
use \Joomla\CMS\Authentication\Authentication;
use \Joomla\CMS\Authentication\AuthenticationResponse as JAuthResponse; 

/**
 * Hitobito OAuth2 Login plugin
 *  
 * Plugin to login into Joomla CMS with OAuth2 provided by Hitobito
 *
 * @since  1.0.0
 */
class Hitobitoauth extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load plugin language files automatically
	 *
	 * @var    boolean
	 * @since  1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
     * Should I try to detect and register legacy event listeners?
     *
     * @var    boolean
     * @since  1.0.0
     *
     * @deprecated
     */
    protected $allowLegacyListeners = false;

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
	 * @param   DispatcherInterface   $subject   The object to observe -- event dispatcher.
	 * @param   array                 $config    An optional associative array of configuration settings.
	 *
	 * @return  void
	 */
	public function __construct(&$subject, array $config = [])
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
     * Returns an array of events this subscriber will listen to.
     *
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        try {
            $app = Factory::getApplication();
        } catch (\Exception $e) {
            return [];
        }

        if (!$app->isClient('site') && !$app->isClient('administrator')) {
            return [];
        }

        return [
			'onAfterRoute'			=> 'onAfterRoute',
            'onBeforeRender'		=> 'onBeforeRender',
            'onContentPrepareForm'	=> 'onContentPrepareForm',
            'onUserAuthenticate'    => 'onUserAuthenticate',
        ];
    }

	/**
	 * Method to authenticate a user via OAuth and
	 * fetch the user info from hitobito
	 *
	 * @return  void
	 */
	public function onAfterRoute()
	{
		$state = Factory::getApplication()->getUserState('hitobitauth.state', null);

 		if(Factory::getApplication()->getUserState('hitobitauth.state', null) === true &&
		   Factory::getApplication()->getUserState('hitobitauth.client', null) == 'site' &&
		   Factory::getApplication()->input->get('oauth',null) == 'success')
		{
			// Successful authetication in frontend
			$script  = 'if (window.opener != null && !window.opener.closed) {';
			$script .=     'window.opener.location.reload();';
			$script .= '}';
			$script .= 'window.close();';

			Factory::getApplication()->setUserState('hitobitauth.state', false);
			
			echo '<script>'.$script.'</script>';

			return;
		}

		if(Factory::getApplication()->getUserState('hitobitauth.state', null) === true &&
		   Factory::getApplication()->getUserState('hitobitauth.client', null) == 'administrator' &&
		   Factory::getApplication()->input->get('oauth',null) == 'success')
		{
			// Successful authetication in backend
			echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CHECK_CREDITS_SUCCESS');

			Factory::getApplication()->setUserState('hitobitauth.state', false);
			die;
		}

		if((Factory::getApplication()->input->get('task',null)=='oauth' && 
		Factory::getApplication()->input->get('app',null)=='hitobito') ||
		Factory::getApplication()->input->get('state',null)=='oauth' &&
		Factory::getApplication()->input->get('code',null) != null)
		{
			// Start OAuth authetication process
			jimport('joomla.oauth2.client');
			Factory::getApplication()->setUserState('hitobitauth.state', true);

			if(Factory::getApplication()->input->get('from',null) !== null)
			{
				Factory::getApplication()->setUserState('hitobitauth.client', Factory::getApplication()->input->get('from',null));
			}

			JLoader::register('JOAuth2ClientCustom', JPATH_PLUGINS.'/system/hitobitoauth/src/client.php');

			$oauth_client = new JOAuth2ClientCustom();
			$oauth_client->setOption('sendheaders',true);
			$oauth_client->setOption('client_id','token');
			$oauth_client->setOption('scope',array('with_roles'));
			$oauth_client->setOption('requestparams',array('state'=>'oauth','task'=>Factory::getApplication()->input->get('task',null),'access_type'=>'offline'));
			$oauth_client->setOption('clientid',$this->params->get('clientid',false));
			$oauth_client->setOption('clientsecret',$this->params->get('clientsecret',false));
			$oauth_client->setOption('redirecturi',JUri::root());
			$oauth_client->setOption('authurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/authorize');
			$oauth_client->setOption('tokenurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/token');
			$oauth_client->authenticate();
			$this->token = $oauth_client->getToken()['access_token'];
			$this->oauth_client = $oauth_client;

			if($oauth_client->isAuthenticated())
			{
				if(Factory::getApplication()->getUserState('hitobitauth.client', null) == 'administrator')
				{
					// Checking credetials in administrator
					Factory::getApplication()->redirect(Route::_('index.php?oauth=success'));
					return;
				}

				// Fetch authenticated user info
				$opts = array(
				'http'=>array(
					'method'=>'GET',
					'header'=>"Authorization: Bearer $this->token\r\n" .
							"X-Scope: with_roles\r\n" .
							"Accept-Language: de\r\n"
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

				// Authenticate hitobito user
				$response = new JAuthResponse();
				$options  = array();
				$this->onUserAuthenticate($options, $response);

				if($this->params->get('registrationallowed', true) && $this->hitobito_user === true
					&& $response->status === Authentication::STATUS_SUCCESS)
				{
					// perform registration
					$this->registerUser($response);
				}

				if($this->hitobito_user instanceof User && $this->params->get('updateallowed', true)
					&& $response->status === Authentication::STATUS_SUCCESS)
				{
					// update the current joomla user based on hitobito data
					$this->updateUser();
				}

				$options = array('action' => 'core.login.'.(Factory::getApplication()->isSite()?'site':'admin'),
								'autoregister' => false);
				
				$this->login($options, $response);

				// if not redirected on onAfterLogin just go to front page //
				Factory::getApplication()->redirect(Route::_('index.php?oauth=success'));
			}
		}

		if(Factory::getApplication()->getUserState('hitobitauth.msg', null))
		{
			// Output messages from user state if available
			$msg     = Factory::getApplication()->getUserState('hitobitauth.msg', null);
			$msgType = Factory::getApplication()->getUserState('hitobitauth.msgType', 'message');
			Factory::getApplication()->enqueueMessage($msg, $msgType);

			if(Factory::getApplication()->getUserState('hitobitauth.state', null) === false)
			{
				// End OAuth authetication process
				$this->resetUserSate();
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
		if(Factory::getApplication()->isClient('site'))
		{
			$doc = Factory::getApplication()->getDocument();

			// script for button click
			$script   = '';
			$path = Path::clean(JPATH_PLUGINS.'/system/hitobitoauth/layouts/oauth.js.php');

			ob_start();
			include $path;
			$script .= ob_get_contents();
			ob_end_clean();
			$doc->addScriptDeclaration($script);

			// css button
			$css = '.btn-hitobito,.btn-hitobito:hover,.btn-hitobito:active,.btn-hitobito:focus {margin-bottom: 20px; background-color: '.$this->params->get('hitobito_bgcolor','#99bf62').'; color: '.$this->params->get('hitobito_color','#fff').'; background-image: linear-gradient(to bottom,'.$this->params->get('hitobito_bgcolor','#99bf62').','.$this->params->get('hitobito_bgcolor','#99bf62').'); text-shadow: initial;}';
			$doc->addStyleDeclaration($css);

			// logo
			$default = Uri::root().'plugins/system/hitobitoauth/images/hitobito_logo.png';
			$logo = '<span><img src="'.$this->params->get('hitobito_logo', $default).'" alt="Hitobito Logo" width="20" height="15"></span> ';

			// html button
			$html = '<hr /><a id="hitobito_btn" class="btn btn-hitobito w-100" href="#" onclick="getOAuthToken(this, \'site\')">'.$logo.Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_LOGIN_WITH', $this->params->get('hitobito_name','Hitobito')).'</a>';
			$html = addcslashes($html,"'\"");

			// add button
			$script = 'jQuery(document).ready(function($){$(\'input[name="task"][value="user.login"], form[action*="task=user.login"] > :first-child\').closest(\'form\').find(\'input[type="submit"],button[type="submit"]\').after("'.$html.'");});';
			$doc->addScriptDeclaration($script);
		}
	}

	/**
     * Adds the hitobito id field to the user editing form
     *
     * @param   Event  $event  The event we are handling
     *
     * @return  void
     *
     * @throws  Exception
     * @since   4.0.0
     */
    public function onContentPrepareForm(Event $event): void
	{
		/**
         * @var   Form  $form The form to be altered.
         * @var   mixed $data The associated data for the form.
         */
        [$form, $data] = $event->getArguments();

	    // Check we are manipulating a valid form
	    $context = $form->getName();
		if (!in_array($context, $this->allowedContext))
		{
			return;
		}

		// This feature only applies in the site and administrator applications
        if (
            !$this->getApplication()->isClient('site')
            && !$this->getApplication()->isClient('administrator')
        ) {
            return;
        }

		Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
		$form->loadFile('user-form', false);

		return;
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

			if(Factory::getApplication()->input->get('state',null) != 'oauth' || $this->hitobito_user === false
				|| ($this->params->get('grouprestriction', false) && !in_array($this->params->get('hitobito_groupid', 0), $this->groups)))
			{
				// authentication failed
				$response->status        = Authentication::STATUS_FAILURE;

				if($this->hitobito_user === false)
				{
					// user not found in CMS
					$response->error_message = Text::_('PLG_SYSTEM_HITOBITOAUTH_AUTH_USERNOTFOUND');
				}
				else
				{
					// other failure
					$response->error_message = Text::_('PLG_SYSTEM_HITOBITOAUTH_AUTH_ERROR_DEFAULT');
				}

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

		if($response->status === Authentication::STATUS_SUCCESS && !$this->error)
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
				// Login successful
				$options['user'] = $user;
				$options['responseType'] = $response->type;

				// The user is successfully logged in. Run the after login events
				Factory::getApplication()->triggerEvent('onUserAfterLogin', array($options));
			}
			else
			{
				// Login failed
				Factory::getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname), 'error');
				Factory::getApplication()->setUserState('hitobitauth.msg', Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname));
				Factory::getApplication()->setUserState('hitobitauth.msgType', 'error');

				return false;
			}

			Factory::getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_AUTH_SUCCESS', $response->fullname), 'message');
			Factory::getApplication()->setUserState('hitobitauth.msg', Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_AUTH_SUCCESS', $response->fullname));
			Factory::getApplication()->setUserState('hitobitauth.msgType', 'message');

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
			Factory::getApplication()->enqueueMessage($response->error_message, 'error');
			Factory::getApplication()->setUserState('hitobitauth.msg', $response->error_message);
			Factory::getApplication()->setUserState('hitobitauth.msgType', 'error');
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
			Factory::getApplication()->setUserState('hitobitauth.msg', 'Error in autoregistration for user: ' . $response->username);
			Factory::getApplication()->setUserState('hitobitauth.msgType', 'error');
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
			Factory::getApplication()->setUserState('hitobitauth.msg', 'Error in updating user data: ' . $this->hitobito_user->username);
			Factory::getApplication()->setUserState('hitobitauth.msgType', 'error');
		}
	}

	/**
	 *  Get roles of this user based on a Hitobito group.
	 * 
	 * @param   integer   $group_id   ID of the Hitobito group id to be used
	 * 
	 * @return  array     Array with available roles of this user
	 */
	protected function getRolesOfGroup($group_id)
	{
		$group_roles = array();

		foreach ($this->credentials['roles'] as $key => $role)
		{
			if($role['group_id'] == $group_id)
			{
				//array_push($group_roles, $role['name']);
				array_push($group_roles, $role['role_class']);
			}
		}

		return $group_roles;
	}

	/**
	 *  Get CMS usergroups based on Hitobito roles
	 * 
	 * @return  array     Array with associated usergroups
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
						Factory::getApplication()->setUserState('hitobitauth.msg', Text::_('PLG_SYSTEM_HITOBITOAUTH_SU_ERROR'));
						Factory::getApplication()->setUserState('hitobitauth.msgType', 'error');
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

	/**
	 *  Resets all user states
	 * 
	 * @return  void
	 */
	protected function resetUserSate()
	{
		Factory::getApplication()->setUserState('hitobitauth.client', null);
		Factory::getApplication()->setUserState('hitobitauth.msg', null);
		Factory::getApplication()->setUserState('hitobitauth.msgType', null);
	}
}
