<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Controller\CommentController as AdminCommentController;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\GetRedirectionAware;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\ReturnURLAware;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\ReusableModels;
use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use RuntimeException;

class CommentController extends AdminCommentController
{
	use FrontendCommentsAware;
	use GetRedirectionAware;
	use ReturnURLAware;
	use ReusableModels;

	public function batch($model)
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	public function editAssociations()
	{
		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
	}

	/**
	 * Method to check if you can edit an existing record.
	 *
	 * @param   array   $data  An array of input data.
	 * @param   string  $key   The name of the key for the primary key; default is id.
	 *
	 * @return  boolean
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function allowEdit($data = [], $key = 'id')
	{
		if (parent::allowEdit($data, $key))
		{
			return true;
		}

		/**
		 * If I am here the user does not have the core.edit permissions. I will check if the user has core.edit.own and
		 * the referenced comment belongs to the user.
		 */
		$user = UserFetcher::getUser();

		if ($user->guest || !$user->authorise('core.edit.own', 'com_engage'))
		{
			return false;
		}

		/** @var CommentTable $table */
		$table = $this->getModel()->getTable('Comment', 'Administrator');
		$id    = $data[$key] ?? null;

		if (empty($id) || !$table->load($id))
		{
			return false;
		}

		return $table->created_by == $user->id;
	}

	protected function onAfterApply()
	{
		$this->applyReturnUrl();

		$this->cleanCache();
	}

	protected function onAfterSave()
	{
		$this->applyReturnUrl();

		$this->cleanCache();
	}

	protected function onBeforeMain()
	{
		$this->disableJoomlaCache();
		$this->getView()->returnUrl = $this->getRedirection() ?: $this->getReturnUrl() ?: '';
	}

	protected function postSaveHook(BaseDatabaseModel $model, $validData = [])
	{
		// If the user was unsubscribed from comments we need to resubscribe them
		$user = UserFetcher::getUser();
		$model->resubscribeUser((int) ($validData['asset_id'] ?? 0), $user, $validData['email'] ?? null);

		$this->setMessage(Text::_('COM_ENGAGE_COMMENTS_MSG_SUCCESS'), 'success');

		parent::postSaveHook($model, $validData);
	}

}