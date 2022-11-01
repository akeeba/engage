<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Table;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Mixin\TableColumnAliasTrait;
use Akeeba\Component\Engage\Administrator\Mixin\TriggerEventTrait;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;

/**
 * Common class for all table objects
 *
 * @since  3.0.0
 */
#[\AllowDynamicProperties]
abstract class AbstractTable extends Table
{
	use TriggerEventTrait;
	use TableColumnAliasTrait;

	/** @inheritdoc */
	public function __construct($table, $key, DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		parent::__construct($table, $key, $db, $dispatcher);

		$this->triggerEvent('onAfterInitialise');
	}

	/** @inheritdoc */
	public function bind($src, $ignore = [])
	{
		$this->triggerEvent('onBeforeBind', [&$src, &$ignore]);

		$result = parent::bind($src, $ignore);

		$this->triggerEvent('onAfterBind', [&$result, $src, $ignore]);

		return $result;
	}

	/** @inheritdoc */
	public function check()
	{
		try
		{
			$this->triggerEvent('onBeforeCheck');

			$result = parent::check();
		}
		catch (\Exception $e)
		{
			$this->setError($e->getMessage());

			$result = false;
		}

		$this->triggerEvent('onAfterCheck', [&$result]);

		return $result;
	}

	/** @inheritdoc */
	public function checkIn($pk = null)
	{
		$this->triggerEvent('onBeforeCheckIn', [&$pk]);

		$result = parent::checkIn($pk);

		$this->triggerEvent('onAfterCheckIn', [&$result, $pk]);

		return $result;
	}

	/** @inheritdoc */
	public function checkOut($userId, $pk = null)
	{
		$this->triggerEvent('onBeforeCheckout', [&$userId, &$pk]);

		$result = parent::checkOut($userId, $pk);

		$this->triggerEvent('onAfterCheckout', [&$result, $userId, $pk]);

		return $result;
	}

	/** @inheritdoc */
	public function delete($pk = null)
	{
		$this->triggerEvent('onBeforeDelete', [&$pk]);

		$result = parent::delete($pk);

		$this->triggerEvent('onAfterDelete', [&$result, $pk]);

		return $result;
	}

	/** @inheritdoc */
	public function getAssetName()
	{
		return $this->_getAssetName();
	}

	/** @inheritdoc */
	public function hit($pk = null)
	{
		$this->triggerEvent('onBeforeHit', [&$pk]);

		$result = parent::hit($pk);

		$this->triggerEvent('onAfterHit', [&$result, $pk]);

		return $result;
	}

	/** @inheritdoc */
	public function load($keys = null, $reset = true)
	{
		$this->triggerEvent('onBeforeLoad', [&$keys, &$reset]);

		$result = parent::load($keys, $reset);

		$this->triggerEvent('onAfterLoad', [&$result, $keys, $reset]);

		return $result;
	}

	/** @inheritdoc */
	public function move($delta, $where = '')
	{
		$this->triggerEvent('onBeforeMove', [&$delta, &$where]);

		$result = parent::move($delta, $where);

		$this->triggerEvent('onAfterMove', [&$result, $delta, $where]);

		return $result;
	}

	/** @inheritdoc */
	public function publish($pks = null, $state = 1, $userId = 0)
	{
		$this->triggerEvent('onBeforePublish', [&$pks, &$state, &$userId]);

		$result = parent::publish($pks, $state, $userId);

		$this->triggerEvent('onAfterPublish', [&$result, $pks, $state, $userId]);

		return $result;
	}

	/** @inheritdoc */
	public function reorder($where = '')
	{
		$this->triggerEvent('onBeforeReorder', [&$where]);

		$result = parent::reorder($where);

		$this->triggerEvent('onAfterReorder', [&$result, $where]);

		return $result;
	}

	/** @inheritdoc */
	public function reset()
	{
		$this->triggerEvent('onBeforeReset');

		parent::reset();

		$this->triggerEvent('onAfterReset');
	}

	/** @inheritdoc */
	public function save($src, $orderingFilter = '', $ignore = '')
	{
		$this->triggerEvent('onBeforeSave', [&$src, &$orderingFilter, &$ignore]);

		$result = parent::save($src, $orderingFilter, $ignore);

		$this->triggerEvent('onAfterSave', [&$result, $src, $orderingFilter, $ignore]);

		return $result;
	}

	/** @inheritdoc */
	public function store($updateNulls = false)
	{
		$this->triggerEvent('onBeforeStore', [&$updateNulls]);

		$result = parent::store($updateNulls);

		$this->triggerEvent('onAfterStore', [&$result, $updateNulls]);

		return $result;
	}
}