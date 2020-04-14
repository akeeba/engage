<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Helper;

defined('_JEXEC') or die;

use FOF30\Utils\SelectOptions;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

final class Select
{
	/**
	 * Return the options for creating a dropdown for the Enabled field
	 *
	 * @param   array  $params  Parameters to pass to SelectOptions::getOptions
	 *
	 * @return  array
	 *
	 * @see     SelectOptions::getOptions
	 */
	public static function published(array $params = []): array
	{
		$options   = SelectOptions::getOptions('published', $params);
		$options[] = HTMLHelper::_('select.option', '-3', Text::_('COM_ENGAGE_COMMENT_ENABLED_OPT_SPAM'));

		ksort($options);

		return $options;
	}

	/**
	 * Return the options to select the email template type (`key` field)
	 *
	 * @param   bool  $short  Should I use short names instead?
	 *
	 * @return  array
	 */
	public static function emailTemplateKey($short = false): array
	{
		$suffix = $short ? '_SHORT' : '';

		return [
			HTMLHelper::_('select.option', 'manage', Text::_('COM_ENGAGE_EMAILTEMPLATES_KEY_MANAGE' . $suffix)),
			HTMLHelper::_('select.option', 'spam', Text::_('COM_ENGAGE_EMAILTEMPLATES_KEY_SPAM' . $suffix)),
			HTMLHelper::_('select.option', 'notify', Text::_('COM_ENGAGE_EMAILTEMPLATES_KEY_NOTIFY' . $suffix)),
		];
	}


}