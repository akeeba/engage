<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Controller;

defined('_JEXEC') or die();

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
		parent::__construct($container, $config);

		$this->setPredefinedTaskList([
			'browse', 'submit', 'edit', 'save', 'cancel', 'publish', 'unpublish', 'remove',
		]);
	}

	/**
	 * Submit a new comment
	 *
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
		$model->body       = $comment;
		$model->enabled    = 1;
		$model->created_by = null;

		// If it's a guest user we need to unset the name and email
		if (!$user->guest)
		{
			$model->name       = null;
			$model->email      = null;
			$model->created_by = $user->id;
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

	protected function onAfterPublish()
	{
		$this->addCommentFragmentToReturnURL();
	}

	protected function onAfterUnpublish()
	{
		$this->addCommentFragmentToReturnURL();
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
}