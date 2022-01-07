<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// no direct access
defined('_JEXEC') or die();

use Akeeba\Component\Engage\Administrator\Helper\TemplateEmails;
use Akeeba\Component\Engage\Administrator\Model\UpgradeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\PackageAdapter;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseDriver;

/**
 * Akeeba Engage package extension installation script file.
 *
 * @see https://docs.joomla.org/Manifest_files#Script_file
 * @see \Akeeba\Component\Engage\Administrator\Model\UpgradeModel
 */
class Pkg_EngageInstallerScript
{
	/**
	 * Called after any type of installation / uninstallation action.
	 *
	 * @param   string          $type    Which action is happening (install|uninstall|discover_install|update)
	 * @param   PackageAdapter  $parent  The object responsible for running this script
	 *
	 * @return  bool
	 * @since   7.0.0
	 */
	public function postflight(string $type, PackageAdapter $parent): bool
	{
		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		$model = $this->getUpgradeModel();

		if (!empty($model))
		{
			try
			{
				if (!$model->postflight($type, $parent))
				{
					return false;
				}
			}
			catch (Exception $e)
			{
				return false;
			}
		}

		$this->updateEmails();

		if ($type === 'update')
		{
			$this->addUnsubscribeKey();
		}

		return true;
	}

	public function preflight(string $type, PackageAdapter $parent): bool
	{
		// Do not run on uninstall.
		if ($type === 'uninstall')
		{
			return true;
		}

		// Prevent users from installing this on Joomla 3
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			$msg = "<p>This version of Akeeba Engage cannot run on Joomla 3. Please download and install Akeeba Engage 2 instead. Kindly note that our site's Downloads page clearly indicates which version of our software is compatible with Joomla 3 and which version is compatible with Joomla 4.</p>";

			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}

		if ($type === 'update')
		{
			$this->dropUnsubscribeKey();
		}

		return true;
	}

	private function addUnsubscribeKey()
	{
		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = "ALTER TABLE `#__engage_unsubscribe` ADD PRIMARY KEY (`asset_id`,`email`(100))";
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// We expect this to fail if updating from a version beyond 3.0.0
		}
	}

	private function dropUnsubscribeKey()
	{
		/** @var DatabaseDriver $db */
		$db    = Factory::getContainer()->get('DatabaseDriver');
		$query = "ALTER TABLE `#__engage_unsubscribe` DROP KEY `#__engage_unsubscribe_unique`";
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// We expect this to fail if updating from a version beyond 3.0.0
		}
	}

	/**
	 * Get the UpgradeModel of the installed component
	 *
	 * @return  UpgradeModel|null  The upgrade Model. NULL if it cannot be loaded.
	 * @since   7.0.0
	 */
	private function getUpgradeModel(): ?UpgradeModel
	{
		// Make sure the latest version of the Model file will be loaded, regardless of the OPcache state.
		$filePath = JPATH_ADMINISTRATOR . '/components/com_engage/src/Model/UpgradeModel.php';

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($filePath);
		}

		// Can I please load the model?
		if (!class_exists('\Akeeba\Component\Engage\Administrator\Model\UpgradeModel'))
		{
			if (!file_exists($filePath) || !is_readable($filePath))
			{
				return null;
			}

			include_once $filePath;
		}

		if (!class_exists('\Akeeba\Component\Engage\Administrator\Model\UpgradeModel'))
		{
			return null;
		}

		try
		{
			return new UpgradeModel();
		}
		catch (Throwable $e)
		{
			return null;
		}
	}

	private function updateEmails(): void
	{
		// Make sure the latest version of the Helper file will be loaded, regardless of the OPcache state.
		$filePath = JPATH_ADMINISTRATOR . '/components/com_engage/src/Helper/TemplateEmails.php';

		if (function_exists('opcache_invalidate'))
		{
			opcache_invalidate($filePath);
		}

		if (!class_exists('\Akeeba\Component\Engage\Administrator\Helper\TemplateEmails'))
		{
			if (!file_exists($filePath) || !is_readable($filePath))
			{
				return;
			}

			include_once $filePath;
		}

		if (!class_exists('\Akeeba\Component\Engage\Administrator\Helper\TemplateEmails'))
		{
			return;
		}

		try
		{
			TemplateEmails::updateAllTemplates();
		}
		catch (Exception $e)
		{
		}
	}
}
