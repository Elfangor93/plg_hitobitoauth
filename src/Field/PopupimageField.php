<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Schlumpf\Plugin\System\Hitobitoauth\Field;

defined('_JEXEC') or die();

use \Joomla\CMS\Factory;
use \Joomla\CMS\Form\FormField;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Language\Text;

class PopupimageField extends FormField
{
    /**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $type = 'Popupimage';

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

  protected function getLabel()
  {
    return Text::_($this->element['label']);
  }

  /**
   * Method to get the user field input markup.
   *
   * @return  string  The field input markup.
   *
   * @since   1.6
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

    $path = Uri::root().'media/plg_system_hitobitoauth/images/'.$this->element['value'];
    $html = '<button class="btn btn-outline-secondary" onclick="popupImage(event, \''.$path.'\')">'.Text::_('PLG_SYSTEM_HITOBITOAUTH_SHOW_IMAGE').'</button>';
    
    return $html;
  }
}
