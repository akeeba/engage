<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Helper;

defined('_JEXEC') or die;

use Akeeba\Engage\Site\Helper\Filter;
use FOF40\Container\Container;

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
	 * This can turn WordPress-style comments (partially HTML) into a passable HTML document.
	 *
	 * Here's the thing. WordPress' comment system is a site compromise waiting to happen. WordPress does NOT filter
	 * HTML properly. I've had people who submitted comments containing XML tags – thankfully benign ones. WordPress
	 * kept them as-is without converting the tag boundaries to &lt; and &gt;. The only easy way to fix that is to
	 * convert WordPress' comment FrankenHTML into bbCode and parse the comment as bbCode (which escapes everything that
	 * is not an explicit tag). This works great for my imported comments. Your mileage may vary :p
	 *
	 * @param   string  $text  What we expect is a borked WordPress comment ;)
	 *
	 * @return  string  Actual HTML code we can display.
	 */
	public static function processFlatComment(string $text): string
	{
		// If the text looks like valid HTML then return it as-is
		if (self::isLikelyHtml($text))
		{
			return $text;
		}

		$text = preg_replace('#<a href="(.*)">(.*)</a>#i', '[url=$1]$2[/url]', $text);
		$text = preg_replace('#<strong>(.*)</strong>#i', '[b]$1[/b]', $text);
		$text = preg_replace('#<b>(.*)</b>#i', '[b]$1[/b]', $text);
		$text = preg_replace('#<em>(.*)</em>#i', '[i]$1[/i]', $text);
		$text = preg_replace('#<i>(.*)</i>#i', '[i]$1[/i]', $text);
		$text = preg_replace('#<u>(.*)</u>#i', '[u]$1[/u]', $text);
		$text = preg_replace('#<s>(.*)</s>#i', '[s]$1[/s]', $text);
		$text = preg_replace('#<ul>(.*)</ul>#i', '[list]$1[/list]', $text);
		$text = preg_replace('#<ol>(.*)</ol>#i', '[list=1]$1[/list]', $text);
		$text = preg_replace('#<li>(.*)</li>#i', '[*] $1', $text);
		$text = preg_replace('#<pre>(.*)</pre>#i', '[code]$1[/code]', $text);
		$text = preg_replace('#<code>(.*)</code>#i', '[code]$1[/code]', $text);
		$text = preg_replace('#<tt>(.*)</tt>#i', '[code]$1[/code]', $text);
		$text = preg_replace('#<blockquote>(.*)</blockquote>#i', '[quote]$1[/quote]', $text);
		$text = preg_replace('#<img(.*)src="(.*)"(.*)/?>#i', '[img=$2]', $text);

		$text = htmlentities($text);

		return Filter::filterText(BBCode::parseBBCode($text));
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
	 * Does this piece of text looks like possibly valid HTML body text?
	 *
	 * @param   string  $text  The text to examine
	 *
	 * @return  bool  True if it looks like HTML
	 */
	private static function isLikelyHtml(string $text): bool
	{
		// Remove any whitespace and newlines
		$text = trim($text);

		// The text must start with opening a tag
		if (substr($text, 0, 1) != '<')
		{
			return false;
		}

		/**
		 * Valid HTML must start with a block level HTML element.
		 *
		 * We use a length-sorted collection of block level tags to improve the performance below.
		 *
		 * HTML elements reference: https://developer.mozilla.org/en-US/docs/Web/HTML/Block-level_elements
		 */
		$sortedElements = [
			1  => ['p'],
			2  => ['dd', 'dl', 'dt', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'li', 'ol', 'ul'],
			3  => ['div', 'nav', 'pre'],
			4  => ['form', 'main'],
			5  => ['aside', 'table'],
			6  => ['dialog', 'figure', 'footer', 'header', 'hgroup'],
			7  => ['address', 'details', 'section'],
			8  => ['articles', 'fieldset'],
			10 => ['blockquote', 'figcaption'],
		];

		foreach ($sortedElements as $len => $elements)
		{
			if (in_array(substr($text, 1, $len), $elements))
			{
				return true;
			}
		}

		return false;
	}
}
