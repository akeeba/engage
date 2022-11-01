<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

trait TableColumnAliasTrait
{
	public function __get($name)
	{
		if ($this->hasField($name))
		{
			$realColumn = $this->getColumnAlias($name);

			return $this->{$realColumn};
		}

		return $this->{$name} ?? null;
	}

	/**
	 * Magic setter, is aware of column aliases.
	 *
	 * This is required for using Joomla's batch processing to copy / move records of tables which do not have a catid
	 * column.
	 *
	 * @param $name
	 * @param $value
	 */
	public function __set($name, $value)
	{
		if ($this->hasField($name))
		{
			$realColumn          = $this->getColumnAlias($name);
			$this->{$realColumn} = $value;

			return;
		}

		$this->{$name} = $value;
	}


}