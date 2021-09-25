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

use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\MVC\Model\BaseDatabaseModel;
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
	 * User credentials
	 *
	 * @var    array
	 * @since  1.0.0
	 */
	protected $credentials = array();

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
		dump('start of plg_system_hitobitoauth');
	}
}
