<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @package     Akeeba\Component\Engage\Administrator\Mixin
 * @subpackage
 *
 * @copyright   A copyright
 * @license     A "Slug" license name e.g. GPL2
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Event\CoreEventAware;
use Joomla\CMS\Factory;
use Joomla\Event\DispatcherAwareInterface;

/**
 * A trait to easily run plugin events.
 *
 * @since 9.4.0
 */
trait RunPluginsTrait
{
	use CoreEventAware;

	/**
	 * Execute a plugin event and return its results
	 *
	 * @param   string       $event      The event name
	 * @param   array        $arguments  The event arguments
	 * @param   string|null  $className  The concrete event's class name; null to have Joomla auto-detect it.
	 *
	 * @return  array
	 *
	 * @throws  \Exception
	 * @since   9.4.0
	 */
	protected function triggerPluginEvent(string $event, array $arguments, ?string $className = null): array
	{
		// Call the Joomla! plugins
		$dispatcher  = $this instanceof DispatcherAwareInterface ? $this->getDispatcher() : null;

		if (is_null($dispatcher))
		{
			if (method_exists($this, 'getApplication'))
			{
				$app = $this->getApplication();
			}
			elseif (property_exists($this, 'app') && $this->app instanceof CMSApplication)
			{
				$app = $this->app;
			}
			else
			{
				$app = Factory::getApplication();
			}

			$dispatcher = $app->getDispatcher();
		}

		$className   = $className ?: self::getEventClassByEventName($event);
		$eventObject = new $className($event, $arguments);
		$eventResult = $dispatcher->dispatch($event, $eventObject);
		$results     = $eventResult->getArgument('result') ?: [];

		return is_array($results) ? $results : [];
	}
}