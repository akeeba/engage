<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Trait to easily manage model state fields for ListModel classes
 */
trait ModelPopulateStateTrait
{
	/**
	 * Default sort direction: ASC or DESC (case–sensitive)
	 *
	 * @var   null|string
	 * @since 3.0.0
	 */
	private $defaultDirection = null;

	/**
	 * Default sort field. Must be one of the $this->stateFilterFields keys
	 *
	 * @var   null|string
	 * @since 3.0.0
	 */
	private $defaultOrdering = null;

	/**
	 * State filter field definitions.
	 *
	 * Given in the format [columnName => filterType, ...] where filterType is one of string, int, ignore.
	 * * string. Accepts string.
	 * * int. Accepts int. Empty state is an empty string (numeric 0 is NOT an empty state!)
	 * * ignore. Not populated from the request. Must be explicitly passed to the model.
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private $stateFilterFields = [];

	/** @inheritdoc */
	protected function getStoreId($id = '')
	{
		foreach (array_keys($this->stateFilterFields) as $key)
		{
			$v  = $this->getState('filter.' . $key);
			$id .= ':';

			if (empty($v))
			{
				continue;
			}

			if (is_scalar($v))
			{
				$id .= $v;
				continue;
			}

			$id .= @json_encode($v) ?: serialize($v);
		}

		return parent::getStoreId($id);
	}

	/** @inheritdoc */
	protected function populateState($ordering = null, $direction = null)
	{
		$ordering  = $ordering ?? $this->defaultOrdering;
		$direction = $direction ?? $this->defaultDirection;

		$app = Factory::getApplication();

		foreach ($this->stateFilterFields as $name => $type)
		{
			if ($type == 'ignore')
			{
				continue;
			}

			$value = $app->getUserStateFromRequest(
				$this->context . 'filter.' . $name,
				'filter_' . $name, '', $type
			);

			switch ($type)
			{
				case 'string':
					$this->setState('filter.' . $name, $value);
					break;

				case 'array':
					if (!is_array($value))
					{
						$value = explode(',', $value);
					}

					$value = array_filter($value, function ($x) {
						return !empty($x);
					});

					$this->setState('filter.' . $name, $value);
					break;

				case 'int':
					$this->setState('filter.' . $name, ($value === '') ? $value : (int) $value);
					break;
			}
		}

		parent::populateState($ordering, $direction);
	}

	/**
	 * Set up the model state filter fields. Used in populateState and getStoreId.
	 *
	 * @param   array        $filters           Filter definitions, see $this->stateFilterFields
	 * @param   string|null  $defaultOrdering   Default ordering column, must be a key in $filters
	 * @param   string|null  $defaultDirection  Default ordering direction, ASC or DESC (case–sensitive)
	 *
	 *
	 * @since   3.0.0
	 */
	private function setupStateFilters(array $filters, ?string $defaultOrdering = null, ?string $defaultDirection = null): void
	{
		$defaultDirection = is_string($defaultDirection) ? strtoupper($defaultDirection) : null;

		$this->stateFilterFields = $filters;
		$this->defaultOrdering   = array_key_exists($defaultOrdering, $filters) ? $defaultOrdering : null;
		$this->defaultDirection  = in_array($defaultDirection, ['ASC', 'DESC']) ? $defaultDirection : null;
	}
}