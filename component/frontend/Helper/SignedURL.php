<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Helper;


use Akeeba\Engage\Admin\Model\Comments;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

final class SignedURL
{
	public static function getToken(string $task, string $email, string $asset_id, int $expires): string
	{
		$signString = $task . '-' . $email . '-' . $asset_id . '-' . $expires;
		$key        = Factory::getConfig()->get('secret');

		return hash_hmac('sha1', $signString, $key, false);
	}

	public static function verifyToken(?string $token, ?string $task, ?string $email, ?string $asset_id, ?int $expires)
	{
		/**
		 * IMPORTANT! While an empty token or empty individual token components immediately disqualify the token, we
		 * need to go through all of the code to provide a constant time token check. Do not try to be "smart" by doing
		 * early exist or combining checks with bitwise OR operators. These tricks, along with boolean short circuit
		 * evaluation, would cause the token check to be variable time which could cause subtle security issues. We
		 * really need to go through "stupid" code to achieve a constant time token verification.
		 */
		$validToken   = self::getToken($task ?? '', $email ?? '', $asset_id ?? '', $expires ?? '');
		$confirmToken = Crypt::timingSafeCompare($validToken, $token ?? '');

		if (is_null($task))
		{
			$confirmToken = false;
		}

		if (is_null($email))
		{
			$confirmToken = false;
		}

		if (is_null($asset_id))
		{
			$confirmToken = false;
		}

		if (is_null($expires))
		{
			$confirmToken = false;
		}

		if ($expires < time())
		{
			$confirmToken = false;
		}

		return $confirmToken;
	}

	public static function getSignedURL(string $url, Comments $comment, ?string $email = null): string
	{
		$uri   = new Uri($url);
		$task  = $uri->getVar('task', '');
		$email = $uri->getVar('email', $email);

		if (empty($task) || empty($email))
		{
			return $url;
		}

		$expires = (int) $uri->getVar('expires', time() + 86400);
		$uri->setVar('email', $email);
		$uri->setVar('expires', $expires);
		$uri->setVar('id', $comment->getId());
		$uri->setVar('token', self::getToken($task, $email, $comment->asset_id, $expires));

		return $uri->toString(['path', 'query', 'fragment']);
	}

	public static function getAbsoluteSignedURL(string $url, Comments $comment, ?string $email = null): string
	{
		$signedURL = self::getSignedURL($url, $comment, $email);

		return Route::_($signedURL, true, Route::TLS_IGNORE, true);
	}
}
