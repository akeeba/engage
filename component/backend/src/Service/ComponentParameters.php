<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Service;


defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use ReflectionClass;

class ComponentParameters
{
	/**
	 * The Cache Cleaner service
	 *
	 * @since 3.2.0
	 * @var   CacheCleaner
	 */
	private CacheCleaner $cacheCleanerService;

	/**
	 * Default extension to save parameters to
	 *
	 * @since 3.2.0
	 * @var   string
	 */
	private string $defaultExtension;

	public function __construct(CacheCleaner $cacheCleanerService, string $defaultExtension)
	{
		$this->cacheCleanerService = $cacheCleanerService;
		$this->defaultExtension    = $defaultExtension;
	}

	public function save(Registry $params, ?string $extension = null): void
	{
		$criteria = $this->extensionNameToCriteria($extension ?? $this->defaultExtension);

		if (empty($criteria))
		{
			return;
		}

		/** @var DatabaseDriver $db */
		$db   = JoomlaFactory::getContainer()->get('DatabaseDriver');
		$data = $params->toString('JSON');

		$query = $db->getQuery(true)
		            ->update($db->qn('#__extensions'))
		            ->set($db->qn('params') . ' = ' . $db->q($data))
		            ->where($db->qn('element') . ' = :element')
		            ->where($db->qn('type') . ' = :type')
		            ->bind(':element', $criteria['element'], ParameterType::STRING)
		            ->bind(':type', $criteria['type'], ParameterType::STRING);

		if (isset($criteria['folder']) && !empty($criteria['folder']))
		{
			$query->where($db->quoteName('folder') . ' = :folder')
			      ->bind(':folder', $criteria['folder'], ParameterType::STRING);
		}

		$db->setQuery($query);

		try
		{
			$db->execute();

			/**
			 * The component parameters are cached. We just changed them. Therefore, we MUST reset the system
			 * cache which holds them.
			 */
			$this->cacheCleanerService->clearGroups(['_system']);
		}
		catch (\Exception $e)
		{
			// Don't sweat if it fails
		}

		// Reset ComponentHelper's cache
		if ($criteria['type'] === 'component')
		{
			$refClass = new ReflectionClass(ComponentHelper::class);
			$refProp  = $refClass->getProperty('components');
			$refProp->setAccessible(true);

			$components = $refProp->getValue();

			$components[$criteria['element']]->params = $params;

			$refProp->setValue($components);
		}
		elseif ($criteria['type'] === 'plugin')
		{
			$refClass = new ReflectionClass(PluginHelper::class);
			$refProp  = $refClass->getProperty('plugins');

			$refProp->setAccessible(true);

			$plugins = $refProp->getValue();

			foreach ($plugins as $plugin)
			{
				if ($plugin->type === $criteria['folder'] && $plugin->name === $criteria['element'])
				{
					$plugin->params = $params->toString('JSON');
				}
			}

			$refProp->setValue($plugins);
		}
	}

	/**
	 * Convert a Joomla extension name to `#__extensions` table query criteria.
	 *
	 * The following kinds of extensions are supported:
	 * * `pkg_something` Package type extension
	 * * `com_something` Component
	 * * `plg_folder_something` Plugins
	 * * `mod_something` Site modules
	 * * `amod_something` Administrator modules. THIS IS CUSTOM.
	 * * `file_something` File type extension
	 * * `lib_something` Library type extension
	 *
	 * @param   string  $extensionName
	 *
	 * @return  string[]
	 * @since   3.2.0
	 */
	private function extensionNameToCriteria(string $extensionName): array
	{
		$parts = explode('_', $extensionName, 3);

		switch ($parts[0])
		{
			case 'pkg':
				return [
					'type'    => 'package',
					'element' => $extensionName,
				];

			case 'com':
				return [
					'type'    => 'component',
					'element' => $extensionName,
				];

			case 'plg':
				return [
					'type'    => 'plugin',
					'folder'  => $parts[1],
					'element' => $parts[2],
				];

			case 'mod':
				return [
					'type'      => 'module',
					'element'   => $extensionName,
					'client_id' => 0,
				];

			// That's how we note admin modules
			case 'amod':
				return [
					'type'      => 'module',
					'element'   => substr($extensionName, 1),
					'client_id' => 1,
				];

			case 'file':
				return [
					'type'    => 'file',
					'element' => $extensionName,
				];

			case 'lib':
				return [
					'type'    => 'library',
					'element' => $parts[1],
				];
		}

		return [];
	}
}