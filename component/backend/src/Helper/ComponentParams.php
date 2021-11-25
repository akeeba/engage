<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;
use ReflectionClass;

class ComponentParams
{
	/**
	 * All possible ticket statuses and their description
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private static $ticketStatuses;

	/**
	 * Actually Save the params into the db
	 *
	 * @param   Registry     $params
	 * @param   string       $element
	 * @param   string       $type
	 * @param   string|null  $folder
	 * @param   bool         $throwException
	 *
	 * @since   3.0.0
	 */
	public static function save(Registry $params, string $element = 'com_engage', string $type = 'component', ?string $folder = null, bool $throwException = false): void
	{
		/** @var DatabaseDriver $db */
		$db   = JoomlaFactory::getContainer()->get('DatabaseDriver');
		$data = $params->toString('JSON');

		$sql = $db->getQuery(true)
			->update($db->qn('#__extensions'))
			->set($db->qn('params') . ' = ' . $db->q($data))
			->where($db->qn('element') . ' = :element')
			->where($db->qn('type') . ' = :type')
			->bind(':element', $element)
			->bind(':type', $type);

		if (!empty($folder))
		{
			$sql->where($db->quoteName('folder') . ' = :folder')
				->bind(':folder', $folder);
		}

		$db->setQuery($sql);

		try
		{
			$db->execute();

			// The extension parameters are cached. We just changed them. Therefore we MUST reset the system cache which holds them.
			CacheCleaner::clearCacheGroups(['_system'], [0, 1]);
		}
		catch (Exception $e)
		{
			// Don't sweat if it fails unless told otherwise
			if ($throwException)
			{
				throw $e;
			}
		}

		// Reset ComponentHelper's cache
		if ($type === 'component')
		{
			$refClass = new ReflectionClass(ComponentHelper::class);
			$refProp  = $refClass->getProperty('components');
			$refProp->setAccessible(true);
			$components                    = $refProp->getValue();
			$components[$element]->params = $params;
			$refProp->setValue($components);
		}
		elseif ($type === 'plugin')
		{
			$refClass = new ReflectionClass(PluginHelper::class);
			$refProp  = $refClass->getProperty('plugins');
			$refProp->setAccessible(true);
			$plugins = $refProp->getValue();

			foreach ($plugins as $plugin)
			{
				if ($plugin->type === $folder && $plugin->element == $element)
				{
					$plugin->params = $params->toString('JSON');
				}
			}

			$refProp->setValue($plugins);
		}
	}
}