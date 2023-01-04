<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\Helper;

defined('_JEXEC') or die();

use Exception;
use Joomla\CMS\Crypt\Crypt;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Helper class to work with signed URLs for inclusion in email messages
 *
 * @since 1.0.0
 */
final class SignedURL
{
	/**
	 * Get an absolute, signed, SEF URL
	 *
	 * @param   string       $url      The non-SEF URL to sign
	 * @param   object       $comment  The comment object used in the signature
	 * @param   string|null  $email    The email address used in the signature
	 *
	 * @return  string
	 * @throws Exception
	 * @since   1.0.0
	 */
	public static function getAbsoluteSignedURL(string $url, object $comment, ?string $email = null): string
	{
		$signedURL = self::getSignedURL($url, $comment, $email);

		return Route::_($signedURL, true, Route::TLS_IGNORE, true);
	}

	/**
	 * Get a non-SEF, signed URL
	 *
	 * @param   string       $url      The non-SEF URL to sign
	 * @param   object       $comment  The comment object used in the signature
	 * @param   string|null  $email    The email address used in the signature
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public static function getSignedURL(string $url, object $comment, ?string $email = null): string
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
		$uri->setVar('cid[]', $comment->id);
		$uri->setVar('token', self::getToken($task, $email, $comment->asset_id, $expires));

		return $uri->toString(['path', 'query', 'fragment']);
	}

	/**
	 * Use HMAC-SHA-1 to generate a secure token
	 *
	 * @param   string  $task      The component task to include in the signature
	 * @param   string  $email     The email address to include in the signature
	 * @param   string  $asset_id  The article's asset_id to include in the signature
	 * @param   int     $expires   The expiration UNIX timestamp to include in the signature
	 *
	 * @return  string
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public static function getToken(string $task, string $email, string $asset_id, int $expires): string
	{
		$signString = $task . '-' . $email . '-' . $asset_id . '-' . $expires;
		$key        = Factory::getApplication()->get('secret');

		return hash_hmac('sha1', $signString, $key, false);
	}

	/**
	 * Verify the token of a signed URL
	 *
	 * @param   string|null  $token     The token to verify
	 * @param   string|null  $task      The component task, used to calculate the reference signature
	 * @param   string|null  $email     The email address, used to calculate the reference signature
	 * @param   string|null  $asset_id  The article's asset ID, used to calculate the reference signature
	 * @param   int|null     $expires   The expiration datetime of the signature, used to calculate the reference
	 *                                  signature
	 *
	 * @return  bool
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public static function verifyToken(?string $token, ?string $task, ?string $email, ?string $asset_id, ?int $expires)
	{
		/**
		 * IMPORTANT! While an empty token or empty individual token components immediately disqualify the token, we
		 * need to go through all the code to provide a constant time token check. Do not try to be "smart" by doing
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
}
