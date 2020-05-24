<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Model\Exception;

defined('_JEXEC') or die();

use Joomla\CMS\Language\Text;
use RuntimeException;
use Throwable;

/**
 * Class BlatantSpam
 *
 * Signals that a comment is blatant spam and needs to be discarded immediately.
 *
 * @package Akeeba\Engage\Admin\Model\Exception
 */
class BlatantSpam extends RuntimeException
{
	public function __construct($message = "", $code = 0, Throwable $previous = null)
	{
		if (empty($message))
		{
			$message= Text::_('COM_ENGAGE_COMMENTS_ERR_BLATANT_SPAM');
		}

		parent::__construct($message, $code, $previous);
	}

}
