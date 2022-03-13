<?php
defined('_JEXEC') or die();

use \Joomla\CMS\Form\Field\RadioField;
use \Joomla\CMS\Language\Text;

class JFormFieldJsGroups extends JFormField
{
    /**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.6
	 */
	public $type = 'JsGroups';

    protected function getLabel()
    {
		return '';
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
        $js = '<script>function getGroups(event) {';
            $js .= 'event.preventDefault();';
            $js .= 'let id = document.getElementById("jform_params_hitobito_groupid").value;';
            $js .= 'let token = document.getElementById("jform_params_hitobito_grouptoken").value;';
            $js .= 'let host = document.getElementById("jform_params_clienthost").value;';
            $js .= 'window.open(host+"/de/groups/"+id+".json?token="+token,"popUpWindow","height=500,width=400,left=100,top=100,resizable=yes,scrollbars=yes,toolbar=no,menubar=no,location=no,directories=no,status=no");';
        $js .= '};</script>';

        return  $js;
    }
}