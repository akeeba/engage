<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Model\Mixin\PopulateStateAware;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\ParameterType;

/**
 * Backend comments list model
 *
 * @since 3.0.0
 */
class CommentsModel extends ListModel
{
	use PopulateStateAware;

	public function __construct($config = [], MVCFactoryInterface $factory = null)
	{
		$config['filter_fields'] = $config['filter_fields'] ?? [
				// Sortable and/or filter columns
				'id', 'asset_id', 'name', 'email', 'ip', 'user_agent', 'enabled',
				'created', 'created_by', 'modified', 'modified_by',
				// Filter–only fields
				'search', 'from', 'to',
			];

		parent::__construct($config, $factory);

		$this->setupStateFilters([
			// Visible filters
			'search'     => 'string',
			'from'       => 'string',
			'to'         => 'string',
			'created_by' => 'int',
			'enabled'    => 'int',

			// Internal filters
			'asset_id'   => 'int',
			'parent_id'  => 'int',
		], 'created', 'DESC');
	}

	protected function getListQuery()
	{
		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->quoteName('c' . '.*'),
				'IFNULL(' . $db->quoteName('u.name') . ', ' . $db->quoteName('c.name') . ') AS ' . $db->quoteName('user_name'),
				'IFNULL(' . $db->quoteName('u.email') . ', ' . $db->quoteName('c.email') . ') AS ' . $db->quoteName('user_email'),
				$db->quoteName('a.title', 'article_title'),
				$db->quoteName('a.alias', 'article_alias'),
				$db->quoteName('a.catid', 'article_catid'),
				$db->quoteName('cat.title', 'cat_title'),
				$db->quoteName('cat.alias', 'cat_alias'),
			])
			->from($db->quoteName('#__engage_comments', 'c'))
			->join('LEFT', $db->quoteName('#__users', 'u'),
				$db->quoteName('u.id') . ' = ' . $db->quoteName('c.created_by')
			)
			->join('LEFT', $db->quoteName('#__content', 'a'),
				$db->quoteName('a.asset_id') . ' = ' . $db->quoteName('c.asset_id')
			)
			->join('LEFT', $db->quoteName('#__categories', 'cat'),
				$db->quoteName('cat.id') . ' = ' . $db->quoteName('a.catid')
			);

		// Asset ID filter
		$fltAssetId = $this->getState('filter.asset_id');

		if (is_numeric($fltAssetId) && ($fltAssetId > 0))
		{
			$query->where($db->quoteName('c.asset_id') . ' = :asset_id')
				->bind(':asset_id', $fltAssetId, ParameterType::INTEGER);
		}

		// Parent ID filter
		$fltParentid = $this->getState('filter.parent_id');

		if (is_numeric($fltParentid) && ($fltParentid > 0))
		{
			$query->where($db->quoteName('c.parent_id') . ' = :parent_id')
				->bind(':parent_id', $fltParentid, ParameterType::INTEGER);
		}

		// Search filter
		$fltSearch    = $this->getState('filter.search');
		$fltCreatedBy = $this->getState('filter.created_by');

		if (!empty($fltSearch))
		{
			if (substr($fltSearch, 0, 3) === 'id:')
			{
				$fsValue = substr($fltSearch, 3);

				$query->where($db->quoteName('c.id') . ' = :filter_search')
					->bind(':filter_search', $fsValue, ParameterType::INTEGER);
			}
			elseif (substr($fltSearch, 0, 5) === 'user:')
			{
				$fltCreatedBy = null;
				$fsValue      = '%' . substr($fltSearch, 5) . '%';

				if ($query->where === null)
				{
					// So that extendWhere() doesn't output bad SQL
					$query->where('1=1');
				}

				$query->extendWhere('AND', [
					$db->quoteName('user_name') . ' LIKE :filter_search_1',
					$db->quoteName('user_email') . ' LIKE :filter_search_2',
				], 'OR')
					->bind(':filter_search_1', $fsValue, ParameterType::STRING)
					->bind(':filter_search_2', $fsValue, ParameterType::STRING);
			}
			elseif (substr($fltSearch, 0, 6) === 'title:')
			{
				$fsValue = '%' . substr($fltSearch, 6) . '%';
				$query->where($db->quoteName('a.asset_id') . ' = :filter_search')
					->bind(':filter_search', $fsValue, ParameterType::INTEGER);
			}
			elseif (substr($fltSearch, 0, 8) === 'comment:')
			{
				$fsValue = '%' . substr($fltSearch, 8) . '%';

				$query->where($db->quoteName('c.body') . 'LIKE :filter_search')
					->bind(':filter_search', $fsValue, ParameterType::STRING);
			}
			else
			{
				$fsValue = '%' . $fltSearch . '%';

				if ($query->where === null)
				{
					// So that extendWhere() doesn't output bad SQL
					$query->where('1=1');
				}

				$query->extendWhere('AND', [
					$db->quoteName('c.body') . 'LIKE :filter_search',
					$db->quoteName('user_name') . ' LIKE :filter_search_1',
					$db->quoteName('user_email') . ' LIKE :filter_search_2',
				], 'OR')
					->bind(':filter_search', $fsValue, ParameterType::STRING)
					->bind(':filter_search_1', $fsValue, ParameterType::STRING)
					->bind(':filter_search_2', $fsValue, ParameterType::STRING);
			}
		}

		// Created By filter
		if (is_numeric($fltCreatedBy) && ($fltCreatedBy > 0))
		{
			$query->where($db->quoteName('c.created_by') . ' = :created_by')
				->bind(':created_by', $fltCreatedBy, ParameterType::INTEGER);
		}

		// Enabled filter
		$fltEnabled = $this->getState('filter.enabled');

		if (is_numeric($fltEnabled))
		{
			$query->where($db->quoteName('c.enabled') . ' = :enabled')
				->bind(':enabled', $fltEnabled, ParameterType::INTEGER);
		}

		// From/to filter
		$fltFrom = $this->getState('filter.from');
		$fltTo   = $this->getState('filter.to');

		// -- Convert to Joomla date objects
		try
		{
			$fltFrom = $fltFrom ? new Date($fltFrom) : null;
		}
		catch (Exception $e)
		{
			$fltFrom = null;
		}

		try
		{
			$fltTo = $fltFrom ? new Date($fltTo) : null;
		}
		catch (Exception $e)
		{
			$fltTo = null;
		}

		// Swap dates if both are defined but from is later than to.
		if (!empty($fltTo) && !empty($fltFrom) && ($fltTo->diff($fltFrom) != 0))
		{
			$temp    = $fltFrom;
			$fltFrom = $fltTo;
			$fltTo   = $temp;
			unset($temp);
		}

		if (!empty($fltTo) && !empty($fltFrom))
		{
			$sFrom = $fltFrom->toSql();
			$sTo   = $fltTo->toSql();
			$query->where($db->quoteName('c.created_on') . ' BETWEEN :from AND :to')
				->bind(':from', $sFrom, ParameterType::STRING)
				->bind(':to', $sTo, ParameterType::STRING);
		}
		elseif (!empty($fltFrom))
		{
			$sFrom = $fltFrom->toSql();
			$query->where($db->quoteName('c.created_on') . ' >= :from')
				->bind(':from', $sFrom, ParameterType::STRING);
		}
		elseif (!empty($fltTo))
		{
			$sTo = $fltTo->toSql();
			$query->where($db->quoteName('c.created_on') . ' <= :to')
				->bind(':to', $sTo, ParameterType::STRING);
		}

		// List ordering clause
		$orderCol  = $this->state->get('list.ordering', 'created');
		$orderDirn = $this->state->get('list.direction', 'DESC');
		$ordering  = $db->escape($orderCol) . ' ' . $db->escape($orderDirn);

		$query->order($ordering);

		return $query;
	}


}