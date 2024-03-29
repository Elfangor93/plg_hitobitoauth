<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.HitobitoOAuth
 *
 * @author      Schlumpf
 * @copyright   Copyright (C) tech.spuur.ch
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die();

use \Joomla\CMS\Factory;
use \Joomla\CMS\Form\FormField;
use \Joomla\CMS\Form\Field\RadioField;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Filesystem\Path as JPath;

class JFormFieldJsGroups extends FormField
{
    /**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $type = 'JsGroups';

    /**
	 * Hide the label when rendering the form field.
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $hiddenLabel = true;

	/**
	 * Hide the description when rendering the form field.
	 *
	 * @var    boolean
	 * @since  4.0.0
	 */
	protected $hiddenDescription = true;

    protected function getLabel()
    {
		$css = '.form-horizontal .control-label {width: 300px;}';
		Factory::getApplication()->getDocument()->addStyleDeclaration($css);

		return '<button class="btn" onclick="getGroups(event)">'.Text::_($this->element['label']).'</button>';
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
        $js   = '';
        $path = JPath::clean(JPATH_PLUGINS.'/system/hitobitoauth/layouts/usergroups.js.php');

        ob_start();
		include $path;
		$js .= ob_get_contents();
		ob_end_clean();

        return  $js;
    }
}