<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Engage\Site\Helper\Meta;
use FOF40\Container\Container;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\User as UserTable;
use Joomla\CMS\User\User;

// Joomla 3 requires a braindead way to include the PrivacyPlugin class
if (version_compare(JVERSION, '3.9999.9999', 'le'))
{
	JLoader::register('PrivacyPlugin', JPATH_ADMINISTRATOR . '/components/com_privacy/helpers/plugin.php');
}

/**
 * com_privacy plugin for Akeeba Engage
 */
class plgPrivacyEngage extends PrivacyPlugin
{
	/**
	 * Should this plugin be allowed to run?
	 *
	 * If the runtime dependencies are not met the plugin effectively self-disables even if it's published. This
	 * prevents a WSOD should the user e.g. uninstall a library or the component without unpublishing the plugin first.
	 *
	 * @var  bool
	 */
	private $enabled = true;

	/**
	 * The Akeeba Engage component container
	 *
	 * @var  Container|null
	 */
	private $container;

	public function __construct(&$subject, $config = [])
	{
		if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
		{
			$this->enabled = false;
		}

		if (!ComponentHelper::isEnabled('com_engage'))
		{
			$this->enabled = false;
		}

		parent::__construct($subject, $config);

		$this->loadLanguage();

		// Required here for Akeeba Engage's class autoloader to kick in.
		$this->container = Container::getInstance('com_engage');
	}

	/**
	 * Removes the data associated with a remove information request
	 *
	 * This event will sanitize the Akeeba Engage comment data
	 *
	 * @param   PrivacyTableRequest  $request  The request record being processed
	 * @param   User                 $user     The user account associated with this request if available
	 *
	 * @return  void
	 */
	public function onPrivacyRemoveData(PrivacyTableRequest $request, User $user = null): void
	{
		if (!$this->enabled)
		{
			return;
		}

		$this->container->platform->loadTranslations('com_engage');

		Meta::pseudonymiseUserComments($user);
	}


	/**
	 * Processes an export request for Joomla core user data
	 *
	 * This event will collect data for the following core tables:
	 *
	 * - #__engage_comments
	 *
	 * @param   PrivacyTableRequest  $request  The request record being processed
	 * @param   User                 $user     The user account associated with this request if available
	 *
	 * @return  PrivacyExportDomain[]
	 */
	public function onPrivacyExportRequest(PrivacyTableRequest $request, User $user = null): array
	{
		if (!$this->enabled)
		{
			return [];
		}

		/** @var UserTable $userTable */
		$userTable = User::getTable();
		$userTable->load($user->id);

		$domain = $this->createDomain('engage_comments', 'Comments, via Akeeba Engage');
		$db     = $this->container->db;

		// #__engage_comments by created_by

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('created_by') . ' = ' . $db->q($user->id));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			$domain->addItem($this->createItemFromArray((array) $record, $record->engage_comment_id));

			unset($record);
		}

		// #__engage_comments by email
		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__engage_comments'))
			->where($db->qn('email') . ' = ' . $db->q($user->email));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			$domain->addItem($this->createItemFromArray((array) $record, $record->engage_comment_id));

			unset($record);
		}

		return [$domain];
	}
}
