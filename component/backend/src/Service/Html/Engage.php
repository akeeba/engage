<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Service\Html;

use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;

defined('_JEXEC') or die;

final class Engage
{
	use DatabaseAwareTrait;

	/**
	 * Public constructor
	 *
	 * @param   DatabaseDriver  $db  The application's database driver object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->setDbo($db);
	}
}