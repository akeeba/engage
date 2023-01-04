<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Console\Engage\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\CliCommand\CleanSpam;
use Joomla\Application\ApplicationEvents;
use Joomla\Application\Event\ApplicationEvent;
use Joomla\CMS\Application\ConsoleApplication;
use Joomla\CMS\Factory as JoomlaFactory;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Throwable;

class Engage extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;

	private static $commands = [
		CleanSpam::class,
	];

	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  7.0.0
	 */
	protected $autoloadLanguage = true;

	public function __construct(&$subject, $config, MVCFactory $mvcFactory)
	{
		parent::__construct($subject, $config);

		$this->setMVCFactory($mvcFactory);
	}


	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   7.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			ApplicationEvents::BEFORE_EXECUTE => 'registerCLICommands',
		];
	}

	/**
	 * Registers command classes to the CLI application.
	 *
	 * This is an event handled for the ApplicationEvents::BEFORE_EXECUTE event.
	 *
	 * @param   ApplicationEvent  $event  The before_execite application event being handled
	 *
	 * @since        7.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function registerCLICommands(ApplicationEvent $event)
	{
		// Only register CLI commands if we can boot up the Akeeba Backup component enough to make it usable.
		try
		{
			$this->initialiseComponent();
		}
		catch (Throwable $e)
		{
			return;
		}

		foreach (self::$commands as $commandFQN)
		{
			try
			{
				if (!class_exists($commandFQN))
				{
					continue;
				}

				$command = new $commandFQN();

				if (method_exists($command, 'setMVCFactory'))
				{
					$command->setMVCFactory($this->getMVCFactory());
				}

				$this->getApplication()->addCommand($command);
			}
			catch (Throwable $e)
			{
				continue;
			}
		}
	}

	private function initialiseComponent(): void
	{
		// Load the Akeeba Ticket System language files
		$lang = JoomlaFactory::getApplication()->getLanguage();
		$lang->load('com_engage', JPATH_SITE, null, true, false);
		$lang->load('com_engage', JPATH_ADMINISTRATOR, null, true, false);

		// Make sure we have a version loaded
		@include_once(JPATH_ADMINISTRATOR . '/components/com_engage/version.php');

		if (!defined('AKENGAGE_VERSION'))
		{
			define('AKENGAGE_VERSION', 'dev');
			define('AKENGAGE_DATE', date('Y-m-d'));
		}
	}
}