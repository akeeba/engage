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

		$text = self::processFlatComment($text);
		$text = self::processRemoveRelAttributes($text);
		$text = self::processAnchorTagsNoFollow($text);

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

	/**
	 * Remove existing rel attributes from all tags
	 *
	 * @param   string  $text  The comment text to process
	 *
	 * @return  string
	 */
	private static function processRemoveRelAttributes(string $text): string
	{
		$text = preg_replace_callback('/(<[a-z_\-\.]*\s*[^>]*\s+)(rel\s*=\s*"[^"]+")/i', function (array $matches): string {
			return $matches[1];
		}, $text);

		return $text;
	}

	/**
	 * Add rel="nofollow noreferrer" to anchor tags
	 *
	 * @param   string  $text  The comment text to process
	 *
	 * @return  string
	 */
	private static function processAnchorTagsNoFollow(string $text): string
	{
		$text = preg_replace_callback('/(<a\s*[^>]*\s+)href\s*=/i', function (array $matches): string {
			return rtrim($matches[1]) . ' rel="nofollow noreferrer" href=';
			//var_dump($matches);die;
		}, $text);

		return $text;
	}

	/**
	 * This can turn WordPress-style comments (partially HTML) into a passable HTML document.
	 *
	 * @param   string  $text  What we expect is a borked WordPress comment ;)
	 *
	 * @return  string  Actual HTML code we can display.
	 */
	public static function processFlatComment(string $text): string
	{
		$text = trim($text);

		// Do I have a paragraph tag in the beginning of the comment?
		if (in_array(strtolower(substr($text, 0, 3)), ['<p>', '<p ']))
		{
			return $text;
		}

		// Do I have a DIV tag in the beginning of the comment?
		if (in_array(strtolower(substr($text, 0, 5)), ['<div>', '<div ']))
		{
			return $text;
		}

		$paragraphs = explode("\n\n", $text);

		return implode("\n", array_map(function (string $text) {
			// Do I have a p tag?
			if (in_array(strtolower(substr($text, 0, 3)), ['<p>', '<p ']))
			{
				return $text;
			}

			// Do I have a div tag?
			if (in_array(strtolower(substr($text, 0, 5)), ['<div>', '<div ']))
			{
				return $text;
			}

			return "<p>" . $text . "</p>";
		}, $paragraphs));
	}
}