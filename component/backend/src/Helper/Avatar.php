<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Event\Event;

defined('_JEXEC') or die;

/**
 * Helper class to get the avatar of a user
 *
 * @since 3.0.0
 */
class Avatar
{
	/**
	 * Cache of avatars per user ID
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	private static $avatarImages = [];

	/**
	 * Get the user's avatar image for a specific size
	 *
	 * @param   int|null  $user_id        User ID to get the avatar for
	 * @param   int       $size           Image width in pixels
	 * @param   null      $fallbackEmail  Fallback email address is the user does not exist
	 *
	 * @return  string
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public static function getUserAvatar(?int $user_id, int $size = 128, $fallbackEmail = null): string
	{
		// Get the user and the normalised user ID.
		$user    = (is_numeric($user_id) && ($user_id > 0)) ? UserFetcher::getUser($user_id) : null;
		$user_id = is_object($user) ? $user->id : null;

		// Guest comment. Use a fake user and our plugin events. Return empty if no fallback email or plugin response.
		if (empty($user_id))
		{
			if (empty($fallbackEmail))
			{
				return $fallbackEmail;
			}

			$fakeUser        = new User();
			$fakeUser->email = $fallbackEmail;

			$avatars = array_filter(self::getAvatarFromPluginEvents($fakeUser, $size), function ($x) {
				return !empty($x);
			});

			if (empty($avatars))
			{
				return '';
			}

			return array_shift($avatars);
		}

		if (array_key_exists($user_id, self::$avatarImages))
		{
			return self::$avatarImages[$user_id];
		}

		// Support custom fields
		self::$avatarImages[$user_id] = self::getAvatarFromCustomField($user_id);

		if (!empty(self::$avatarImages[$user_id]))
		{
			return self::$avatarImages[$user_id];
		}

		// TODO Support Joomla plugin events — if Joomla ever has such an event...

		// Support our custom plugin events
		$avatars = array_filter(self::getAvatarFromPluginEvents($user, $size), function ($x) {
			return !empty($x);
		});

		self::$avatarImages[$user_id] = empty($avatars) ? '' : array_shift($avatars);

		return self::$avatarImages[$user_id];
	}

	/**
	 * Returns the URL for the user's profile page, empty if no profile is available
	 *
	 * @return  string  The user's profile page, empty if no profile is available
	 */
	public static function getProfileURL(User $user): string
	{
		try
		{
			PluginHelper::importPlugin('engage');

			$dispatcher = Factory::getApplication()->getDispatcher();
			$eventName  = 'onAkeebaEngageUserProfileURL';
			$event      = new Event($eventName, [$user]);
			$result     = $dispatcher->dispatch($eventName, $event);

			$results = !isset($result['result']) || \is_null($result['result']) ? [] : $result['result'];
		}
		catch (Exception $e)
		{
			$results = [];
		}

		$results = array_filter($results, function ($x) {
			return is_string($x) && !empty($x);
		});

		if (empty($results))
		{
			return '';
		}

		return array_shift($results);
	}

	/**
	 * Get the user's avatar from a custom field.
	 *
	 * The custom field is expected to render EITHER an img element OR a URL to the avatar image.
	 *
	 * @param   int|null  $user_id  The user ID to get the avatar for
	 *
	 * @return  string|null  The avatar URL (best guess!) or NULL if it is not possible
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private static function getAvatarFromCustomField(?int $user_id): ?string
	{
		$cParams       = ComponentHelper::getParams('com_engage');
		$customFieldId = $cParams->get('customfield_avatar', '');

		if (empty($customFieldId))
		{
			return null;
		}

		$user = UserFetcher::getUser($user_id);

		if ($user->guest)
		{
			return null;
		}

		$fields = FieldsHelper::getFields('com_users.user', $user, true);
		$fields = array_filter($fields, function (object $field) use ($customFieldId) {
			return $field->id == $customFieldId;
		});

		if (empty($fields))
		{
			return null;
		}

		$field         = array_shift($fields);
		$renderedValue = $field->value;

		if (is_array($renderedValue))
		{
			$renderedValue = array_shift($renderedValue);
		}

		if (empty($renderedValue))
		{
			return null;
		}

		$hasMatch = preg_match('#src\s*=\s*"(.*)"#i', $renderedValue, $matches);

		if (!$hasMatch)
		{
			return null;
		}

		return $matches[1] ?: null;
	}

	/**
	 * Get the user avatar from our custom plugin events
	 *
	 * @param   User  $user  The user to get the avatar for
	 * @param   int   $size  The desired avatar maximum dimension in pixels.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	private static function getAvatarFromPluginEvents(User $user, int $size): array
	{
		try
		{
			PluginHelper::importPlugin('engage');

			$dispatcher = Factory::getApplication()->getDispatcher();
			$eventName  = 'onAkeebaEngageUserAvatarURL';
			$event      = new Event($eventName, [$user, $size]);
			$result     = $dispatcher->dispatch($eventName, $event);

			return !isset($result['result']) || \is_null($result['result']) ? [] : $result['result'];
		}
		catch (Exception $e)
		{
			return [];
		}
	}


}