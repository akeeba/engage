<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Provider;

defined('_JEXEC') or die;

use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

class ComponentParameters implements ServiceProviderInterface
{
	private string $defaultExtension;

	public function __construct(string $defaultExtension)
	{
		$this->defaultExtension = $defaultExtension;
	}

	public function register(Container $container)
	{
		$container->set(
			\Akeeba\Component\Engage\Administrator\Service\ComponentParameters::class,
			function (Container $container) {
				return new \Akeeba\Component\Engage\Administrator\Service\ComponentParameters(
					$container->get(\Akeeba\Component\Engage\Administrator\Service\CacheCleaner::class),
					$this->defaultExtension
				);
			}
		);
	}
}