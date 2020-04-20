<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Engage\Site\Helper\Meta;
use FOF30\Container\Container;
use FOF30\Utils\ArrayHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;

class plgUserEngage extends CMSPlugin
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

	/**
	 * Cache the user objects to remove
	 *
	 * @var User[]
	 */
	private $usersToRemove = [];

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *
	 * @return  void
	 */
	public function __construct(&$subject, $config = [])
	{
		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			$this->enabled = false;
		}

		if (!ComponentHelper::isEnabled('com_engage'))
		{
			$this->enabled = false;
		}

		parent::__construct($subject, $config);

		$this->loadLanguage();
	}


	/**
	 * Remove all user profile information for the given user ID.
	 *
	 * Method is called before user data is deleted from the database. We use it to cache the user object so we can use
	 * it onUserAfterDelete when the user object is no longer available through the Joomla API.
	 *
	 * @param   array   $user     Holds the user data
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function onUserBeforeDelete($user): bool
	{
		// Make sure we can actually run
		if (!$this->enabled)
		{
			return true;
		}

		// Get the user ID; fail if it's not available
		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');

		if (!$userId)
		{
			return true;
		}

		// Get and verify the user object
		$userObject = Factory::getUser($userId);

		if ($userObject->id != $userId)
		{
			return true;
		}

		// Cache the user object
		$this->usersToRemove[$userId] = clone $userObject;

		return true;
	}

	/**
	 * Remove all user profile information for the given user ID
	 *
	 * Method is called after user data is deleted from the database
	 *
	 * @param   array   $user     Holds the user data
	 * @param   bool    $success  True if user was successfully stored in the database
	 * @param   string|null  $msg      Message
	 *
	 * @return  bool
	 *
	 * @throws  Exception
	 */
	public function onUserAfterDelete(array $user, bool $success = true, ?string $msg = null): bool
	{
		// Make sure we can actually run
		if (!$this->enabled)
		{
			return true;
		}

		// Get the user ID; fail if it's not available
		$userId = ArrayHelper::getValue($user, 'id', 0, 'int');

		if (!$userId)
		{
			return true;
		}

		// Make sure we've seen this user ID before
		if (array_key_exists($userId, $this->usersToRemove))
		{
			return true;
		}

		// If Joomla reported failure to remove the user we don't remove the comments.
		if (!$success)
		{
			unset($this->usersToRemove[$userId]);

			return true;
		}

		// Remove the comments and uncache the user object.
		$this->getContainer()->platform->loadTranslations('com_engage');

		Meta::pseudonymiseUserComments($this->usersToRemove[$userId], true);

		unset($this->usersToRemove[$userId]);

		return true;
	}

	/**
	 * Get the Akeeba Engage container, preloaded for comments display
	 *
	 * @return  Container
	 */
	private function getContainer(): Container
	{
		if (empty($this->container))
		{
			// Get the container singleton instance
			$this->container = Container::getInstance('com_engage');
		}

		return $this->container;
	}


}