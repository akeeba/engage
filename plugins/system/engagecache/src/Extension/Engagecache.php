<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\System\Engagecache\Extension;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Workaround for paginated frontend comments display to guest users when caching is enabled.
 */
class Engagecache extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		return ['onAfterInitialise' => 'onAfterInitialise'];
	}

	/**
	 * Fixes the frontend display of comments for guests when caching is enabled.
	 *
	 * Joomla caches the entire article contents, including the plugin output, for guest users. The problem is that
	 * while this takes into account Joomla's pagination it does not take into account the comment pagination.
	 *
	 * Fortunately, BaseController::display does take into account a stdClass object named registeredurlparams if it's
	 * already set in the Joomla application object. This property does not exist in the base CMSApplication class and
	 * you'd be hard pressed to know it's a thing just by reading Joomla's developer documentation. Anyway, it is used
	 * if it's there so we prime it with the contents of our pagination parameters. This forces Joomla to take into
	 * account BOTH the active component's (e.g. com_content) caching parameters AND our comment pagination parameters.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 *
	 * @see     BaseController::display()
	 */
	public function onAfterInitialise(Event $event)
	{
		$app              = $this->getApplication();
		$cParams          = ComponentHelper::getParams('com_engage');
		$defaultListLimit = $cParams->get('default_limit', 20);
		$defaultListLimit = ($defaultListLimit == -1) ? 20 : $defaultListLimit;

		$limitStart = $app->input->getInt('akengage_limitstart', 0);
		$limit      = $app->input->getInt('akengage_limit', $defaultListLimit);

		if (!empty($app->registeredurlparams))
		{
			$registeredurlparams = $app->registeredurlparams;
		}
		else
		{
			$registeredurlparams = new \stdClass();
		}

		if (!empty($limitStart))
		{
			$registeredurlparams->akengage_limitstart = $limitStart;
		}

		$registeredurlparams->akengage_limit = $limit;

		$app->registeredurlparams = $registeredurlparams;
	}
}
