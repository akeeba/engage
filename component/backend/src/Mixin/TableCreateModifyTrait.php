<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Joomla\CMS\Factory;

trait TableCreateModifyTrait
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
		elseif ($this->updateCreated)
		{
			// Field created_by can be set by the user, so we don't touch it if it's set.
			if ($this->updateCreated && $this->hasField('created_by') && empty($this->created_by))
			{
				$this->created_by = $user->id;
			}
		}
	}
}