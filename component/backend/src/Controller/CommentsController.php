<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Controller\Mixin\ControllerEvents;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\RegisterControllerTasks;
use Akeeba\Component\Engage\Administrator\Controller\Mixin\ReturnURLAware;
use Akeeba\Component\Engage\Administrator\Model\CommentModel;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Joomla\Utilities\ArrayHelper;

class CommentsController extends AdminController
{
	use ControllerEvents;
	use RegisterControllerTasks;
	use ReturnURLAware;

	/** @inheritdoc */
	protected $text_prefix = 'COM_ENGAGE_COMMENTS';

	/**
	 * Constructor.
	 *
	 * @param   array                     $config   An optional associative array of configuration settings.
	 *                                              Recognized key values include 'name', 'default_task', 'model_path',
	 *                                              and 'view_path' (this list is not meant to be comprehensive).
	 * @param   MVCFactoryInterface|null  $factory  The factory.
	 * @param   CMSApplication|null       $app      The JApplication for the dispatcher
	 * @param   Input|null                $input    Input
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function __construct($config = [], MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
	{
		parent::__construct($config, $factory, $app, $input);

		$this->registerControllerTasks();
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
	public function getModel($name = 'Comment', $prefix = 'Administrator', $config = ['ignore_request' => true])
	{
		return parent::getModel($name, $prefix, $config);
	}

	/**
	 * Report a message as positively not spam and publish it.
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function reportham()
	{
		$this->reportMessage();
	}

	/**
	 * Report a message as positively spam and delete it.
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function reportspam()
	{
		$this->reportMessage();
	}

	/**
	 * Mark a message as possible spam (unpublish with state -3)
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function possiblespam(): void
	{
		// Check for request forgeries
		$this->checkToken();

		// Get items to publish from the request.
		$cid = $this->input->get('cid', [], 'array');

		if (empty($cid))
		{
			$this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), ['category' => 'jerror']);
		}
		else
		{
			// Get the model.
			/** @var CommentModel $model */
			$model = $this->getModel();

			// Make sure the item ids are integers
			$cid = ArrayHelper::toInteger($cid);

			// Mark the items.
			try
			{
				$model->publish($cid, -3);
				$errors = $model->getErrors();
				$ntext  = null;

				if ($errors)
				{
					Factory::getApplication()->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_MARKED_POSSIBLE_SPAM', \count($cid)), 'error');
				}
				else
				{
					$ntext = $this->text_prefix . '_N_ITEMS_FAILED_MARK_POSSIBLE_SPAM';
				}

				if (\count($cid))
				{
					$this->setMessage(Text::plural($ntext, \count($cid)));
				}
			}
			catch (\Exception $e)
			{
				$this->setMessage($e->getMessage(), 'error');
			}
		}

		$this->setRedirect(
			$this->getReturnUrl() ?: Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(), false
			)
		);
	}

	/**
	 * Report a message as ham or spam. The actual reporting is taken care of by the plugins.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function reportMessage(): void
	{
		// Check for request forgeries
		$this->checkToken();

		// Get items to publish from the request.
		$cid    = $this->input->get('cid', [], 'array');
		$data   = ['reportham' => false, 'reportspam' => true];
		$task   = $this->getTask();
		$asSpam = ArrayHelper::getValue($data, $task, 0, 'int');

		if (empty($cid))
		{
			$this->app->getLogger()->warning(Text::_($this->text_prefix . '_NO_ITEM_SELECTED'), ['category' => 'jerror']);
		}
		else
		{
			// Get the model.
			/** @var CommentModel $model */
			$model = $this->getModel();

			// Make sure the item ids are integers
			$cid = ArrayHelper::toInteger($cid);

			// Publish the items.
			try
			{
				$model->reportMessage($cid, $asSpam);
				$errors = $model->getErrors();
				$ntext  = null;

				if ($asSpam)
				{
					if ($errors)
					{
						Factory::getApplication()->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_FAILED_REPORT_SPAM', \count($cid)), 'error');
					}
					else
					{
						$ntext = $this->text_prefix . '_N_ITEMS_REPORTED_SPAM';
					}
				}
				else
				{
					if ($errors)
					{
						Factory::getApplication()->enqueueMessage(Text::plural($this->text_prefix . '_N_ITEMS_FAILED_REPORT_HAM', \count($cid)), 'error');
					}
					else
					{
						$ntext = $this->text_prefix . '_N_ITEMS_REPORTED_HAM';
					}

				}

				if (\count($cid))
				{
					$this->setMessage(Text::plural($ntext, \count($cid)));
				}
			}
			catch (\Exception $e)
			{
				$this->setMessage($e->getMessage(), 'error');
			}
		}

		$this->setRedirect(
			$this->getReturnUrl() ?: Route::_(
				'index.php?option=' . $this->option . '&view=' . $this->view_list
				. $this->getRedirectToListAppend(), false
			)
		);
	}

}