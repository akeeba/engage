<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

final class BBCode
{
	/**
	 * Convert BBcode to HTML
	 *
	 * @param   string  $text
	 * @param   int     $nestingLevel
	 *
	 * @return   string
	 * @since    1.0.0
	 */
	static public function parseBBCode(string $text, int $nestingLevel = 0): string
	{
		// Convert linebreaks (the CODE tag will revert them)
		$text = nl2br($text);

		// Parse the following tags: size, color, b, i, list, *, img
		$bracketStart = strpos($text, '[');

		while ($bracketStart !== false)
		{
			$bracketEnd = strpos($text, ']', $bracketStart);

			if ($bracketEnd === false)
			{
				break;
			}

			$tagData  = substr($text, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
			$tagData  = trim($tagData);
			$tagParts = explode('=', $tagData, 2);
			$tag      = strtoupper($tagParts[0]);
			$tagParam = (count($tagParts) > 1) ? $tagParts[1] : null;

			// When I have processed the tag, I'll set this to true
			$processedTag = false;

			if (in_array($tag, ['SIZE', 'COLOR', 'B', 'I', 'U', 'S', 'LIST', 'IMG', 'URL', 'CODE', 'QUOTE']))
			{
				// Find the end tag
				[$endTagStart, $endTagEnd] = self::findEndTag($tag, $text, $bracketEnd);

				// Exceptions where an end tag is not expected
				$exception = false;

				if ($tag == '*')
				{
					$exception = true;
				}

				if (($tag == 'IMG') && is_null($tagData))
				{
					$exception = true;
				}

				if (($endTagStart != false) && !$exception && ($nestingLevel <= 15))
				{
					$innerText = substr($text, $bracketEnd + 1, $endTagStart - $bracketEnd - 1);

					switch ($tag)
					{
						case 'SIZE':
							$processedTag = true;
							$fsize        = (int) $tagParam;

							if ($fsize <= 50)
							{
								$fsize = 50;
							}
							elseif ($fsize > 200)
							{
								$fsize = 200;
							}

							$newText = '<span style="font-size: ' . $fsize . '%">' . self::parseBBCode($innerText, $nestingLevel + 1) . '</span>';
							break;

						case 'COLOR':
							if (empty($tagParam))
							{
								$tagParam = '';
							}

							$color          = strtolower(trim($tagParam));
							$acceptedColors = [
								'aqua',
								'black',
								'blue',
								'fuchsia',
								'gray',
								'grey',
								'green',
								'lime',
								'maroon',
								'navy',
								'olive',
								'purple',
								'red',
								'silver',
								'teal',
								'white',
								'yellow',
								'beige',
								'brown',
								'chocolate',
								'cyan',
								'darkblue',
								'darkcyan',
								'darkgray',
								'darkgrey',
								'darkorange',
								'darkred',
								'dimgray',
								'dimgrey',
								'gold',
								'goldenrod',
								'greenyellow',
								'indigo',
								'ivory',
								'khaki',
								'lavender',
								'lightblue',
								'lightcyan',
								'lightgray',
								'lightgrey',
								'lightgreen',
								'lightyellow',
								'navy',
								'orangered',
								'pink',
								'plum',
								'skyblue',
								'slateblue',
								'snow',
								'tan',
								'tomato',
								'whitesmoke',
							];

							if (!in_array($color, $acceptedColors))
							{
								// Is it a hex code?
								if ((substr($color, 0, 1) == '#') && in_array(strlen($color), [4, 7]))
								{
									$hexValue = dechex(hexdec(substr($color, 1)));
									$color    = '#' . strtoupper($hexValue);
								}
								else
								{
									$color = '';
								}
							}

							$newText = self::parseBBCode($innerText, $nestingLevel + 1);

							if (!empty($color))
							{
								$newText = '<span style="color: ' . $color . '">' . $newText . '</span>';
							}

							$processedTag = true;
							break;

						case 'B':
							$newText      = '<strong>' . self::parseBBCode($innerText, $nestingLevel + 1) . '</strong>';
							$processedTag = true;
							break;

						case 'I':
							$newText      = '<em>' . self::parseBBCode($innerText, $nestingLevel + 1) . '</em>';
							$processedTag = true;
							break;

						case 'U':
							$newText      = '<span style="text-decoration: underline">' . self::parseBBCode($innerText, $nestingLevel + 1) . '</span>';
							$processedTag = true;
							break;

						case 'S':
							$newText      = '<span style="text-decoration: line-through">' . self::parseBBCode($innerText, $nestingLevel + 1) . '</span>';
							$processedTag = true;
							break;

						case 'CODE':
							$innerText = str_replace('<br />', "", $innerText);
							//$innerText = htmlentities($innerText, ENT_QUOTES, 'UTF-8');
							$newText      = '<pre>' . $innerText . '</pre>';
							$processedTag = true;
							break;

						case 'LIST':
							// Do I have an ordered or unordered list?
							if (empty($tagParam))
							{
								$element = 'ul';
								$start   = '';
							}
							else
							{
								$element = 'ol';

								if ((int) $tagParam > 0)
								{
									$start = ' start = "' . (int) $tagParam . '"';
								}
								else
								{
									$start = '';
								}
							}

							// Break the innertext on [*] elements
							if ($innerText)
							{
								$innerText  = trim(str_replace('<br />', '', $innerText));
								$innerParts = explode('[*]', $innerText);

								if (count($innerParts))
								{
									$items = [];

									foreach ($innerParts as $item)
									{
										$item = trim($item);

										if (empty($item))
										{
											continue;
										}

										$items[] = self::parseBBCode($item, $nestingLevel + 1);
									}

									$innerText = '<li>' . implode('</li><li>', $items) . '</li>';
									$newText   = '<' . $element . $start . '>' . $innerText . '</' . $element . '>';
								}
								else
								{
									$newText = self::parseBBCode($innerText, $nestingLevel + 1);
								}

							}
							else
							{
								$newText = '';
							}
							$processedTag = true;
							break;

						case 'IMG':
							$newText = '';
							$imgURL  = trim($innerText);

							if (!empty($imgURL))
							{
								$imgURL = filter_var($imgURL, FILTER_SANITIZE_URL);
							}

							if (!empty($imgURL))
							{
								$newText = '<img src="' . htmlspecialchars($imgURL, ENT_COMPAT) . '" />';
							}

							$processedTag = true;
							break;

						case 'URL':
							if (!is_null($tagParam))
							{
								$url = trim($tagParam);
							}
							else
							{
								$url = trim($innerText);
							}

							if (!empty($url))
							{
								$url = filter_var($url, FILTER_SANITIZE_URL);
							}

							if (!empty($url))
							{
								$anchorText = trim($innerText);

								if (empty($anchorText))
								{
									$anchorText = $url;
								}

								// Do not encode the anchor text. This leads to double encoding of the anchor text, ending up showing things like &mu; instead of μ.
								// $anchorText = htmlentities($anchorText, ENT_QUOTES);
								$newText = '<a rel="nofollow" href="' . htmlentities($url, ENT_COMPAT) . '">' . $anchorText . '</a>';
							}
							else
							{
								$newText = '';
							}

							$processedTag = true;
							break;

						case 'QUOTE':
							$cite = '';

							if (!is_null($tagParam))
							{
								$cite = ' cite="' . htmlentities($tagParam, ENT_COMPAT) . '"';
							}

							$newText      = '<blockquote' . $cite . '>' . self::parseBBCode($innerText, $nestingLevel + 1) . '</blockquote>';
							$processedTag = true;

							break;
						default:
							// Invalid tag. Strip it.

							$newText      = self::parseBBCode($innerText, $nestingLevel + 1);
							$processedTag = true;
							break;
					}

					if ($processedTag)
					{
						$text = self::replace($bracketStart, $endTagEnd, $newText, $text);
					}
				}
			}

			if (!$processedTag)
			{
				$anotherOpeningBracket = strpos($text, '[', $bracketStart + 1);

				if (($anotherOpeningBracket !== false) && ($anotherOpeningBracket < $bracketEnd))
				{
					// Opening brace inside the text; only replace until that point
					$allText      = substr($text, $bracketStart, $bracketEnd - $bracketStart + 1);
					$anotherStart = strpos($allText, '[', 2);
					$newString    = htmlentities(substr($allText, 0, $anotherStart - 1));
					$newString    = str_replace('[', '&#91;', $newString);
					$rest         = substr($allText, $anotherStart);
					$newString    .= $rest;

					while (strpos($newString, '[['))
					{
						$newString = str_replace('[[', '&#93;[', $newString);
					}
				}
				else
				{
					// No opening brace; replace brackets with HTML entities
					$newString = '&#91;' . htmlentities(substr($text, $bracketStart + 1, $bracketEnd - $bracketStart));
				}

				$text = self::replace($bracketStart, $bracketEnd, $newString, $text);
			}

			$bracketStart = strpos($text, '[');
		} // end while $bracketStart !== false

		// Finally, return the converted text
		return $text;
	}

	/**
	 * Replace a substring inside a string
	 *
	 * @param   int     $from  Starting position
	 * @param   int     $to    Ending position
	 * @param   string  $new   Text to replace with
	 * @param   string  $old   Text to find and replace with $new
	 *
	 * @return   string
	 * @since    1.0.0
	 */
	private static function replace(int $from, int $to, string $new, string $old): string
	{
		if ($from > $to)
		{
			$x    = $from;
			$from = $to;
			$to   = $x;
		}

		if ($from == 0)
		{
			return $new . substr($old, $to + 1);
		}
		else
		{
			return substr($old, 0, $from) . $new . substr($old, $to + 1);
		}
	}

	/**
	 * Find the closing BBcode tag.
	 *
	 * @param   string  $tag     The tag to look for.
	 * @param   string  $text    The text to look the end tag in.
	 * @param   int     $offset  Offset from the beginning $text the search will take place.
	 *
	 * @return   array|false[]
	 * @since    1.0.0
	 */
	private static function findEndTag(string $tag, string $text, int $offset = 0)
	{
		$ret = [false, false];

		$rest         = substr($text, $offset + 1);
		$bracketStart = strpos($text, '[');

		while ($bracketStart !== false)
		{
			$bracketEnd = strpos($text, ']', $bracketStart);

			if ($bracketEnd === false)
			{
				break;
			}

			$tagData  = substr($text, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
			$tagData  = trim($tagData);
			$tagParts = explode('=', $tagData, 2);
			$newTag   = strtoupper($tagParts[0]);

			// Check if we have /TAG
			$found = false;

			if (substr($newTag, 0, 1) == '/')
			{
				$tagName = trim(substr($newTag, 1));
				$found   = strtoupper($tag) == $tagName;
			}

			if ($found)
			{
				$ret          = [$bracketStart, $bracketEnd];
				$bracketStart = false;
			}
			else
			{
				$bracketStart = strpos($text, '[', $bracketStart + 1);
			}
		}

		return $ret;
	}
}
