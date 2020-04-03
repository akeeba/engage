<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Controller;

defined('_JEXEC') or die();

use Akeeba\Engage\Site\View\Comments\Html;
use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Controller\Mixin\PredefinedTaskList;
use FOF30\View\Exception\AccessForbidden;

class Comments extends DataController
{
	use PredefinedTaskList;

	/** @inheritDoc */
	public function __construct(Container $container, array $config = [])
	{
		parent::__construct($container, $config);

		$this->setPredefinedTaskList([
			'browse', 'edit', 'save', 'cancel', 'publish', 'unpublish', 'remove',
		]);
	}

	/**
	 * Ensures that we are allowed to display a list of comments.
	 */
	protected function onBeforeBrowse()
	{
		// Make sure we are allowed to show this page (the content plugin explicitly told us to render it).
		if (!isset($this->container['commentsBrowseEnablingFlag']) || !$this->container['commentsBrowseEnablingFlag'])
		{
			throw new AccessForbidden();
		}

		// Get the asset_id and access level
		$assetId = $this->input->getInt('asset_id', 0);
		$access  = $this->input->getInt('access', 0);

		if (empty($assetId))
		{
			throw new AccessForbidden();
		}


		$user = $this->container->platform->getUser();

		if (!in_array($access, $user->getAuthorisedViewLevels()))
		{
			throw new AccessForbidden();
		}

		// Pass the asset ID to the view
		/** @var Html $view */
		$view = $this->getView();

		$view->assetId = $assetId;
	}
}