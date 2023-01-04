<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Provider;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Service\CacheCleaner as CacheCleanerService;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * Cache Cleaner service provider
 *
 * @since  3.2.0
 */
class CacheCleaner implements ServiceProviderInterface
{
	public function register(Container $container)
	{
		$container->set(
			CacheCleanerService::class,
			function (Container $container) {
				$app = Factory::getApplication();

				return new CacheCleanerService(
					$app,
					$container->get(CacheControllerFactoryInterface::class)
				);
			}
		);
	}

}