<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Model\Mixin;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use DateTimeZone;
use Exception;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;

trait NormalizeBrowserDateTimeInput
{
	/**
	 * Converts the date/time returned by the browser's ‘datetime-local’ input field to a GMT, database formatted string
	 *
	 * The browser's ‘datetime-local’ input fields allow the user to enter a date and time in their local timezone. This
	 * is conveyed to the server as a string similar to “2021-07-01T12:00”, without a timezone. One option is to have
	 * the user try to make GMT conversions in their head and ask them to enter a GMT date and time. This is confusing,
	 * more so when the date and time rendered on the page is local. Instead, we want to have the user enter a date and
	 * time string in their local timezone.
	 *
	 * However, the database server expects GMT dates. This is where this method comes in.
	 *
	 * We figure out the applicable time zone and append it in an ISO-8601-compatible format to the date/time string.
	 * Even though most databases SHOULD understand ISO-8601 formatted strings we take an additional precautionary step
	 * by passing the ISO-8601 string through Joomla's Date class and using it's ‘toSql’ method to get the canonical
	 * representation of the date in a format the database server will understand.
	 *
	 * @param   string  $dateTime  The browser-returned local date and time string (ISO-8601 without timezone).
	 *
	 * @return  string  The GMT date and time in canonical database format.
	 * @since   3.0.0
	 */
	protected function normaliseBrowserDateTime(string $dateTime): string
	{
		if (empty($dateTime))
		{
			return '';
		}

		try
		{
			// Get the user's timezone. If none is specified we use the server's timezone defined in Global Configuration.
			$app  = Factory::getApplication();
			$user = UserFetcher::getUser();
			$zone = $user->getParam('timezone', $app->get('offset', 'UTC'));
			$tz   = new DateTimeZone($zone);

			// Get the offset from GMT and express it as +HH:MM or -HH:MM
			$gmt     = new Date('now', new DateTimeZone('UTC'));
			$offset  = $tz->getOffset($gmt);
			$prefix  = ($offset >= 0) ? '+' : '-';
			$hours   = floor($offset / 3600);
			$minutes = floor(($offset % 3600) / 60);

			/**
			 * Convert the browser time string, expressed in the user's local timezone as YYYY-mm-ddTHH:MM:SS to an ISO 8601
			 * date time string. For example, July 1st, 2021 noon Easter European Summer Time is returned by the browser as
			 * 2021-07-01T12:00 and we convert it to 2021-07-01T12:00+03:00
			 */
			$dateString = $dateTime . $prefix . $hours . ':' . $minutes;

			// Create a new DateTime object from the ISO 8601 date
			$date = new Date($dateString, new DateTimeZone('UTC'));

			// Return the GMT date time in database notation, e.g. 2021-06-30 21:00:00 for the previous example.
			return $date->toSql();
		}
		catch (Exception $e)
		{
			return '';
		}
	}

}