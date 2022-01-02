<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Table\Mixin;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Joomla\CMS\Factory;

trait CreateModifyAware
{
	private $updateCreated = true;

	private $updateModified = true;

	public function getUpdateCreated(): bool
	{
		return $this->updateCreated;
	}

	public function setUpdateCreated(bool $updateCreated): void
	{
		$this->updateCreated = $updateCreated;
	}

	public function getUpdateModified(): bool
	{
		return $this->updateModified;
	}

	public function setUpdateModified(bool $updateModified): void
	{
		$this->updateModified = $updateModified;
	}

	public function onBeforeStore($updateNulls = false)
	{
		$date = Factory::getDate()->toSql();
		$user = UserFetcher::getUser();

		// Set created date if not set.
		if ($this->updateCreated && $this->hasField('created') && !(int) $this->created)
		{
			$this->created = $date;
		}

		if ($this->updateModified && ($this->getId() > 0))
		{
			// Existing item
			if ($this->hasField('modified_by'))
			{
				$this->modified_by = $user->id;
			}

			if ($this->hasField('modified'))
			{
				$this->modified = $date;
			}
		}
		elseif ($this->updateCreated || $this->updateModified)
		{
			// Field created_by can be set by the user, so we don't touch it if it's set.
			if ($this->updateCreated && $this->hasField('created_by') && empty($this->created_by))
			{
				$this->created_by = $user->id;
			}

			// Set modified to created date if not set
			if ($this->updateModified && $this->hasField('modified') && $this->hasField('created') && !(int) $this->modified)
			{
				$this->modified = $this->created;
			}

			// Set modified_by to created_by user if not set
			if ($this->updateModified && $this->hasField('modified_by') && $this->hasField('created_by') && empty($this->modified_by))
			{
				$this->modified_by = $this->created_by;
			}
		}
	}
}