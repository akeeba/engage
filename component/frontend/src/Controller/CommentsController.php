<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Controller\CommentsController as AdminCommentsController;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\GetRedirectionAware;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\ReturnURLAware;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\ReusableModels;
use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\View\Comments\HtmlView;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Plugin\Content\Engage\Extension\Engage;
use RuntimeException;

class CommentsController extends AdminCommentsController
{
	use FrontendCommentsAware;
	use GetRedirectionAware;
	use ReturnURLAware;
	use ReusableModels;

	/**
	 * Disable method inapplicable to the frontend
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function checkin()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/** @inheritDoc */
	public function display($cachable = false, $urlparams = [])
	{
		$urlparams = array_merge_recursive([
			'option'              => 'CMD',
			'view'                => 'CMD',
			'task'                => 'CMD',
			'format'              => 'CMD',
			'layout'              => 'CMD',
			'asset_id'            => 'INT',
			'akengage_limitstart' => 'INT',
			'akengage_limit'      => 'INT',
		], $urlparams);

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Disable method inapplicable to the frontend
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function reorder()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Disable method inapplicable to the frontend
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function runTransition()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Disable method inapplicable to the frontend
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function saveOrderAjax()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Disable method inapplicable to the frontend
	 *
	 * @return  bool
	 * @since   3.0.0
	 */
	public function saveorder()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Unsubscribes a user from notifications regarding a specific content item's comments.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function unsubscribe(): void
	{
		$this->checkToken();

		$this->disableJoomlaCache();

		// I need at least a comment ID and an email to unsubscribe
		$id               = $this->input->get->getInt('id', 0);
		$unsubscribeEmail = $this->input->get->getString('email', '');

		/** @var CommentTable $comment */
		$comment = $this->getModel()->getTable('Comment', 'Administrator');

		if (($id <= 0) || empty($unsubscribeEmail) || !$comment->load($id))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Try to unsubscribe -- if already unsubscribed redirect back with an error
		$o = (object) [
			'asset_id' => $comment->asset_id,
			'email'    => $unsubscribeEmail,
		];
		/** @var DatabaseDriver $db */
		$db = Factory::getContainer()->get('DatabaseDriver');

		try
		{
			if (!$db->insertObject('#__engage_unsubscribe', $o))
			{
				throw new RuntimeException('Already unsubscribed');
			}

			$this->app->triggerEvent('onEngageUnsubscribeEmail', [$comment, $unsubscribeEmail]);

			$message = Text::sprintf('COM_ENGAGE_COMMENTS_LBL_UNSUBSCRIBED', $unsubscribeEmail);
			$msgType = 'info';
		}
		catch (Exception $e)
		{
			$message = Text::_('COM_ENGAGE_COMMENTS_ERR_ALREADY_UNSUBSCRIBED');
			$msgType = 'error';
		}

		$this->setMessage($message, $msgType);

		$this->applyReturnUrl();
	}

	protected function onAfterPossiblespam(): void
	{
		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Runs after publishing a comment. Adjusts the redirection with the published comment's ID in the fragment.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function onAfterPublish()
	{
		$this->disableJoomlaCache();
		$this->addCommentFragmentToReturnURL();
		$this->cleanCache();
	}

	protected function onAfterReportham()
	{
		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Runs after unpublishing a comment. Adjusts the redirection with the unpublished comment's ID in the fragment.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function onAfterUnpublish()
	{
		$this->disableJoomlaCache();
		$this->addCommentFragmentToReturnURL();
		$this->cleanCache();
	}

	/**
	 * Ensures that we are allowed to display a list of comments.
	 *
	 * @return  void
	 * @throws  RuntimeException|Exception
	 * @since   1.0.0
	 */
	protected function onBeforeMain(): void
	{
		/**
		 * If the current user has the core.edit.own privilege we have to disable caching. The idea is that each user in
		 * the group that gives the core.edit.own privilege will see an edit button on DIFFERENT comments than any other
		 * user in that group.
		 *
		 * In all other cases we let Joomla figure out caching. If enabled, it's performed per user group. All users
		 * with the same user group combination will be seeing the exact same comments display at all times.
		 */
		if (UserFetcher::getUser()->authorise('core.edit.own', 'com_engage'))
		{
			$this->disableJoomlaCache();
		}

		// Make sure we are allowed to show this page (we must be called by the plugin).
		$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
		$allowed   = false;

		foreach ($backtrace as $info)
		{
			if (!isset($info['class']) || !isset($info['function']))
			{
				continue;
			}

			if ($info['class'] === Engage::class && $info['function'] === 'renderComments')
			{
				$allowed = true;

				break;
			}
		}

		if (!$allowed)
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Apply the custom pagination to the model.
		$defaultLimit = $this->getDefaultListLimit();
		$start        = $this->app->getUserStateFromRequest('com_engage.comments.limitstart', 'akengage_limitstart', 0);
		$limit        = $this->app->getUserStateFromRequest('com_engage.comments.limit', 'akengage_limit', $defaultLimit);

		$model = $this->getModel('Comments', 'Site', ['ignore_request' => true]);
		$model->setState('list.start', $start);
		$model->setState('list.limit', $limit);

		// Get the asset_id and assert we have access to it
		$assetId = $this->getAssetId();

		// Pass the data to the view
		/** @var HtmlView $view */
		$view          = $this->getView();
		$view->setModel($model, true);
		$view->assetId = $assetId;
	}

	/**
	 * Get the default list limit configured by the site administrator
	 *
	 * @return  int
	 * @since   3.0.0
	 */
	private function getDefaultListLimit(): int
	{
		$defaultLimit = ComponentHelper::getParams('com_engage')->get('default_limit', 20);
		$defaultLimit = ($defaultLimit > 0) ? $defaultLimit : 0;

		if (!is_null($defaultLimit) || !class_exists(Factory::class))
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

	/** @inheritDoc */
	protected function postDeleteHook(BaseDatabaseModel $model, $id = null)
	{
		parent::postDeleteHook($model, $id);

		$this->disableJoomlaCache();
		$this->cleanCache();
	}

	/**
	 * Get the asset ID from the request and verify it is real
	 *
	 * @return  int
	 *
	 * @throws  RuntimeException
	 */
	private function getAssetId(): int
	{
		// Get the asset ID from the request
		$assetId = $this->input->getInt('asset_id', 0);

		// Make sure the asset ID is non-zero
		if (empty($assetId) || ($assetId <= 0))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Return the asset ID
		return $assetId;
	}

}