<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Table\AbstractTable;
use Exception;
use Joomla\CMS\Language\Text;

/**
 * A trait to replace getItem with something which returns a real table instead of a plain old, stupid object
 */
trait ModelGetItemTrait
{
	/**
	 * Method to get a single record.
	 *
	 * @param   int|null  $pk  The id of the primary key.
	 *
	 * @return  AbstractTable|bool  Object on success, false on failure.
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function getItemTable($pk = null)
	{
		$pk    = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');
		$table = $this->getTable();

		if ($pk > 0)
		{
			if ($table->load($pk) === false)
			{
				$this->setError($table->getError() ?: Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));

				return false;
			}
		}

		return $table;
	}

}