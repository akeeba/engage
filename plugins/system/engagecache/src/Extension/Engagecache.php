<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\EngageCache\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
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
		return [
			'onAfterRoute'   => 'onAfterRoute',
			'onBeforeRender' => 'onBeforeRender',
		];
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
	public function onAfterRoute(Event $event)
	{
		$app = $this->getApplication();

		if ($app->input->getCmd('option') !== 'com_content')
		{
			return;
		}

		if (!empty($app->registeredurlparams))
		{
			$registeredurlparams = $app->registeredurlparams;
		}
		else
		{
			$registeredurlparams = new \stdClass();
		}

		$registeredurlparams->akengage_limitstart = 'INT';
		$registeredurlparams->akengage_limit      = 'INT';
		$registeredurlparams->akengage_cid        = 'INT';

		$app->registeredurlparams = $registeredurlparams;
	}

	/**
	 * Fixes some perplexing behaviour in Joomla.
	 *
	 * When caching is enabled Joomla will cache the JavaScript we told it load (good!) and its script options (great!),
	 * but not… the language strings. Which are used by the JavaScript code.
	 *
	 *
	 * @param   Event  $event
	 */
	public function onBeforeRender(Event $event)
	{
		// When caching is enabled Joomla does not call the events which allow for these lang strings to be included.
		$language = $this->getApplication()->getLanguage();
		$language->load('com_engage', JPATH_SITE);

		Text::script('COM_ENGAGE_COMMENTS_FORM_BTN_SUBMIT_PLEASE_WAIT');
		Text::script('COM_ENGAGE_COMMENTS_DELETE_PROMPT');
	}
}
