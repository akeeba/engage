<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;

/**
 * Utility class to retrieve user objects given the user ID.
 *
 * This replaces Factory::getUser in Joomla since it's deprecated as of Joomla 4 and might go away anytime.
 *
 * Furthermore, it caches the fetching — unlike the user service — making returning users in comment threads
 * substantially faster that going through Joomla!'s services.
 *
 * @since 3.0.0
 */
class UserFetcher
{
	private static $cacheIdentities = true;

	private static $identityUserCache = null;

	private static $usersCache = [];

	/**
	 * Get a Joomla user object
	 *
	 * @param   int|null  $id  The ID of the user; NULL for currently logged in user
	 *
	 * @return  User|null
	 *
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

}