<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

/**
 * The Akeeba Engage latest comments module service provider.
 *
 * @since  3.0.9
 */
return new class implements ServiceProviderInterface
{
	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   3.0.9
	 */
	public function register(Container $container)
	{
		$container->registerServiceProvider(new ModuleDispatcherFactory('\\Joomla\\Module\\EngageLatest'));
		$container->registerServiceProvider(new HelperFactory('\\Joomla\\Module\\EngageLatest\\Site\\Helper'));

		$container->registerServiceProvider(new Module());
	}
};
