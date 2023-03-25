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
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Filesystem\Path;

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
		$html = '<button class="btn btn-primary" onclick="getOAuthToken(this, \'administrator\')">'.Text::_($this->element['label']).'</button>';

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
		$script   = '';
		$path = Path::clean(JPATH_PLUGINS.'/system/hitobitoauth/layouts/oauth.js.php');

		ob_start();
		include $path;
		$script .= ob_get_contents();
		ob_end_clean();

        return  '<script>'.$script.'</script>';
	}
}
