<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Service\Html;

use Akeeba\Component\Engage\Administrator\Helper\BBCode;
use Akeeba\Component\Engage\Administrator\Helper\HtmlFilter;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\Helpers\JGrid;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;

defined('_JEXEC') or die;

final class Engage
{
	use DatabaseAwareTrait;

	/**
	 * Public constructor
	 *
	 * @param   DatabaseDriver  $db  The application's database driver object
	 */
	public function __construct(DatabaseDriver $db)
	{
		$this->setDbo($db);
	}

	/**
	 * Get an IP lookup URL for the provided IP address
	 *
	 * @param   string|null  $ip  The IP address to look up
	 *
	 * @return  string  The lookup URL, empty if not applicable.
	 * @since   1.0.0
	 */
	public function getIPLookupURL(?string $ip): string
	{
		static $protoURL;

		$protoURL = $protoURL ?? ComponentHelper::getParams('com_engage')->get('iplookup', '');

		if (empty($ip) || empty($protoURL) || (strpos($protoURL, '%s') === false))
		{
			return '';
		}

		return sprintf($protoURL, urlencode($ip));
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
	 * @since   1.0.0
	 */
	public function processCommentTextForDisplay(?string $text): string
	{
		if (empty($text))
		{
			return '';
		}

		$text = $this->processFlatComment($text);
		$text = $this->processRemoveRelAttributes($text);
		$text = $this->processAnchorTagsNoFollow($text);

		return $text;
	}

	/**
	 * Create an excerpt of the comment text (maximum 50 words or 350 characters).
	 *
	 * @param   string|null  $text
	 *
	 * @return  string
	 * @since   3.0.0
	 */
	public function textExcerpt(?string $text): string
	{
		if (empty($text))
		{
			return '';
		}

		$excerpt = str_replace(["\n", "\r", "\t"], [' ', ' ', ' '], $text);
		$excerpt = str_replace(["<br/>", "<br />", "</p>"], ["\n", "\n", "\n"], $excerpt);
		$excerpt = strip_tags($excerpt);

		if (str_word_count($excerpt) <= 50 || strlen($excerpt) <= 350)
		{
			return $text;
		}

		$excerpt = explode(' ', $excerpt);
		$excerpt = array_filter($excerpt, function ($x) {
			return !empty($x);
		});
		$excerpt = array_slice($excerpt, 0, min(50, count($excerpt)));
		$excerpt = implode(' ', $excerpt);

		if (strlen($excerpt) > 350)
		{
			$excerpt = substr($excerpt, 0, 350);
		}

		$excerpt .= '…';

		return nl2br($excerpt);
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
	 * @since   1.0.0
	 */
	public function processFlatComment(string $text): string
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

		return HtmlFilter::filterText(BBCode::parseBBCode($text));
	}

	/**
	 * Returns a published state on a grid
	 *
	 * @param   integer       $value        The state value.
	 * @param   integer       $i            The row index
	 * @param   string|array  $prefix       An optional task prefix or an array of options
	 * @param   boolean       $enabled      An optional setting for access control on the action.
	 * @param   string        $checkbox     An optional prefix for checkboxes.
	 * @param   string        $publishUp    An optional start publishing date.
	 * @param   string        $publishDown  An optional finish publishing date.
	 * @param   string        $formId       An optional form selector.
	 *
	 * @return  string  The HTML markup
	 *
	 * @see     JHtmlJGrid::state()
	 * @since   1.6
	 */
	public static function published($value, $i, $prefix = '', $enabled = true, $checkbox = 'cb', $publishUp = null, $publishDown = null,
	                                 $formId = null
	)
	{
		if (is_array($prefix))
		{
			$options = $prefix;
			$enabled = array_key_exists('enabled', $options) ? $options['enabled'] : $enabled;
			$checkbox = array_key_exists('checkbox', $options) ? $options['checkbox'] : $checkbox;
			$prefix = array_key_exists('prefix', $options) ? $options['prefix'] : '';
		}

		$states = [
			1 => ['unpublish', 'JPUBLISHED', 'JLIB_HTML_UNPUBLISH_ITEM', 'JPUBLISHED', true, 'publish', 'publish'],
			0 => ['publish', 'JUNPUBLISHED', 'JLIB_HTML_PUBLISH_ITEM', 'JUNPUBLISHED', true, 'unpublish', 'unpublish'],
			-3 => [
				'reportham', 'COM_ENGAGE_COMMENT_ENABLED_OPT_POSSIBLE_SPAM', 'COM_ENGAGE_COMMENTS_TOOLBAR_REPORTHAM',
				'COM_ENGAGE_COMMENT_ENABLED_OPT_POSSIBLE_SPAM', true, 'flag text-warning border-warning', 'flag',
			],
			-2 => [
				'reportspam', 'COM_ENGAGE_COMMENT_ENABLED_OPT_SPAM', 'COM_ENGAGE_COMMENTS_TOOLBAR_REPORTSPAM',
				'COM_ENGAGE_COMMENT_ENABLED_OPT_SPAM', true, 'exclamation-circle text-danger border-danger', 'exclamation-circle',
			],
		];

		// Special state for dates
		if ($publishUp || $publishDown)
		{
			$nullDate = Factory::getDbo()->getNullDate();
			$nowDate = Factory::getDate()->toUnix();

			$tz = Factory::getUser()->getTimezone();

			$publishUp = ($publishUp !== null && $publishUp !== $nullDate) ? Factory::getDate($publishUp, 'UTC')->setTimezone($tz) : false;
			$publishDown = ($publishDown !== null && $publishDown !== $nullDate) ? Factory::getDate($publishDown, 'UTC')->setTimezone($tz) : false;

			// Create tip text, only we have publish up or down settings
			$tips = array();

			if ($publishUp)
			{
				$tips[] = Text::sprintf('JLIB_HTML_PUBLISHED_START', HTMLHelper::_('date', $publishUp, Text::_('DATE_FORMAT_LC5'), 'UTC'));
			}

			if ($publishDown)
			{
				$tips[] = Text::sprintf('JLIB_HTML_PUBLISHED_FINISHED', HTMLHelper::_('date', $publishDown, Text::_('DATE_FORMAT_LC5'), 'UTC'));
			}

			$tip = empty($tips) ? false : implode('<br>', $tips);

			// Add tips and special titles
			foreach ($states as $key => $state)
			{
				// Create special titles for published items
				if ($key == 1)
				{
					$states[$key][2] = $states[$key][3] = 'JLIB_HTML_PUBLISHED_ITEM';

					if ($publishUp > $nullDate && $nowDate < $publishUp->toUnix())
					{
						$states[$key][2] = $states[$key][3] = 'JLIB_HTML_PUBLISHED_PENDING_ITEM';
						$states[$key][5] = $states[$key][6] = 'pending';
					}

					if ($publishDown > $nullDate && $nowDate > $publishDown->toUnix())
					{
						$states[$key][2] = $states[$key][3] = 'JLIB_HTML_PUBLISHED_EXPIRED_ITEM';
						$states[$key][5] = $states[$key][6] = 'expired';
					}
				}

				// Add tips to titles
				if ($tip)
				{
					$states[$key][1] = Text::_($states[$key][1]);
					$states[$key][2] = Text::_($states[$key][2]) . '<br>' . $tip;
					$states[$key][3] = Text::_($states[$key][3]) . '<br>' . $tip;
					$states[$key][4] = true;
				}
			}

			return JGrid::state($states, $value, $i, array('prefix' => $prefix, 'translate' => !$tip), $enabled, true, $checkbox, $formId);
		}

		return JGrid::state($states, $value, $i, $prefix, $enabled, true, $checkbox, $formId);
	}

	/**
	 * Remove existing rel attributes from all tags
	 *
	 * @param   string  $text  The comment text to process
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function processRemoveRelAttributes(string $text): string
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
	 * @since   1.0.0
	 */
	private function processAnchorTagsNoFollow(string $text): string
	{
		$text = preg_replace_callback('/(<a\s*[^>]*\s+)href\s*=/i', function (array $matches): string {
			return rtrim($matches[1]) . ' rel="nofollow noreferrer" href=';
		}, $text);

		return $text;
	}

	/**
	 * Does this piece of text looks like possibly valid HTML body text?
	 *
	 * @param   string  $text  The text to examine
	 *
	 * @return  bool  True if it looks like HTML
	 * @since   1.0.0
	 */
	private function isLikelyHtml(string $text): bool
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