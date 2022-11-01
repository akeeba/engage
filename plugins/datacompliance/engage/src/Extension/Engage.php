<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Datacompliance\Engage\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\DataCompliance\Administrator\Helper\Export;
use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Akeeba\DataCompliance\Admin\Helper\Export as OldExport;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use SimpleXMLElement;

/**
 * Data Compliance plugin for Akeeba Engage
 */
class Engage extends CMSPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  3.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_engage'))
		{
			return [];
		}


		return [
			'onDataComplianceDeleteUser'          => 'onDataComplianceDeleteUser',
			'onDataComplianceExportUser'          => 'onDataComplianceExportUser',
			'onDataComplianceGetWipeBulletpoints' => 'onDataComplianceGetWipeBulletpoints',
		];
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Sanitize comments relevant to the user
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function onDataComplianceDeleteUser(Event $event): void
	{
		/**
		 * @var   int    $userID The user ID we are asked to delete
		 * @var   string $type   The export type (user, admin, lifecycle)
		 */
		[$userID, $type] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$ret = [
			'engage' => [
				'id' => [],
			],
		];

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Engage data", Log::INFO, 'com_datacompliance');

		$user = UserFetcher::getUser($userID);

		$this->getApplication()->getLanguage()->load('com_engage', JPATH_ADMINISTRATOR);
		$this->getApplication()->getLanguage()->load('com_engage', JPATH_SITE);

		$ret['engage']['id'] = Meta::pseudonymiseUserComments($user);

		$event->setArgument('result', array_merge($result, [$ret]));
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__engage_comments
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceExportUser(Event $event): void
	{
		/** @var   int $userID The user ID to export data for */
		[$int] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$export = new SimpleXMLElement("<root></root>");
		$db     = $this->getDatabase();

		// #__engage_comments by created_by
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'engage_comments');
		$domain->addAttribute('description', 'Comments, via Akeeba Engage');

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('created_by') . ' = ' . $db->q($userID));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			if (class_exists(Export::class))
			{
				Export::adoptChild($domain, Export::exportItemFromObject($record));
			}
			elseif (class_exists(OldExport::class))
			{
				OldExport::adoptChild($domain, OldExport::exportItemFromObject($record));
			}

			unset($record);
		}

		// #__engage_comments by email
		$user = Factory::getUser($userID);

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('email') . ' = ' . $db->q($user->email));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			if (class_exists(Export::class))
			{
				Export::adoptChild($domain, Export::exportItemFromObject($record));
			}
			elseif (class_exists(OldExport::class))
			{
				OldExport::adoptChild($domain, OldExport::exportItemFromObject($record));
			}

			unset($record);
		}

		$event->setArgument('result', array_merge($result, [$export]));
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onDataComplianceGetWipeBulletpoints(Event $event): ?array
	{
		$event->setArgument('result', array_merge($event->getArgument('result', []), [
			[
				Text::_('PLG_DATACOMPLIANCE_ENGAGE_DOMAINNAMEACTIONS_1'),
			],
		]));
	}
}
