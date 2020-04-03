<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Controller;

defined('_JEXEC') or die();

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
		$parentId  = $this->input->post->getInt('parent', 0);
		$name      = $this->input->post->getString('name', null);
		$email     = $this->input->post->getString('email', null);
		$comment   = $this->input->post->getHtml('comment', null);
		$returnUrl = $this->getReturnUrl();
		$platform  = $this->container->platform;
		$user      = $platform->getUser();

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
		$parent = $model->getRoot()->getClone();

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
		$model->created_by = null;

		// If it's a guest user we need to unset the name and email
		if (!$user->guest)
		{
			$model->name       = null;
			$model->email      = null;
			$model->created_by = $user->id;
		}

		// Try to save the comment
		try
		{
			$model->insertAsChildOf($parent);
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

		// Get the asset_id and access level
		$assetId = $this->getAssetId();

		// Make sure the user is allowed to view this asset (this is information returned by the plugin)
		$access   = $this->input->getInt('access', 0);
		$platform = $this->container->platform;
		$user     = $platform->getUser();

		if (!in_array($access, $user->getAuthorisedViewLevels()))
		{
			throw new AccessForbidden();
		}

		// Pass the data to the view
		/** @var Html $view */
		$view             = $this->getView();
		$sessionNamespace = $this->container->componentName . '.' . $this->name;

		$view->assetId       = $assetId;
		$view->storedName    = $platform->getSessionVar('name', '', $sessionNamespace);
		$view->storedEmail   = $platform->getSessionVar('email', '', $sessionNamespace);
		$view->storedComment = $platform->getSessionVar('comment', '', $sessionNamespace);
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
}