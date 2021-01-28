<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\DataCompliance\Admin\Helper\Export;
use Akeeba\Engage\Site\Helper\Meta;
use FOF40\Container\Container;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Data Compliance plugin for Akeeba Engage
 */
class plgDatacomplianceEngage extends CMSPlugin
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
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Sanitize comments relevant to the user
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): ?array
	{
		if (!$this->enabled)
		{
			return null;
		}

		$ret = [
			'engage' => [
				'engage_comment_id' => [],
			],
		];

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Engage data", Log::INFO, 'com_datacompliance');

		$user = Factory::getUser($userID);

		$this->container->platform->loadTranslations('com_engage');

		$ret['engage']['engage_comment_id'] = Meta::pseudonymiseUserComments($user);

		return $ret;
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  string[]
	 */
	public function onDataComplianceGetWipeBulletpoints(int $userID, string $type): ?array
	{
		if (!$this->enabled)
		{
			return null;
		}

		return [
			Text::_('PLG_DATACOMPLIANCE_ENGAGE_DOMAINNAMEACTIONS_1'),
		];
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__engage_comments
	 *
	 * @param   int  $userID
	 *
	 * @return  SimpleXMLElement
	 */
	public function onDataComplianceExportUser(int $userID): ?SimpleXMLElement
	{
		if (!$this->enabled)
		{
			return null;
		}

		$export = new SimpleXMLElement("<root></root>");
		$db     = $this->container->db;

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
			Export::adoptChild($domain, Export::exportItemFromObject($record));

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
			Export::adoptChild($domain, Export::exportItemFromObject($record));

			unset($record);
		}

		return $export;
	}
}
