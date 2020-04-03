<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\View\Comments;

defined('_JEXEC') or die();

use Akeeba\Engage\Site\Model\Comments;
use Exception;
use FOF30\View\DataView\Html as DataHtml;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;

class Html extends DataHtml
{
	/**
	 * Root node for all comments
	 *
	 * @var Comments
	 */
	public $rootNode;

	/**
	 * The asset ID to display comments for
	 *
	 * @var int
	 */
	public $assetId;

	/**
	 * Executes before rendering the page for the Browse task.
	 */
	protected function onBeforeBrowse()
	{
		// Load the CSS
		$this->addCssFile('media://com_engage/css/comments.min.css');

		// Load the model and persist its state in the session
		/** @var Comments $model */
		$model = $this->getModel();

		$model->savestate(1);

		// Display limits
		$defaultLimit = $this->getDefaultListLimit();

		$this->lists             = new \stdClass();
		$this->lists->limitStart = $this->input->getInt('akengage_limitstart', 0);
		$this->lists->limit      = $model->getState('akengage_limit', $defaultLimit, 'int');

		// Pass the display limits to the model
		$model->limitstart = $this->lists->limitStart;
		$model->limit      = $this->lists->limit;

		// Assign items to the view
		$model          = $model->getRoot();
		$this->rootNode = $model->getClone()->bind(['depth' => 0]);

		$model->scopeAssetCommentTree($this->assetId);

		$this->items     = $model->get(false);
		$this->itemCount = $model->count();

		// Pagination
		$this->pagination = new Pagination($this->itemCount, $this->lists->limitStart, $this->lists->limit, 'akengage_');

		// Pass page params on frontend only
		if (!$this->container->platform->isFrontend())
		{
			return;
		}

		/** @var \JApplicationSite $app */
		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		$params           = $app->getParams();
		$this->pageParams = $params;
	}

	/**
	 * Get the default list limit configured by the site administrator
	 *
	 * @return  int
	 */
	protected function getDefaultListLimit(): int
	{
		$defaultLimit = 20;

		if ($this->container->platform->isCli() || !class_exists('Joomla\CMS\Factory'))
		{
			return $defaultLimit;
		}

		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return $defaultLimit;
		}

		if (is_object($app) && method_exists($app, 'get'))
		{
			$defaultLimit = (int) $app->get('list_limit', 20);
		}

		return $defaultLimit;
	}

}