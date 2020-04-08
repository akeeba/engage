<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

// Prevent direct access
defined('_JEXEC') or die;

if (class_exists('JFormFieldModuleModules'))
{
	return;
}

FormHelper::loadFieldClass('list');

/**
 * ModulePositions Field class for the Joomla Framework.
 *
 * @since   1.0.0
 */
class JFormFieldModuleModules extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 */
	protected $type = 'ModuleModules';

	protected function getOptions()
	{
		HTMLHelper::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_modules/helpers/html');
		@include_once JPATH_ADMINISTRATOR . '/components/com_modules/helpers/modules.php';

		if (!class_exists('ModulesHelper'))
		{
			return [];
		}

		$clientId = is_null($this->element->attributes()->client_id) ? 0 : (int) $this->element->attributes()->client_id;
		$modules  = ModulesHelper::getModules($clientId);

		$none = HTMLHelper::_('select.option', '', Text::_('COM_ENGAGE_CONFIG_LOGIN_NONE'));
		array_unshift($modules, $none);

		return $modules;
	}
}
