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