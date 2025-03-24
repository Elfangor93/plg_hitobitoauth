<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Manuel Häusler (Schlumpf)
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Schlumpf\Plugin\System\Hitobitoauth\Extension;

\defined('_JEXEC') or die;

// Plugin events
use \Joomla\CMS\Event\CoreEventAware;
use \Joomla\Event\DispatcherInterface;
use \Joomla\Event\SubscriberInterface;
use \Joomla\Event\Event;
use \Joomla\Plugin\System\Webauthn\PluginTraits\EventReturnAware;

// Other sources
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\Plugin\CMSPlugin;
use \Joomla\Database\DatabaseInterface;
use \Joomla\Http\HttpFactory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\User\User;
use \Joomla\CMS\User\UserFactoryInterface;
use \Joomla\CMS\User\UserHelper;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\Log\Log;
use \Joomla\CMS\Authentication\Authentication;
use \Joomla\CMS\Authentication\AuthenticationResponse as AuthResponse;
use \Schlumpf\Plugin\System\Hitobitoauth\Oauth\Customclient as OAuth2ClientCustom;

/**
 * Hitobito OAuth2 Login plugin
 *  
 * Plugin to login into Joomla CMS with OAuth2 provided by Hitobito
 *
 * @since  1.0.0
 */
class Hitobitoauth extends CMSPlugin implements SubscriberInterface
{
  // Utility traits
  use CoreEventAware;
  use EventReturnAware;

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
	protected $credentials = [];

	/**
	 * User roles based on selected group
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	protected $roles = [];

	/**
	 * User credentials from hitobito
	 *
	 * @var    User object
	 * @since  1.0.0
	 */
	protected $hitobito_user = false;

	/**
	 * State of login
	 *
	 * @var    bool
	 * @since  2.0.0
	 */
	protected $login_success = false;

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
	private $allowedContext = [
		'com_users.profile',
		'com_users.user',
		'com_users.registration',
		'com_admin.profile',
	];

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
			}
			catch (\Exception $e)
			{
				return [];
			}

			if (!$app->isClient('site') && !$app->isClient('administrator'))
			{
				return [];
			}

			return [
					'onAfterRoute'			    => 'onAfterRoute',
					'onContentPrepareForm'	=> 'onContentPrepareForm',
					'onUserLoginButtons'    => 'onUserLoginButtons',
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
		// This feature only applies in the site and administrator applications
    if( !$this->getApplication()->isClient('site') &&
			  !$this->getApplication()->isClient('administrator')
      )
		{
      return;
    }

    // Successful authentication in frontend
 		if($this->getApplication()->getUserState('hitobitauth.state', null) === true &&
		   $this->getApplication()->getUserState('hitobitauth.client', null) == 'site' &&
		   $this->getApplication()->input->get('oauth',null) == 'success')
		{
      $script  = 'localStorage.setItem("hitobito_oauth_refresh", "true");';
			$script .= 'window.close();';

      // Reset the user state
			$this->getApplication()->setUserState('hitobitauth.state', false);
			
			echo '<script>'.$script.'</script>';

			return;
		}

    // Successful authetication in backend
		if($this->getApplication()->getUserState('hitobitauth.state', null) === true &&
		   $this->getApplication()->getUserState('hitobitauth.client', null) == 'administrator' &&
		   $this->getApplication()->input->get('oauth',null) == 'success')
		{
			echo Text::_('PLG_SYSTEM_HITOBITOAUTH_CHECK_CREDITS_SUCCESS');

			$this->getApplication()->setUserState('hitobitauth.state', false);
			die;
		}

    // Start OAuth authetication process
		if(($this->getApplication()->input->get('task',null)=='oauth' && 
			$this->getApplication()->input->get('app',null)=='hitobito') ||
			$this->getApplication()->input->get('state',null)=='oauth' &&
			$this->getApplication()->input->get('code',null) != null)
		{
			$input = $this->getApplication()->getInput();
			$this->getApplication()->setUserState('hitobitauth.state', true);

			if($input->get('from',null) !== null)
			{
        // Set client to state
				$this->getApplication()->setUserState('hitobitauth.client', $input->get('from',null));
			}

			$http  = (new HttpFactory())->getHttp([]);

			$oauth_client = new OAuth2ClientCustom([], $http, $input, $this->getApplication());
			$oauth_client->setOption('sendheaders',true);
			$oauth_client->setOption('client_id','token');
			$oauth_client->setOption('scope',['with_roles']);
			$oauth_client->setOption('requestparams',['state'=>'oauth','task'=>$input->get('task',null),'access_type'=>'offline']);
			$oauth_client->setOption('clientid',$this->params->get('clientid',false));
			$oauth_client->setOption('clientsecret',$this->params->get('clientsecret',false));
			$oauth_client->setOption('redirecturi',Uri::root());
			$oauth_client->setOption('authurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/authorize');
			$oauth_client->setOption('tokenurl',$this->params->get('clienthost','https://demo.hitobito.com').'/oauth/token');
			$oauth_client->authenticate();
			$this->token = $oauth_client->getToken()['access_token'];
			$this->oauth_client = $oauth_client;

			if($oauth_client->isAuthenticated())
			{
				if($this->getApplication()->getUserState('hitobitauth.client', null) == 'administrator')
				{
					// Checking credetials in administrator
					$this->getApplication()->redirect(Route::_('index.php?oauth=success'));
					return;
				}

				// Fetch authenticated user info
				$opts = [
					'http'=>[
						'method'=>'GET',
						'header'=>"Authorization: Bearer $this->token\r\n" .
											"X-Scope: with_roles\r\n" .
											"Accept-Language: de\r\n"
					]
				];

				$context = \stream_context_create($opts);
				$file    = \file_get_contents($this->params->get('clienthost','https://demo.hitobito.com').'/oauth/profile', false, $context);

				// Safe user info to credentials
				$this->credentials = \json_decode($file, true);

				// Get roles of user based on group
				$this->roles = $this->getRolesOfGroup($this->params->get('hitobito_groupid', 0));

				// Get hitobito id from current user
				$this->hitobito_user = $this->getUserByHitobitoID($this->credentials['id']);

				// Authenticate hitobito user
				$response = new AuthResponse();
				$options  = [];
				$this->onUserAuthenticate($options, $response);

				if($response->status === Authentication::STATUS_SUCCESS && $this->isAllowedUsergroup())
				{
					// Authentication successful
					if($this->hitobito_user === true && $this->params->get('registrationallowed', true))
					{
						// Perform registration
						$this->registerUser($response);
					}
					elseif($this->hitobito_user instanceof User && $this->params->get('updateallowed', true))
					{
						// Update the current CMS user based on hitobito data
						$this->updateUser();
					}
				}

				if( $response->status === Authentication::STATUS_SUCCESS && 
				    $this->hitobito_user instanceof User
				  )
				{
				  // Perform the login to the CMS
				  $options = ['action' => 'core.login.'.($this->getApplication()->isClient('site') ? 'site' : 'admin'), 'autoregister' => false];
				  $this->login($options, $response);

				  // If not redirected on onAfterLogin just go to front page
				  $this->getApplication()->redirect(Route::_('index.php?oauth=success'));
				}

				if(!$this->login_success)
				{
				  // Login failed
				  $this->getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname), 'error');
				  $this->getApplication()->setUserState('hitobitauth.msg', Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname));
				  $this->getApplication()->setUserState('hitobitauth.msgType', 'error');
				  $this->getApplication()->redirect(Route::_('index.php?oauth=success'));
				}
			}
		}

    // Output messages from user state if available
		if($this->getApplication()->getUserState('hitobitauth.msg', null))
		{			
			$msg     = $this->getApplication()->getUserState('hitobitauth.msg', null);
			$msgType = $this->getApplication()->getUserState('hitobitauth.msgType', 'message');
			$this->getApplication()->enqueueMessage($msg, $msgType);

			if($this->getApplication()->getUserState('hitobitauth.state', null) === false)
			{
				// End OAuth authetication process
				$this->resetUserSate();
			}
		}
	}

  /**
   * Creates the additional hitobito login button
   *
   * @param   Event  $event  The event we are handling
   *
   * @return  void
   *
   * @see     AuthenticationHelper::getLoginButtons()
   *
   * @since   4.0.0
   */
  public function onUserLoginButtons(Event $event): void
  {
    if($this->getApplication()->isClient('site'))
		{
      /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
      $wa = $this->getApplication()->getDocument()->getWebAssetManager();

      // Add js and css
      $css = ':root { --hitobito_bgcolor: ' . $this->params->get('hitobito_bgcolor','#99bf62') . '; --hitobito_color: ' . $this->params->get('hitobito_color','#fff') . ';}';
      $wa->addInlineStyle($css, ['position' => 'before'], [], ['hitobitoauth.style']);
      $wa->registerAndUseStyle('hitobitoauth.style', 'plg_system_hitobitoauth/hitobitoauth.css');
      $wa->registerAndUseScript('hitobitoauth.script', 'plg_system_hitobitoauth/hitobitoauth.js');

      // Unique ID for this button (allows display of multiple modules on the page)
      $randomId = 'hitobito_btn-' . UserHelper::genRandomPassword(5);

      // Get button icon
      $default_img = Uri::root().'media/plg_system_hitobitoauth/images/hitobito_logo.png';
      $img_html    = '<span class="icon"><img src="'.$this->params->get('hitobito_logo', $default_img).'" alt="Hitobito Logo" width="20" height="15"></span>';

      $this->returnFromEvent($event, [
        [
          'label'              => Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_LOGIN_WITH', $this->params->get('hitobito_name','Hitobito')),
          'id'                 => $randomId,
          'image'              => $img_html,
          'class'              => 'btn btn-hitobito w-100',
          'onclick'            => 'getOAuthToken(event, \''. Uri::root() .'\', \'site\')',
        ],
      ]);
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
     */
    extract($event->getArguments());
    $form = $event->getForm();

    // Check we are manipulating a valid form
    if(!($form instanceof Form))
    {
      $this->setError($event, 'JERROR_NOT_A_FORM');
      $this->setResult($event, true);

      return;
    }

	  // Modify only forms that have the correct context
	  $context = $form->getName();
		if (!\in_array($context, $this->allowedContext))
		{
      $this->setResult($event, true);

      return;
		}

		// This feature only applies in the site and administrator applications
    if( !$this->getApplication()->isClient('site') &&
			  !$this->getApplication()->isClient('administrator')
      )
		{
      $this->setResult($event, true);

      return;
    }

		Form::addFormPath(JPATH_PLUGINS . '/' . $this->_type . '/' . $this->_name . '/forms');
		$form->loadFile('user-form', false);

    $this->setResult($event, true);

		return;
	}

	/**
	 * Method to append the required information to the $response object.
	 * 
	 * @param   array           $options       Options array
	 * @param   AuthResponse    $response      Authentication response object
	 * 
	 * @return  bool            true on success, false otherwise
	 */
	public function onUserAuthenticate($options, &$response)
	{
		if($this->getApplication()->input->get('state',null)=='oauth' &&
			$this->getApplication()->input->get('code',null) != null)
		{
			$response->type = 'JOAuth';

			if($this->getApplication()->input->get('state',null) != 'oauth' || $this->hitobito_user === false
				|| ($this->params->get('grouprestriction', false) && !\in_array($this->params->get('hitobito_groupid', 0), $this->groups)))
			{
				// authentication failed
				$response->status = Authentication::STATUS_FAILURE;

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
			elseif($this->getApplication()->input->get('state',null) == 'oauth' && $this->hitobito_user === true)
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
				$response->language      = '';
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

				if ($this->getApplication()->isClient('administrator'))
				{
				  $response->language = $this->hitobito_user->getParam('admin_language');
				}
				else
				{
				  $response->language = $this->hitobito_user->getParam('language');
				}

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
	 * @param   AuthResponse    $response   Object with user info
	 * 
	 * @return  bool     True on success, false otherwise	 * 
	 */
	protected function login($options, $response)
	{
		PluginHelper::importPlugin('user');

		if($response->status === Authentication::STATUS_SUCCESS && !$this->error)
		{
			// OK, the credentials are authenticated and user is authorised from Oauth endpoint.
      // Let's fire the onLogin event.
      $class   = self::getEventClassByEventName('onUserLogin');
      $event   = new $class('onUserLogin', [(array) $response, $options]);
      $this->getApplication()->getDispatcher()->dispatch($event->getName(), $event);
      $results = $event->getArgument('result', []);

			/*
			 * If any of the user plugins did not successfully complete the login routine
			 * then the whole method fails.
			 *
			 * Any errors raised should be done in the plugin as this provides the ability
			 * to provide much more information about why the routine may have failed.
			 */
			$user = $this->hitobito_user;

			if ($response->type == 'Cookie')
			{
				$user->set('cookieLogin', true);
			}

			// If there is no boolean FALSE result from any plugin the login is successful.
			if (\in_array(false, $results, true) === false)
			{
				// Login successful
				// Set the user in the session, letting Joomla! know that we are logged in.
				$this->getApplication()->getSession()->set('user', $user);
				$this->login_success = true;

				// Trigger the onUserAfterLogin event
				$options['user'] = $user;
				$options['responseType'] = $response->type;

				// The user is successfully logged in. Run the after login events
        $class = self::getEventClassByEventName('onUserAfterLogin');
        $event = new $class('onUserAfterLogin', [$options, []]);
        $this->getApplication()->getDispatcher()->dispatch($event->getName(), $event);

				$this->getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_AUTH_SUCCESS', $response->fullname), 'message');
				$this->getApplication()->setUserState('hitobitauth.msg', Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_AUTH_SUCCESS', $response->fullname));
				$this->getApplication()->setUserState('hitobitauth.msgType', 'message');

				return;
			}
			else
			{
				// Login failed
				$this->getApplication()->enqueueMessage(Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname), 'error');
				$this->getApplication()->setUserState('hitobitauth.msg', Text::sprintf('PLG_SYSTEM_HITOBITOAUTH_USERLOGIN_FAILED', $response->fullname));
				$this->getApplication()->setUserState('hitobitauth.msgType', 'error');
			}
		}
    else
    {
      // Authentication failed
			$this->getApplication()->enqueueMessage($response->error_message, 'error');
			$this->getApplication()->setUserState('hitobitauth.msg', $response->error_message);
			$this->getApplication()->setUserState('hitobitauth.msgType', 'error');

      // Log the failure
      Log::add($response->error_message, Log::WARNING, 'jerror');
    }    

		// If we are here the plugins marked a login failure. Trigger the onUserLoginFailure Event.
    $class = self::getEventClassByEventName('onUserLoginFailure');
    $event = new $class('onUserLoginFailure', [(array) $response, []]);
    $this->getApplication()->getDispatcher()->dispatch($event->getName(), $event);

    // Throw an exception to let the caller know that the login failed
    throw new \RuntimeException($response->error_message);
	}

	/**
	 *  Search for Joomla user by hitobito-id.
	 * 
	 * @param   integer      $hitobito_id   User id fetched from OAuth response
	 * 
	 * @return  User|bool   object on success, true if no Joomla user found, false if no Hitobito user	 * 
	 */
	protected function getUserByHitobitoID($hitobito_id)
	{
		if(!isset($hitobito_id) || $hitobito_id < 0)
		{
			return false;
		}

		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true);

		$query->select($db->quoteName(['id', 'params']));
		$query->from($db->quoteName('#__users'));
		$query->where($db->quoteName('params') . ' LIKE ' . $db->quote('%'.$hitobito_id.'%'));

		$db->setQuery($query);
		$users = $db->loadObjectList();

		$id = false;
		foreach($users as $user)
		{
			$params = \json_decode($user->params);

			if(isset($params->hitobito_id) && \intval($params->hitobito_id) === $hitobito_id)
			{
				$id = $user->id;
			}
		}

		if(!$id)
		{
			return true;
		}

		return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
	}

	/**
	 *  Method to create a new CMS user based on hitobito data
	 * 
	 * @param   AuthResponse   $response   Authentication response object
	 * 
	 * @return  void
	 */
	protected function registerUser($response)
	{
		// user object
		$instance = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById(0);
		
		// Get usergroups based on hitobito roles
		$usergroups = $this->getUsergroups();

		// fill user object
		$instance->id             = 0;
		$instance->name           = $response->fullname;
		$instance->username       = $response->username;
		$instance->password_clear = UserHelper::genRandomPassword();
		$instance->email          = $response->email;
		$instance->groups         = $usergroups;
		$instance->authProvider   = 'hitobito';

		$instance->setParam('hitobito_id', $this->credentials['id']);
		$instance->setParam('admin_language', '');
		$instance->setParam('language', '');

		// save user
		if(!$instance->save())
		{
			$this->getApplication()->enqueueMessage('Error in auto registration for user: ' . $response->username, 'error');
			Log::add('Error in autoregistration for user: ' . $response->username . '.', Log::WARNING, 'error');
			$this->getApplication()->setUserState('hitobitauth.msg', 'Error in auto registration for user: ' . $response->username);
			$this->getApplication()->setUserState('hitobitauth.msgType', 'error');
		}
		else
		{
			$this->hitobito_user = $instance;
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
		if(!$this->hitobito_user->save(true))
		{
			$this->getApplication()->enqueueMessage('Error in updating user data: ' . $this->hitobito_user->username, 'error');
			Log::add('Error in updating user data: ' . $this->hitobito_user->username . '.', Log::WARNING, 'error');
			$this->getApplication()->setUserState('hitobitauth.msg', 'Error in updating user data: ' . $this->hitobito_user->username);
			$this->getApplication()->setUserState('hitobitauth.msgType', 'error');
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
		$group_roles = [];

		foreach($this->credentials['roles'] as $key => $role)
		{
			if($role['group_id'] == $group_id)
			{
				//array_push($group_roles, $role['name']);
				\array_push($group_roles, $role['role_class']);
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
			$usergroups = [\intval($this->params->get('cms_group_default', 0))];
		}
		else
		{
			// Perform mapping
			$usergroups = [];
			foreach($this->params->get('groupmapping', false) as $key => $map)
			{
				if(\in_array($map->hitobito_group, $this->roles))
				{
					if($this->checkSU($map->cms_group))
					{
						// try to map super user group
						$this->getApplication()->enqueueMessage(Text::_('PLG_SYSTEM_HITOBITOAUTH_SU_ERROR'), 'error');
						$this->getApplication()->setUserState('hitobitauth.msg', Text::_('PLG_SYSTEM_HITOBITOAUTH_SU_ERROR'));
						$this->getApplication()->setUserState('hitobitauth.msgType', 'error');
						$this->error = true;
					}
					else
					{
						\array_push($usergroups, $map->cms_group);
					}
				}
			}

			if(\count($usergroups) == 0)
			{
				// Use default usergroup if no matches in mapping
				$usergroups = [\intval($this->params->get('cms_group_default', 0))];
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
		$this->getApplication()->setUserState('hitobitauth.client', null);
		$this->getApplication()->setUserState('hitobitauth.msg', null);
		$this->getApplication()->setUserState('hitobitauth.msgType', null);
    //$this->getApplication()->setUserState('hitobitauth.state', null);
	}

	/**
	 *  Checks if this user is part of an allowed Hitobito usergroup
	 * 
	 * @return  bool
	 */
	protected function isAllowedUsergroup()
	{
		$allowed_ids = \trim($this->params->get('hitobito_groupid_allowed', ''));

		if($allowed_ids === '')
		{
			// Empty field means everyone can login
			return true;
		}

		// Validate the param field
		$allowed_ids = \explode(',', $allowed_ids);
		$allowed_ids = ArrayHelper::toInteger($allowed_ids);

		foreach($this->credentials['roles'] as $role)
		{
			if(\in_array($role['group_id'], $allowed_ids))
			{
				// At least one group id matches
				return true;
			}
		}

		return false;
	}

  /**
   * Returns the plugin result
   *
   * @param   Event  $event  The event object
   * @param   mixed  $value  The value to be added to the result
   * @param   bool   $array  True, if the reuslt has to be added/set to the result array. False to override the boolean result value.
   *
   * @return  void
   */
  private function setResult(Event $event, $value, $array=true): void
	{
		if($event instanceof ResultAwareInterface)
    {
			$event->addResult($value);
			
			return;
		}

    if($array)
    {
      $result   = $event->getArgument('result', []) ?: [];
		  $result   = \is_array($result) ? $result : [];
		  $result[] = $value;
    }
    else
    {
      $result   = $event->getArgument('result', true) ?: true;
      $result   = ($result == false) ? false : $value;
    }
		
		$event->setArgument('result', $result);
	}

  /**
   * Returns the plugin error
   *
   * @param   Event  $event    The event object
   * @param   mixed  $message  The message to be added to the error
   *
   * @return  void
   */
  private function setError(Event $event, $message): void
	{
		if($event instanceof EventInterface)
    {

      $event->setArgument('error', $message);
      $event->setArgument('errorMessage', $message);
			
			return;
		}
	}
}
