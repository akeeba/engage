<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Controller;

defined('_JEXEC') or die();

use Akeeba\Component\Engage\Site\Exceptions\BlatantSpam;
use Akeeba\Component\Engage\Site\Helper\Filter;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Akeeba\Component\Engage\Site\Helper\SignedURL;
use Akeeba\Engage\Site\Model\Comments as CommentsModel;
use Akeeba\Engage\Site\View\Comments\Html;
use Exception;
use FOF40\Container\Container;
use FOF40\Controller\DataController;
use FOF40\Controller\Mixin\PredefinedTaskList;
use FOF40\JoomlaAbstraction\CacheCleaner;
use FOF40\Model\DataModel\Exception\RecordNotLoaded;
use FOF40\View\Exception\AccessForbidden;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use RuntimeException;

class Comments extends DataController
{
	use PredefinedTaskList;

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

		$assetId     = $this->getAssetId();
		$postInput   = $this->input->post;
		$parentId    = $postInput->getInt('parent_id', 0);
		$name        = $postInput->getString('name', null);
		$email       = $postInput->getString('email', null);
		$comment     = $postInput->get('comment', null, 'raw');
		$acceptedTos = $postInput->getBool('accept_tos', false);
		$returnUrl   = $this->getReturnUrl();
		$platform    = $this->container->platform;
		$user        = $platform->getUser();

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

		// Check if the user had to give explicit consent but didn't provide it
		if ($user->guest && $this->container->params->get('tos_accept') && !$acceptedTos)
		{
			$this->setRedirect($returnUrl, Text::_('COM_ENGAGE_COMMENTS_ERR_TOSACCEPT'), 'error');
			$this->redirect();

			return;
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
		if (!$user->authorise('core.manage', 'com_engage'))
		{
			$model->enabled = $this->container->params->get('default_publish', 1);
		}

		// If it's not a guest user we need to unset the name and email
		if (!$user->guest)
		{
			$model->name       = null;
			$model->email      = null;
			$model->created_by = $user->id;
		}

		// Try to save the comment, checking for CAPTCHA when necessary
		try
		{
			// Populates the IP address and User Agent, required for the spam check
			$model->useCaptcha(false);

			// This needs to be in the try-catch block in case a guest is using an existing user's email address.
			$model->check();

			// Spam check
			$platform->importPlugin('engage');
			$spamResults = $platform->runPlugins('onAkeebaEngageCheckSpam', [$model]);

			if (in_array(true, $spamResults, true))
			{
				$model->enabled = -3;
			}

			$model->useCaptcha(true);
			$model->setState('captcha', $this->input->get('captcha', '', 'raw'));
			$model->save();
			$model->useCaptcha(false);

			$result = $this->triggerEvent('onAfterSubmit', [$model]);
		}
		catch (Exception $e)
		{
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
}
