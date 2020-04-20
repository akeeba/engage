<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Controller;

defined('_JEXEC') or die();

use Akeeba\Engage\Admin\Model\Exception\BlatantSpam;
use Akeeba\Engage\Site\Helper\Filter;
use Akeeba\Engage\Site\Helper\Meta;
use Akeeba\Engage\Site\Helper\SignedURL;
use Akeeba\Engage\Site\Model\Comments as CommentsModel;
use Akeeba\Engage\Site\View\Comments\Html;
use Exception;
use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Controller\Mixin\PredefinedTaskList;
use FOF30\Model\DataModel\Exception\RecordNotLoaded;
use FOF30\Utils\CacheCleaner;
use FOF30\View\Exception\AccessForbidden;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

class Comments extends DataController
{
	use PredefinedTaskList;

	/** @inheritDoc */
	public function __construct(Container $container, array $config = [])
	{
		$config['taskPrivileges'] = [
			'submit'       => 'core.create',
			'reportspam'   => '@remove',
			'reportham'    => '@publish',
			'possiblespam' => '@publish',
			'unsubscribe'  => true,
		];

		parent::__construct($container, $config);

		$this->setPredefinedTaskList([
			'browse', 'submit', 'edit', 'save', 'publish', 'unpublish', 'remove', 'reportspam', 'reportham',
			'possiblespam', 'unsubscribe',
		]);

		$this->cacheParams = [
			'option'              => 'CMD',
			'view'                => 'CMD',
			'task'                => 'CMD',
			'format'              => 'CMD',
			'layout'              => 'CMD',
			'asset_id'            => 'INT',
			'akengage_limitstart' => 'INT',
			'akengage_start'      => 'INT',
		];
	}

	/**
	 * DEBUG: Trigger email sending
	 *
	 * Don't worry, this will NOT work on your sites. This code is only accesible when I add 'debug' to the
	 * setPredefinedTaskList array in the __construct method.
	 */
	public function debug()
	{
		$this->disableJoomlaCache();

		$comment = $this->getModel();
		$comment->load($this->input->getInt('comment_id'));

		$this->container->platform->importPlugin('engage');
		$this->container->platform->importPlugin('content');
		$this->container->platform->runPlugins('onComEngageModelCommentsAfterCreate', [$comment]);

		echo "OK";

		$this->container->platform->closeApplication();
	}

	/**
	 * Submit a new comment
	 *
	 * @throws BlatantSpam If the comment was reported to be blatant spam
	 * @throws Exception
	 */
	public function submit(): void
	{
		$this->disableJoomlaCache();

		// CSRF prevention
		$this->csrfProtection();

		$assetId   = $this->getAssetId();
		$parentId  = $this->input->post->getInt('parent_id', 0);
		$name      = $this->input->post->getString('name', null);
		$email     = $this->input->post->getString('email', null);
		$comment   = $this->input->post->getHtml('comment', null);
		$returnUrl = $this->getReturnUrl();
		$platform  = $this->container->platform;
		$user      = $platform->getUser();

		// If the comments are disabled for this asset we will return a Not Authorized error
		if (Meta::areCommentsClosed($assetId))
		{
			throw new AccessForbidden();
		}

		// Store the comment parameters in the session
		$sessionNamespace = $this->container->componentName . '.' . $this->name;

		$platform->setSessionVar('name', $name, $sessionNamespace);
		$platform->setSessionVar('email', $email, $sessionNamespace);
		$platform->setSessionVar('comment', $comment, $sessionNamespace);

		// Make sure we have either a user or a name and email
		if ($user->guest && (empty($name) || empty($email)))
		{
			$this->setRedirect($returnUrl, Text::_('COM_ENGAGE_COMMENTS_ERR_NAME_AND_EMAIL_REQUIRED'), 'error');
			$this->redirect();

			return;
		}

		// Make sure we have a comment
		if (empty($comment))
		{
			$this->setRedirect($returnUrl, Text::_('COM_ENGAGE_COMMENTS_ERR_COMMENT_REQUIRED'), 'error');
			$this->redirect();

			return;
		}

		/** @var CommentsModel $model */
		$model = $this->getModel();

		// If it's a reply to another comment let's make sure it exists and for the correct asset ID
		if ($parentId !== 0)
		{
			// A non-zero parent ID was provided. Try to load the comment.
			$parent = $model->tmpInstance()->getClone();

			if (!$parent->load($parentId))
			{
				throw new AccessForbidden();
			}

			// Make sure the parent belongs to the same asset ID we're trying to comment on.
			if ($parent->asset_id != $assetId)
			{
				throw new AccessForbidden();
			}
		}

		// Set up the new comment
		$model->reset()->bind([
			'asset_id'   => $assetId,
			'name'       => $name,
			'email'      => $email,
			'body'       => Filter::filterText($comment),
			'enabled'    => 1,
			'created_by' => null,
			'parent_id'  => ($parentId == 0) ? null : $parentId,
		]);

		// Non-admin users may have their comments auto-unpublished by default
		if (!$user->get('core.manage', 'com_engage'))
		{
			$model->created_by = $this->container->params->get('default_publish', 1);
		}

		// If it's a guest user we need to unset the name and email
		if (!$user->guest)
		{
			$model->name       = null;
			$model->email      = null;
			$model->created_by = $user->id;
		}

		// Populates the IP address and User Agent, required for the spam check
		$model->useCaptcha(false);
		$model->check();

		// Spam check
		$platform->importPlugin('engage');
		$spamResults = $platform->runPlugins('onAkeebaEngageCheckSpam', [$model]);

		if (in_array(true, $spamResults, true))
		{
			$model->enabled = -3;
		}

		// Try to save the comment, checking for CAPTCHA when necessary
		try
		{
			$model->useCaptcha(true);
			$model->setState('captcha', $this->input->get('captcha', '', 'raw'));
			$model->save();
			$model->useCaptcha(false);
		}
		catch (Exception $e)
		{
			throw $e;
			$this->setRedirect($returnUrl, $e->getMessage(), 'error');
			$this->redirect();

			return;
		}

		$this->cleanCache();

		// Clear the session data and redirect back to the asset being commented on.
		$platform->unsetSessionVar('name', $sessionNamespace);
		$platform->unsetSessionVar('email', $sessionNamespace);
		$platform->unsetSessionVar('comment', $sessionNamespace);

		// If the user was unsubscribed from comments we need to resubscribe them
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->delete($db->qn('#__engage_unsubscribe'))
			->where($db->qn('asset_id') . ' = ' . $db->q($model->asset_id))
			->where($db->qn('email') . ' = ' . $db->q($model->getUser()->email));
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Ignore any failures, they are not important.
		}

		$this->setRedirect($returnUrl, Text::_('COM_ENGAGE_COMMENTS_MSG_SUCCESS'));
	}

	/**
	 * Report a message as spam and delete it
	 *
	 * It is up to the plugins to make a sensible report of spam to a remote service.
	 *
	 * @throws  Exception
	 */
	public function reportspam(): void
	{
		$this->reportMessage(true);
	}

	/**
	 * Report a message as ham (non-spam mistakenly recognized as such) and pubish it
	 *
	 * It is up to the plugins to make a sensible report of ham to a remote service.
	 *
	 * @throws  Exception
	 */
	public function reportham(): void
	{
		$this->reportMessage(false);

		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Mark a message as possible spam (unpublish with state -3)
	 *
	 * @throws Exception
	 */
	public function possiblespam(): void
	{
		$this->disableJoomlaCache();

		// CSRF prevention
		$this->csrfProtection();

		$model = $this->getModel()->savestate(false);
		$ids   = $this->getIDsFromRequest($model, false);
		$error = false;

		try
		{
			$status = true;

			foreach ($ids as $id)
			{
				$model->find($id);

				$userId = $this->container->platform->getUser()->id;

				if ($model->isLocked($userId))
				{
					$model->checkIn($userId);
				}

				$model->publish(-3);
			}
		}
		catch (Exception $e)
		{
			$status = false;
			$error  = $e->getMessage();
		}

		$this->cleanCache();

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$url = !empty($customURL) ? $customURL : 'index.php';

		if (!$status)
		{
			$this->setRedirect($url, $error, 'error');
		}
		else
		{
			$this->setRedirect($url);
		}

		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Unsubscribes a user from notifications regarding a specific content item's comments.
	 *
	 * @return  void
	 */
	public function unsubscribe()
	{
		$this->disableJoomlaCache();

		// I need at least a comment ID and an email to unsubscribe
		$id               = $this->input->getInt('id', 0);
		$unsubscribeEmail = $this->input->getString('email', '');

		if (($id <= 0) || empty($unsubscribeEmail))
		{
			throw new AccessForbidden();
		}

		// Try to load the comment
		/** @var CommentsModel $comment */
		$comment = $this->getModel()->tmpInstance();

		try
		{
			$comment->findOrFail($id);
		}
		catch (Exception $e)
		{
			throw new AccessForbidden();
		}

		// Validate the token
		$this->input->set('asset_id', $comment->asset_id);
		$this->csrfProtection();

		// Try to unsubscribe -- if already unsubscribed redirect back with an error
		$o  = (object) [
			'asset_id' => $comment->asset_id,
			'email'    => $unsubscribeEmail,
		];
		$db = $this->container->db;

		try
		{
			if (!$db->insertObject('#__engage_unsubscribe', $o))
			{
				throw new RuntimeException('Already unsubscribed');
			}

			$message = Text::sprintf('COM_ENGAGE_COMMENTS_LBL_UNSUBSCRIBED', $unsubscribeEmail);
			$msgType = 'info';
		}
		catch (Exception $e)
		{
			$message = Text::_('COM_ENGAGE_COMMENTS_ERR_ALREADY_UNSUBSCRIBED');
			$msgType = 'error';
		}

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$url = !empty($customURL) ? $customURL : 'index.php';

		$this->setRedirect($url, $message, $msgType);
	}

	/**
	 * Ensures that we are allowed to display a list of comments.
	 *
	 * @return  void
	 *
	 * @throws  AccessForbidden
	 */
	protected function onBeforeBrowse(): void
	{
		/**
		 * If the current user has the core.edit.own privilege we need to cache per user ID instead of per user group.
		 * The idea is that each user in the group that gives the core.edit.own privilege will see an edit button on
		 * DIFFERENT comments than the other.
		 *
		 * In all other cases we need to cache by user group. All users with the same user group combination will be
		 * seeing the exact same comments display at all times.
		 */
		$this->userCaching = $this->container->platform->getUser()->authorise('core.edit.own', 'com_engage') ? 2 : 1;

		// Make sure we are allowed to show this page (the content plugin explicitly told us to render it).
		if (!isset($this->container['commentsBrowseEnablingFlag']) || !$this->container['commentsBrowseEnablingFlag'])
		{
			throw new AccessForbidden();
		}

		// Get the asset_id and assert we have access to it
		$assetId = $this->getAssetId();

		// Pass the data to the view
		/** @var Html $view */
		$view             = $this->getView();
		$sessionNamespace = $this->container->componentName . '.' . $this->name;
		$platform         = $this->container->platform;

		$view->assetId       = $assetId;
		$view->storedName    = $platform->getSessionVar('name', '', $sessionNamespace);
		$view->storedEmail   = $platform->getSessionVar('email', '', $sessionNamespace);
		$view->storedComment = $platform->getSessionVar('comment', '', $sessionNamespace);
	}

	/**
	 * Asserts that the user has view access to a published asset. Throws a RuntimeException otherwise.
	 *
	 * @param   int  $assetId
	 *
	 * @return  void
	 *
	 * @throws  AccessForbidden
	 */
	protected function assertAssetAccess(?int $assetId): void
	{
		// Get the asset access metadata
		$assetMeta = Meta::getAssetAccessMeta($assetId);

		// Make sure the associated asset is published
		if (!$assetMeta['published'])
		{
			throw new AccessForbidden();
		}

		// Make sure the user is allowed to view this asset and its parent
		$access       = $assetMeta['access'];
		$parentAccess = $assetMeta['parent_access'];
		$platform     = $this->container->platform;
		$user         = $platform->getUser();

		if (!is_null($access) && !in_array($access, $user->getAuthorisedViewLevels()))
		{
			throw new AccessForbidden();
		}

		if (!is_null($parentAccess) && !in_array($parentAccess, $user->getAuthorisedViewLevels()))
		{
			throw new AccessForbidden();
		}
	}

	/**
	 * Runs after publishing a comment. Adjusts the redirection with the published comment's ID in the fragment.
	 */
	protected function onAfterPublish()
	{
		$this->disableJoomlaCache();
		$this->addCommentFragmentToReturnURL();

		$this->cleanCache();
	}

	/**
	 * Runs after unpublishing a comment. Adjusts the redirection with the unpublished comment's ID in the fragment.
	 */
	protected function onAfterUnpublish()
	{
		$this->disableJoomlaCache();
		$this->addCommentFragmentToReturnURL();

		$this->cleanCache();
	}

	protected function onBeforeEdit()
	{
		$this->disableJoomlaCache();

		$view = $this->getView();

		$view->returnURL = '';

		$redirectURL = $this->input->getBase64('returnurl');
		$redirectURL = @base64_decode($redirectURL);

		if (empty($redirectURL))
		{
			return;
		}

		$view->returnURL = $redirectURL;
	}

	protected function onAfterApplySave(&$data, $id)
	{
		$this->cleanCache();
	}

	/** @inheritDoc */
	protected function csrfProtection()
	{
		// First, let's try token validation
		try
		{
			// If I don't have a token fall through to FOF's anti-CSRF protection
			$token = $this->input->getString('token');

			if (empty($token))
			{
				throw new RuntimeException('', 0xDEADBEEF);
			}

			// Do I have a comment ID? Otherwise fall through to FOF's anti-CSRF protection.
			/** @var CommentsModel $model */
			$model = $this->getModel()->tmpInstance();
			$ids   = $this->getIDsFromRequest($model);

			if (empty($ids))
			{
				throw new RuntimeException('', 0xDEADBEEF);
			}

			$id = array_shift($ids);

			if (empty($id))
			{
				throw new RuntimeException('', 0xDEADBEEF);
			}

			// Load the comment or fall through to FOF's anti-CSRF protection.
			$model->findOrFail($id);

			// If the token is valid we can return true
			$task     = $this->input->getCmd('task');
			$email    = $this->input->getString('email');
			$asset_id = $model->asset_id;
			$expires  = $this->input->getInt('expires');

			if (SignedURL::verifyToken($token, $task, $email, $asset_id, $expires))
			{
				return true;
			}
		}
		catch (RecordNotLoaded $e)
		{
			// This is raised if the comment ID is invalid. Ignore and fall through to the regular CSRF protection.
		}
		catch (RuntimeException $e)
		{
			// If it's not a "fall-through" exception we need to throw it back.
			if ($e->getCode() != 0xDEADBEEF)
			{
				throw $e;
			}
		}

		return parent::csrfProtection();
	}

	/**
	 * Get the asset ID from the request and verify it is real
	 *
	 * @return  int
	 *
	 * @throws  AccessForbidden
	 */
	private function getAssetId(): int
	{
		// Get the asset ID from the request
		$assetId = $this->input->getInt('asset_id', 0);

		// Make sure the asset ID is non-zero
		if (empty($assetId) || ($assetId <= 0))
		{
			throw new AccessForbidden();
		}

		// Make sure the asset exists, is published and we have view access to it
		$this->assertAssetAccess($assetId);

		// Return the asset ID
		return $assetId;
	}

	/**
	 * Get the decoded return URL
	 *
	 * @return  string
	 */
	private function getReturnUrl(): string
	{
		$returnUrl = $this->input->post->getBase64('returnurl', null);

		if (!empty($returnUrl))
		{
			$returnUrl = @base64_decode($returnUrl);

			if ($returnUrl === false)
			{
				$returnUrl = null;
			}
		}

		$returnUrl = $returnUrl ?? 'index.php';

		if (!Uri::isInternal($returnUrl))
		{
			$returnUrl = 'index.php';
		}

		return $returnUrl;
	}

	/**
	 * Adds the comment's ID to the fragment of the redirection.
	 *
	 * This only happens if there is no fragment yet and the ID of the item being edited / published / whatever is not
	 * zero.
	 *
	 * This method directly modifies $this->redirect.
	 *
	 * @return  void
	 */
	private function addCommentFragmentToReturnURL(): void
	{
		if (empty($this->redirect))
		{
			return;
		}

		$uri = new Uri($this->redirect);

		if (!empty($uri->getFragment()))
		{
			return;
		}

		$id = $this->input->getInt('id', 0);

		if ($id <= 0)
		{
			return;
		}

		$uri->setFragment('akengage-comment-' . $id);

		$this->redirect = $uri->toString();
	}

	/**
	 * Report a message as ham or spam. The actual reporting is taken care of by the plugins.
	 *
	 * @param   bool  $asSpam  True to report as spam, false to report as ham.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	private function reportMessage(bool $asSpam = true): void
	{
		$this->disableJoomlaCache();

		$this->csrfProtection();

		$model    = $this->getModel()->savestate(false);
		$ids      = $this->getIDsFromRequest($model, false);
		$error    = null;
		$platform = $this->container->platform;

		$platform->importPlugin('engage');

		try
		{
			foreach ($ids as $id)
			{
				$event = $asSpam ? 'onAkeebaEngageReportSpam' : 'onAkeebaEngageReportHam';

				$model->find($id);

				$platform->runPlugins($event, [$model]);

				// If reporting ham also publish the comment
				if (!$asSpam)
				{
					$model->publish();
				}
				// If reporting as positively spam also delete the comment
				else
				{
					$model->delete();
				}
			}
		}
		catch (Exception $e)
		{
			$error = $e->getMessage();
		}

		// Clean cached comments display
		$this->cleanCache();

		// Redirect
		if ($customURL = $this->input->getBase64('returnurl', ''))
		{
			$customURL = base64_decode($customURL);
		}

		$url = !empty($customURL) ? $customURL : 'index.php';

		if (!empty($error))
		{
			$this->setRedirect($url, $error, 'error');
		}
		else
		{
			$message = $asSpam ? 'COM_ENGAGE_COMMENTS_REPORTED_SPAM' : 'COM_ENGAGE_COMMENTS_REPORTED_HAM';

			$this->setRedirect($url, Text::_($message));
		}
	}

	/**
	 * Disables the Joomla cache for this response.
	 *
	 * @return  void
	 * @see     \Joomla\CMS\MVC\Controller\FormController::edit()
	 */
	private function disableJoomlaCache(): void
	{
		try
		{
			/** @var CMSApplication $app */
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return;
		}

		if (!method_exists($app, 'allowCache'))
		{
			return;
		}

		$app->allowCache(false);
	}

	/**
	 * Clear the Joomla cache for Akeeba Engage
	 *
	 * @return  void
	 */
	private function cleanCache(): void
	{
		CacheCleaner::clearCacheGroups([
			'com_content',
			'com_engage',
		], [0]);
	}
}