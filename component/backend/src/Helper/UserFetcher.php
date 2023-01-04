<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseDriver;

/**
 * Utility class to retrieve user objects given the user ID.
 *
 * This replaces Factory::getUser in Joomla since it's deprecated as of Joomla 4 and might go away anytime.
 *
 * Furthermore, it caches the fetching — unlike the user service — making returning users in comment threads
 * substantially faster that going through Joomla!'s services.
 *
 * @since  3.0.0
 */
class UserFetcher
{
	/**
	 * Should I cache user identities?
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	private static $cacheIdentities = true;

	/**
	 * Cache of email addresses to user IDs.
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private static $emailToIdCache = [];

	/**
	 * Cached copy of the identity user
	 *
	 * @var   User|null
	 * @since 3.0.0
	 */
	private static $identityUserCache = null;

	/**
	 * Cache of user IDs to user objects
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private static $usersCache = [];

	/**
	 * Get a Joomla user object
	 *
	 * @param   int|null  $id  The ID of the user; NULL for currently logged in user
	 *
	 * @return  User|null
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public static function getUser(?int $id = null): ?User
	{
		if (is_null($id))
		{
			if (!self::$cacheIdentities || empty(self::$identityUserCache))
			{
				$identityUser = Factory::getApplication()->getIdentity() ?? new User();
			}

			if (!self::$cacheIdentities)
			{
				return $identityUser;
			}

			if (empty(self::$identityUserCache))
			{
				self::$identityUserCache = $identityUser;
			}

			return self::$identityUserCache;
		}

		if (!self::$cacheIdentities)
		{
			return Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);
		}

		if (isset(self::$usersCache[$id]))
		{
			return self::$usersCache[$id];
		}

		self::$usersCache[$id] = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

		return self::$usersCache[$id];
	}

	/**
	 * Get a user object given an email address
	 *
	 * @param   string  $email  The email address to look up a user for
	 *
	 * @return  User|null  The corresponding Joomla user object; NULL if no match is found.
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public static function getUserByEmail(string $email): ?User
	{
		$id = self::getUserIdByEmail($email);

		return is_null($id) ? null : self::getUser($id);
	}

	/**
	 * Get the user ID given an email address.
	 *
	 * @param   string  $email  The email address to look up a user ID for
	 *
	 * @return  int|null  The corresponding user ID; NULL if no user matches this email address
	 * @since   3.0.0
	 */
	public static function getUserIdByEmail(string $email): ?int
	{
		$key = md5(strtolower(MailHelper::cleanLine($email)));

		if (array_key_exists($key, self::$emailToIdCache))
		{
			return self::$emailToIdCache[$key];
		}

		/** @var DatabaseDriver $db */
		$db = Factory::getContainer()->get('DatabaseDriver');
		$q  = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__users'))
			->where($db->qn('email') . ' = ' . $db->q($email));

		try
		{
			self::$emailToIdCache[$key] = $db->setQuery($q)->loadResult();
		}
		catch (Exception $e)
		{
			self::$emailToIdCache[$key] = null;
		}

		return self::$emailToIdCache[$key];
	}
}