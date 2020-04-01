<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\View\Comments;


use Akeeba\Engage\Site\Model\Comments;
use FOF30\View\DataView\Html as DataHtml;
use Joomla\CMS\Pagination\Pagination;
use RuntimeException;

class Html extends DataHtml
{
	/**
	 * Executes before rendering the page for the Browse task.
	 */
	protected function onBeforeBrowse()
	{
		// Create the lists object
		$this->lists = new \stdClass();

		// Load the model
		/** @var Comments $model */
		$model = $this->getModel();

		// We want to persist the state in the session
		$model->savestate(1);

		// Get the asset_id
		$assetId = $this->input->getInt('asset_id', 0);

		if (empty($assetId))
		{
			throw new RuntimeException('Cannot display all comments in the frontend');
		}

		// Display limits
		$defaultLimit = $this->getDefaultListLimit();

		$this->lists->limitStart = $model->getState('comments_limitstart', 0, 'int');
		$this->lists->limit      = $model->getState('comments_limit', $defaultLimit, 'int');

		$model->limitstart = $this->lists->limitStart;
		$model->limit      = $this->lists->limit;

		// Assign items to the view
		$model = $model->getRoot();
		$model->scopeAssetCommentTree($assetId);

		$this->items     = $model->get(false);
		$this->itemCount = $model->count();

		// Ordering information
		$this->lists->order     = $model->getState('filter_order', $model->getIdFieldName(), 'cmd');
		$this->lists->order_Dir = $model->getState('filter_order_Dir', null, 'cmd');

		if ($this->lists->order_Dir)
		{
			$this->lists->order_Dir = strtolower($this->lists->order_Dir);
		}

		// Pagination
		$this->pagination = new Pagination($this->itemCount, $this->lists->limitStart, $this->lists->limit, 'comments_');

		// Pass page params on frontend only
		if ($this->container->platform->isFrontend())
		{
			/** @var \JApplicationSite $app */
			$app              = \JFactory::getApplication();
			$params           = $app->getParams();
			$this->pageParams = $params;
		}
	}

	/**
	 * @return int|mixed
	 * @throws \Exception
	 */
	protected function getDefaultListLimit()
	{
		$defaultLimit = 20;

		if (!$this->container->platform->isCli() && class_exists('JFactory'))
		{
			$app = \JFactory::getApplication();

			if (method_exists($app, 'get'))
			{
				$defaultLimit = $app->get('list_limit');
			}
			else
			{
				$defaultLimit = 20;
			}
		}

		return $defaultLimit;
	}

}