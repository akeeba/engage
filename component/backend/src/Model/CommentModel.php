<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Model;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Mixin\ModelGetItemTrait;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;

/**
 * Backend comment edit / delete model
 *
 * @since 3.0.0
 */
#[\AllowDynamicProperties]
class CommentModel extends AdminModel
{
	use ModelGetItemTrait;

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
	 * Report a message as ham or spam. The actual reporting is taken care of by the plugins.
	 *
	 * @param   bool  $asSpam  True to report as spam, false to report as ham.
	 *
	 * @return  bool
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function reportMessage(array &$pks, bool $asSpam = true): bool
	{
		/** @var CommentTable $table */
		$pks     = ArrayHelper::toInteger($pks);
		$table   = $this->getTable();
		$context = $this->option . '.' . $this->name;

		PluginHelper::importPlugin('engage');

		try
		{
			$app = Factory::getApplication();

			foreach ($pks as $i => $id)
			{
				if (!$table->load($id))
				{
					$this->setError($table->getError());

					return false;
				}

				$event = $asSpam ? 'onAkeebaEngageReportSpam' : 'onAkeebaEngageReportHam';
				$allowed = $asSpam ? $this->canDelete($table) : $this->canEditState($table);

				if (!$allowed)
				{
					// Prune items that you can't change.
					unset($pks[$i]);
					$error = $this->getError();

					if ($error)
					{
						Log::add($error, Log::WARNING, 'jerror');
					}
					else
					{
						Log::add(Text::_($asSpam ? 'JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED' : 'JLIB_APPLICATION_ERROR_EDITSTATE_NOT_PERMITTED'), Log::WARNING, 'jerror');
					}

					$this->cleanCache();

					return false;
				}

				// Trigger the before change state / before delete event.
				if (!$asSpam)
				{
					$result = Factory::getApplication()->triggerEvent($this->event_before_change_state, array($context, [$id], 1));
				}
				else
				{
					$result = Factory::getApplication()->triggerEvent($this->event_before_delete, array($context, $table));
				}

				if (\in_array(false, $result, true))
				{
					$this->setError($table->getError());

					return false;
				}

				// Trigger the event
				$app->triggerEvent($event, [$table]);

				// Publish or delete the comment, depending on whether it's being reported as ham or spam
				if (!$asSpam)
				{
					$table->publish();
				}
				else
				{
					$table->delete();
				}
			}
		}
		catch (Exception $e)
		{
			// Clear the component's cache
			$this->cleanCache();

			// Report the error
			$this->setError($e->getMessage());

			return false;
		}

		// Clear the component's cache
		$this->cleanCache();

		return true;
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
		$date  = clone Factory::getDate();
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