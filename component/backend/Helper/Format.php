<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Helper;


use FOF30\Container\Container;

final class Format
{
	/**
	 * The component's container
	 *
	 * @var  Container|null
	 */
	private static $container;

	/**
	 * Get an IP lookup URL for the provided IP address
	 *
	 * @param   string|null  $ip  The IP address to look up
	 *
	 * @return  string  The lookup URL, empty if not applicable.
	 */
	public static function getIPLookupURL(?string $ip): string
	{
		if (empty($ip))
		{
			return '';
		}

		$protoURL = self::getContainer()->params->get('iplookup', '');

		if (empty($protoURL) || (strpos($protoURL, '%s') === false))
		{
			return '';
		}

		return sprintf($protoURL, $ip);
	}

	/**
	 * Processes the comment text for display in the front-end
	 *
	 * - Removes all rel attributes (in case you use Joomla's text filters which don't do that)
	 * - Adds rel="nofollow noreferrer" to all links
	 *
	 * @param   string|null  $text  The comment text
	 *
	 * @return  string  The processed comment text
	 */
	public static function processCommentTextForDisplay(?string $text): string
	{
		if (empty($text))
		{
			return '';
		}

		// Remove existing rel attributes from everything
		$text = preg_replace_callback('/(<[a-z_\-\.]*\s*[^>]*\s+)(rel\s*=\s*"[^"]+")/i', function (array $matches): string {
			return $matches[1];
		}, $text);

		// Add rel="nofollow noreferrer" to anchor tags
		$text = preg_replace_callback('/(<a\s*[^>]*\s+)href\s*=/i', function (array $matches): string {
			return rtrim($matches[1]) . ' rel="nofollow noreferrer" href=';
			//var_dump($matches);die;
		}, $text);

		return $text;
	}

	/**
	 * Get the component's Container
	 *
	 * @return  Container
	 */
	private static function getContainer(): Container
	{
		if (!is_null(self::$container))
		{
			return self::$container;
		}

		self::$container = Container::getInstance('com_engage');

		return self::$container;
	}
}