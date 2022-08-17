<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Engage\Gravatar\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * Gravatar integration for Akeeba Engage
 *
 * @since  1.0.0
 */
class Gravatar extends CMSPlugin implements SubscriberInterface
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
		return [
			'onAkeebaEngageUserAvatarURL'  => 'onAkeebaEngageUserAvatarURL',
			'onAkeebaEngageUserProfileURL' => 'onAkeebaEngageUserProfileURL',
		];
	}

	/**
	 * Returns the avatar image URL for a user
	 *
	 * @param   Event  $event  The Joomla event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAkeebaEngageUserAvatarURL(Event $event): void
	{
		/**
		 * @var   User $user The Joomla user object
		 * @var   int  $size The size of the avatar in pixels (avatars are square)
		 */
		[$user, $size] = $event->getArguments();

		$hash   = md5($user->email);
		$rating = $this->params->get('rating', 'g');
		$url    = 'https://www.gravatar.com/avatar/' . $hash . '?s=' . ($size ?? 48) . '&r=' . $rating;

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

		$result = $event->getArgument('result', []);
		$event->setArgument('result', array_merge($result, [$url]));
	}

	/**
	 * Returns the user's profile link
	 *
	 * @param   Event  $event  The Joomla event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAkeebaEngageUserProfileURL(Event $event): void
	{
		/**
		 * @var   User $user The Joomla user object
		 */
		[$user] = $event->getArguments();

		$useProfile = $this->params->get('profile_link', 1);
		$url        = null;

		if ($useProfile)
		{
			$url = 'https://www.gravatar.com/' . md5($user->email);
		}

		$result = $event->getArgument('result', []);
		$event->setArgument('result', array_merge($result, [$url]));
	}
}
