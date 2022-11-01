<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') || die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\Content\Engage\Extension\Engage;

return new class implements ServiceProviderInterface {
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   5.0.0
	 */
	public function register(Container $container)
	{
		/** @var \Joomla\CMS\Extension\MVCComponent $component */
		$container->registerServiceProvider(new MVCFactory('Akeeba\\Component\\Engage'));
		$container->registerServiceProvider(new ComponentDispatcherFactory('Akeeba\\Component\\Engage'));

		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$config               = (array) PluginHelper::getPlugin('content', 'engage');
				$subject              = $container->get(DispatcherInterface::class);
				$mvcFactory           = $container->get(MVCFactoryInterface::class);
				$comDispatcherFactory = $container->get(ComponentDispatcherFactoryInterface::class);

				$plugin = new Engage($subject, $config, $mvcFactory, $comDispatcherFactory);

				$plugin->setApplication(Factory::getApplication());
				$plugin->setDatabase($container->get('DatabaseDriver'));

				return $plugin;
			}
		);
	}
};
