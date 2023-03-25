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

\defined('_JEXEC') or die();

use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Form\Field\UrlField;

/**
 * Form Field class for the Joomla Platform.
 * Supports a one line text field.
 *
 * @link   https://html.spec.whatwg.org/multipage/input.html#text-(type=text)-state-and-search-state-(type=search)
 * @since  1.7.0
 */
class HosturlField extends UrlField
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  1.7.0
	 */
	protected $type = 'Hosturl';

	/**
	 * Method to get the field input markup.
	 *
	 * @return  string  The field input markup.
	 *
	 * @since   3.1.2 (CMS)
	 */
	protected function getInput()
	{	
		// Trim the trailing line in the layout file
		return rtrim($this->getRenderer($this->layout)->render($this->getLayoutData()), PHP_EOL);
	}

	/**
	 * Method to get the data to be passed to the layout for rendering.
	 *
	 * @return  array
	 *
	 * @since 3.7
	 */
	protected function getLayoutData()
	{
		$data = parent::getLayoutData();

		$data['value'] = Uri::root();

		// Initialize some field attributes.
		$maxLength    = !empty($this->maxLength) ? ' maxlength="' . $this->maxLength . '"' : '';

		// Note that the input type "url" is suitable only for external URLs, so if internal URLs are allowed
		// we have to use the input type "text" instead.
		$inputType    = $this->element['relative'] ? 'type="text"' : 'type="url"';

		$extraData = array(
			'maxLength' => $maxLength,
			'inputType' => $inputType,
		);

		return array_merge($data, $extraData);
	}
}
