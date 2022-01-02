<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

class plgEngageGravatar extends CMSPlugin
{
	/**
	 * Returns the avatar image URL for a user
	 *
	 * @param   User  $user  The Joomla user object
	 * @param   int   $size  The size of the avatar in pixels (avatars are square)
	 *
	 * @return  string|null  The URL to the avatar image. NULL if none can be determined / is applicable.
	 */
	public function onAkeebaEngageUserAvatarURL(User $user, int $size = 32): ?string
	{
		$hash   = md5($user->email);
		$rating = $this->params->get('rating', 'g');
		$url    = 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size . '&r=' . $rating;

		$defaultImage = $this->params->get('default_image', 'mp');
		$customImage  = $this->params->get('custom_default', '');
		$forceDefault = $this->params->get('force_default', 0);

		$customImage = trim($customImage);

		if (empty($customImage))
		{
			$defaultImage = 'mp';
		}

		switch ($defaultImage)
		{
			case 'custom':
				$url .= '&d=' . urlencode(Uri::base(false) . $customImage);
				break;

			default:
				$url .= '&d=' . $defaultImage;
				break;
		}

		if ($forceDefault)
		{
			$url .= '&f=y';
		}

		return $url;
	}

	/**
	 * Returns the user's profile link
	 *
	 * @param   User  $user  The Joomla! user object
	 *
	 * @return  string|null  The URL to the user's profile. NULL if none can be determined / is applicable.
	 */
	public function onAkeebaEngageUserProfileURL(User $user): ?string
	{
		$useProfile = $this->params->get('profile_link', 1);

		if (!$useProfile)
		{
			return null;
		}

		return 'https://www.gravatar.com/' . md5($user->email);
	}
}
