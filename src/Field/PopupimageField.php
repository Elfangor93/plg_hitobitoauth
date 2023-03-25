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
		$path = Uri::root().'plugins/system/hitobitoauth/images/'.$this->element['label'];
		$html = Text::_('PLG_SYSTEM_HITOBITOAUTH_EXAMPLE_IMAGE').': <a href="#" onclick="popupImage(\''.$path.'\')">'.Text::_('PLG_SYSTEM_HITOBITOAUTH_SHOW_IMAGE').'</a>';
		return $html;
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
        $js  = 'var popupImage = function(url) {';
    	$js .= 		'let winprops = "height=400,width=600,top=100,left=100,scrollbars=1,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no";';
    	$js .=      'let popupWin = window.open(url, "'.Text::_('PLG_SYSTEM_HITOBITOAUTH_EXAMPLE_IMAGE').'", winprops);';
		$js .= '};';

		$html = '<script>'.$js.'</script>';
        return  $html;
    }
}