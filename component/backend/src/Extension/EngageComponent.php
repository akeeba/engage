<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Service\CacheCleaner;
use Akeeba\Component\Engage\Administrator\Service\ComponentParameters;
use Akeeba\Component\Engage\Administrator\Service\Html\Engage;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\DI\Container;
use Psr\Container\ContainerInterface;

class EngageComponent extends MVCComponent implements BootableExtensionInterface
{
	use HTMLRegistryAwareTrait;

	/**
	 * The container we were created with
	 *
	 * @since 3.2.0
	 * @var   Container
	 */
	private $container;

	/** @inheritdoc */
	public function boot(ContainerInterface $container)
	{
		$this->container = $container;
		$db              = $container->get('DatabaseDriver');
		$this->getRegistry()->register('engage', new Engage($db));
	}

	/**
	 * Get the Cache Cleaner service
	 *
	 * @return  CacheCleaner
	 *
	 * @since   3.2.0
	 */
	public function getCacheCleanerService(): CacheCleaner
	{
		return $this->container->get(CacheCleaner::class);
	}

	/**
	 * Get the Component Parameters service
	 *
	 * @return  ComponentParameters
	 *
	 * @since   3.2.0
	 */
	public function getComponentParametersService(): ComponentParameters
	{
		return $this->container->get(ComponentParameters::class);
	}

}