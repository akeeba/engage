<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Controller;

defined('_JEXEC') or die;

use Exception;
use FOF30\Container\Container;
use FOF30\Controller\DataController;
use FOF30\Controller\Mixin\PredefinedTaskList;
use Joomla\CMS\Language\Text;

class Comments extends DataController
{
	use PredefinedTaskList;

	public function __construct(Container $container, array $config = [])
	{
		$config['taskPrivileges'] = [
			'reportspam'   => '@remove',
			'reportham'    => '@publish',
			'possiblespam' => '@publish',
		];

		parent::__construct($container, $config);

		$this->setPredefinedTaskList([
			'browse', 'edit', 'save', 'publish', 'unpublish', 'remove', 'reportspam', 'reportham',
			'possiblespam',
		]);
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

		$url = !empty($customURL) ? $customURL : 'index.php?option=com_engage&view=Comments';

		if (!$status)
		{
			$this->setRedirect($url, $error, 'error');
		}
		else
		{
			$this->setRedirect($url);
		}
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

		$url = !empty($customURL) ? $customURL : 'index.php?option=com_engage&view=Comments';

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