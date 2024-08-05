<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Manuel Häusler (Schlumpf)
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Schlumpf\Plugin\System\Hitobitoauth\Field;

defined('_JEXEC') or die();

use \Joomla\CMS\Factory;
use \Joomla\CMS\Form\FormField;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Uri\Uri;

class JscheckcreditsField extends FormField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $type = 'Jscheckcredits';

	/**
	 * Hide the label when rendering the form field.
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $hiddenLabel = false;

	/**
	 * Hide the description when rendering the form field.
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $hiddenDescription = false;

	/**
	 * Method to get the field label markup.
	 *
	 * @return  string  The field label markup.
	 *
	 * @since   1.7.0
	 */
	protected function getLabel()
	{
		$html = '<button class="btn btn-primary" onclick="getOAuthToken(event, \''. Uri::root() .'\', \'administrator\')">' . Text::_($this->element['label']) . '</button>';

		return $html;
	}

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   1.7.0
	 */
	protected function getInput()
	{
    // Add text strings to Javascript
    Text::script('PLG_SYSTEM_HITOBITOAUTH_API_TOKEN_NEEDED');
    Text::script('PLG_SYSTEM_HITOBITOAUTH_API_ERROR_CORS');
    Text::script('PLG_SYSTEM_HITOBITOAUTH_AVAILABLE_ROLES');
    Text::script('PLG_SYSTEM_HITOBITOAUTH_NO_AVAILABLE_ROLES');
    Text::script('PLG_SYSTEM_HITOBITOAUTH_EXAMPLE_IMAGE');

    /** @var Joomla\CMS\WebAsset\WebAssetManager $wa */
    $wa = Factory::getApplication()->getDocument()->getWebAssetManager();

    $wa->registerAndUseScript('hitobitoauth.script', 'plg_system_hitobitoauth/hitobitoauth.js');

    return '';
	}
}
