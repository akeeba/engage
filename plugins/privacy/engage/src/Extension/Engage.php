<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Privacy\Engage\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Site\Helper\Meta;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\User as UserTable;
use Joomla\CMS\User\User;
use Joomla\Component\Privacy\Administrator\Plugin\PrivacyPlugin;
use Joomla\Component\Privacy\Administrator\Table\RequestTable as PrivacyTableRequest;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use const JPATH_ADMINISTRATOR;
use const JPATH_SITE;

/**
 * com_privacy plugin for Akeeba Engage
 */
class Engage extends PrivacyPlugin implements SubscriberInterface
{
	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

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
			'onPrivacyExportRequest' => 'onPrivacyExportRequest',
			'onPrivacyRemoveData'    => 'onPrivacyRemoveData',
		];
	}

	/**
	 * Processes an export request for Joomla core user data
	 *
	 * This event will collect data for the following core tables:
	 *
	 * - #__engage_comments
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onPrivacyExportRequest(Event $event): void
	{
		/**
		 * @var   PrivacyTableRequest $request The request record being processed
		 * @var   User                $user    The user account associated with this request if available
		 */
		[$request, $user] = $event->getArguments();
		$result = $event->getArgument('result', []);

		/** @var UserTable $userTable */
		$userTable = User::getTable();
		$userTable->load($user->id);

		$domain = $this->createDomain('engage_comments', 'Comments, via Akeeba Engage');
		$db     = $this->db;

		// #__engage_comments by created_by

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('created_by') . ' = ' . $db->q($user->id));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			$domain->addItem($this->createItemFromArray((array) $record, $record->id));

			unset($record);
		}

		// #__engage_comments by email
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('email') . ' = ' . $db->q($user->email));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			$domain->addItem($this->createItemFromArray((array) $record, $record->id));

			unset($record);
		}

		$ret = [$domain];

		$event->setArgument('result', array_merge($result, [$ret]));
	}

	/**
	 * Removes the data associated with a remove information request
	 *
	 * This event will sanitize the Akeeba Engage comment data
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onPrivacyRemoveData(Event $event): void
	{
		/**
		 * @var   PrivacyTableRequest $request The request record being processed
		 * @var   User                $user    The user account associated with this request if available
		 */
		[$request, $user] = $event->getArguments();

		$language = $this->getApplication()->getLanguage();
		$language->load('com_engage', JPATH_ADMINISTRATOR);
		$language->load('com_engage', JPATH_SITE);

		Meta::pseudonymiseUserComments($user);
	}
}
