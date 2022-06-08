<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
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
use Akeeba\Component\Engage\Site\Model\CommentsModel;
use Akeeba\Component\Engage\Site\View\Comments\HtmlView;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\MVC\View\ViewInterface;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseDriver;
use Joomla\Plugin\Content\Engage\Extension\Engage;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;

class CommentsController extends AdminCommentsController
{
	use FrontendCommentsAware;
	use GetRedirectionAware;
	use ReturnURLAware;
	use ReusableModels
	{
		ReusableModels::getModel as reusableGetModel;
		ReusableModels::getView as reusableGetView;
	}

	/**
	 * The default view for the display method.
	 *
	 * @var    string
	 * @since  3.0.0
	 */
	protected $default_view = 'comments';

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

	/**
	 * Default view display method
	 *
	 * @param   boolean  $cachable   If true, the view output will be cached
	 * @param   array    $urlparams  An array of safe url parameters and their variable types, for valid values see
	 *                               {@link InputFilter::clean()}.
	 *
	 * @return  static  A controller object to support chaining.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
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
			'akengage_cid'        => 'INT',
		], $urlparams);

		return parent::display($cachable, $urlparams);
	}

	/**
	 * Method to get a model object, loading it if required.
	 *
	 * @param   string  $name    The model name. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for model. Optional.
	 *
	 * @return  BaseDatabaseModel|boolean  Model object on success; otherwise false on failure.
	 *
	 * @since   3.0.0
	 */
	public function getModel($name = 'Comment', $prefix = 'Site', $config = ['ignore_request' => true])
	{
		return $this->reusableGetModel($name, $prefix, $config);
	}

	/**
	 * Method to get a reference to the current view and load it if necessary.
	 *
	 * @param   string  $name    The view name. Optional, defaults to the controller name.
	 * @param   string  $type    The view type. Optional.
	 * @param   string  $prefix  The class prefix. Optional.
	 * @param   array   $config  Configuration array for view. Optional.
	 *
	 * @return  ViewInterface  Reference to the view or an error.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function getView($name = '', $type = '', $prefix = '', $config = [])
	{
		return $this->reusableGetView($name, $type, $prefix, $config);
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

	/**
	 * Runs after deleting a comment.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function onAfterDelete(): void
	{
		$this->disableJoomlaCache();
		$this->applyReturnUrl();
		$this->cleanCache();
	}

	/**
	 * Runs after marking a comment as possibly spam.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
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
		$this->applyReturnUrl();
		$this->disableJoomlaCache();
		$this->addCommentFragmentToReturnURL();
		$this->cleanCache();
	}

	/**
	 * Runs after reporting a comment as non–spam.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function onAfterReportham()
	{
		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Runs after reporting a comment as spam and deleting it.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function onAfterReportspam()
	{
		$this->applyReturnUrl();
		$this->disableJoomlaCache();
		$this->cleanCache();
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
		$this->applyReturnUrl();
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
		$ordering     = $this->input->get('akengage_order', 'id');
		$orderDir     = strtoupper($this->input->get('akengage_order_Dir', 'DESC') ?: 'DESC');
		$orderDir     = in_array($orderDir, ['ASC', 'DESC']) ? $orderDir : 'DESC';

		/** @var CommentsModel $model */
		$model     = $this->getModel('Comments', 'Site', ['ignore_request' => true]);
		$formModel = $this->getModel('Comment', 'Site', ['ignore_request' => true]);
		$model->setState('list.start', $start);
		$model->setState('list.limit', $limit);
		$model->setState('list.ordering', $ordering);
		$model->setState('list.direction', $orderDir);

		// Get the asset_id and assert we have access to it
		$assetId = $this->getAssetId();
		$model->setState('filter.asset_id', $assetId);

		$cid = $this->input->getInt('akengage_cid', null);
		$limitstart = $this->input->getInt('akengage_limit', null);

		if ($cid > 0 && is_null($limitstart))
		{
			$commentIDs = ArrayHelper::toInteger(array_keys($model->commentIDTreeSliceWithDepth(0, 0) ?: []));
			$index      = array_search($cid, $commentIDs) ?: 0;
			$start      = intdiv($index, $limit) * $limit;

			$model->setState('list.start', $start);
			$this->app->setUserState('com_engage.comments.limitstart', $start);
			$this->input->set('akengage_limitstart', $start);
		}

		// Pass the data to the view
		/** @var HtmlView $view */
		$view = $this->getView();
		$view->setModel($model, true);
		$view->setModel($formModel);
		$view->assetId = $assetId;
	}

	/**
	 * Get the asset ID from the request.
	 *
	 * @return  int
	 *
	 * @throws  RuntimeException
	 * @since   1.0.0
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

	/**
	 * Get the default list limit configured by the site administrator
	 *
	 * @return  int
	 * @since   3.0.0
	 */
	private function getDefaultListLimit(): int
	{
		$defaultLimit = ComponentHelper::getParams('com_engage')->get('default_limit', 20);

		if ($defaultLimit >= 0)
		{
			return $defaultLimit;
		}

		// This should never happen. Fallback to all comments when the CMS Factory class does not exist.
		if (!class_exists(Factory::class))
		{
			return 0;
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