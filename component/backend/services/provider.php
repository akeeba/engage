<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Akeeba\Component\Engage\Administrator\Extension\EngageComponent;
use Akeeba\Component\Engage\Administrator\Provider\CacheCleaner as CacheCleanerProvider;
use Akeeba\Component\Engage\Administrator\Provider\ComponentParameters as ComponentParametersProvider;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 */
	public function register(Container $container)
	{
		// Include the Composer autoloader
		require_once __DIR__ . '/../vendor/autoload.php';

		// Get Joomla services
		$container->registerServiceProvider(new MVCFactory('Akeeba\\Component\\Engage'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('Akeeba\\Component\\Engage'));
		$container->registerServiceProvider(new CacheCleanerProvider());
		$container->registerServiceProvider(new ComponentParametersProvider('com_engage'));

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new EngageComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
				$component->setMVCFactory($container->get(MVCFactoryInterface::class));

				return $component;
			}
		);
	}
};
