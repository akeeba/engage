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
use Akeeba\Engage\Site\Model\Comments as CommentsModel;
use Akeeba\Engage\Site\View\Comments\Html;
use Exception;
use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Controller\Mixin\PredefinedTaskList;
use FOF30\View\Exception\AccessForbidden;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Asset;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Uri\Uri;

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
		];

		parent::__construct($container, $config);

		$this->setPredefinedTaskList([
			'browse', 'submit', 'edit', 'save', 'publish', 'unpublish', 'remove', 'reportspam', 'reportham',
			'possiblespam',
		]);
	}

	/**
	 * Submit a new comment
	 *
	 * @throws BlatantSpam If the comment was reported to be blatant spam
	 * @throws Exception
	 */
	public function submit(): void
	{
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

		// Get the parent comment (default: common comments root)
		/** @var CommentsModel $model */
		$model  = $this->getModel();
		$parent = $model->tmpInstance()->getClone()->getRoot();

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
		$model->reset();
		$model->asset_id   = $assetId;
		$model->name       = $name;
		$model->email      = $email;
		$model->body       = Filter::filterText($comment);
		$model->enabled    = 1;
		$model->created_by = null;

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
			$model->insertAsChildOf($parent);
			$model->useCaptcha(false);
		}
		catch (Exception $e)
		{
			$this->setRedirect($returnUrl, $e->getMessage(), 'error');
			$this->redirect();

			return;
		}

		// The save succeeded. Clear the session data and redirect back to the asset being commented on.
		$platform->unsetSessionVar('name', $sessionNamespace);
		$platform->unsetSessionVar('email', $sessionNamespace);
		$platform->unsetSessionVar('comment', $sessionNamespace);

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
	 * Ensures that we are allowed to display a list of comments.
	 *
	 * @return  void
	 *
	 * @throws  AccessForbidden
	 */
	protected function onBeforeBrowse(): void
	{
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
		$this->addCommentFragmentToReturnURL();
	}

	/**
	 * Runs after unpublishing a comment. Adjusts the redirection with the unpublished comment's ID in the fragment.
	 */
	protected function onAfterUnpublish()
	{
		$this->addCommentFragmentToReturnURL();
	}

	protected function onBeforeEdit()
	{
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

		// Make sure the asset ID really exists
		/** @var Asset $assetTable */
		$assetTable = Table::getInstance('Asset', 'Joomla\\CMS\\Table\\');
		$loaded     = $assetTable->load($assetId);

		if (!$loaded)
		{
			throw new AccessForbidden();
		}

		// Make sure the asset is published and we have view access to it
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
}