<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Model\Mixin\GetItemAware;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use RuntimeException;

/**
 * Backend comment edit / delete model
 *
 * @since 3.0.0
 */
class CommentModel extends AdminModel
{
	use GetItemAware;

	/**
	 * Method for getting a form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  Form|bool
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 *
	 */
	public function getForm($data = [], $loadData = true)
	{
		$id = $data['id'] ?? null;

		// We do not allow adding new comments in the backend
		if (empty($id))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		$controlName = ($data['_control_name'] ?? null) ?: 'jform';
		$form        = $this->loadForm(
			'com_engage.comment',
			'comment',
			[
				'control'   => $controlName,
				'load_data' => $loadData,
			]) ?: false;

		if (empty($form))
		{
			return false;
		}

		// Remove the Enabled field if the current user is not allowed to edit the record state
		if (!$this->canEditState((object) $data))
		{
			$form->setFieldAttribute('enabled', 'disabled', 'true');
			$form->setFieldAttribute('enabled', 'required', 'false');
			$form->setFieldAttribute('enabled', 'filter', 'unset');
		}

		return $form;
	}

	/**
	 * Method to get the data that should be injected in the form.
	 *
	 * @return  array  The default data is an empty array.
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function loadFormData()
	{
		/** @var CMSApplication $app */
		$app         = Factory::getApplication();
		$data        = $app->getUserState('com_engage.edit.comment.data', []);
		$noSavedData = empty($data);

		if ($noSavedData)
		{
			$data             = $this->getItemTable();
			$data->created_by = $data->created_by ?? (UserFetcher::getUser() ?? new User())->id;
		}

		// Make sure data is an array
		$data = is_object($data) ? $data->getProperties() : $data;

		$this->preprocessData('com_engage.comment', $data);

		return $data;

	}

	/**
	 * Prepare and sanitise the table data prior to saving.
	 *
	 * @param   Table  $table  A reference to a Table object.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	protected function prepareTable($table)
	{
		// Set up the created / modified date
		$date  = Factory::getDate();
		$user  = UserFetcher::getUser() ?? new User();
		$isNew = empty($table->getId());

		if ($isNew)
		{
			$table->created    = $date->toSql();
			$table->created_by = $user->id;

			return;
		}

		$table->modified    = $date->toSql();
		$table->modified_by = $user->id;
	}


}