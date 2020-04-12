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
	public static function published(array $params = [])
	{
		$options   = SelectOptions::getOptions('published', $params);
		$options[] = HTMLHelper::_('select.option', '-3', Text::_('COM_ENGAGE_COMMENT_ENABLED_OPT_SPAM'));

		ksort($options);

		return $options;
	}
}