<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\CliCommand\Mixin;

defined('_JEXEC') || die;

/**
 * Utility methods to get memory information
 *
 * @since  3.0.0
 */
trait MemoryInfo
{
	/**
	 * Returns the current memory usage
	 *
	 * @return  string
	 *
	 * @since   3.0.0
	 */
	private function memUsage(): string
	{
		if (function_exists('memory_get_usage'))
		{
			$size = memory_get_usage();
			$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

			return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}

	/**
	 * Returns the peak memory usage
	 *
	 * @return  string
	 *
	 * @since   3.0.0
	 */
	private function peakMemUsage(): string
	{
		if (function_exists('memory_get_peak_usage'))
		{
			$size = memory_get_peak_usage();
			$unit = ['b', 'KB', 'MB', 'GB', 'TB', 'PB'];

			return @round($size / 1024 ** ($i = floor(log($size, 1024))), 2) . ' ' . $unit[$i];
		}
		else
		{
			return "(unknown)";
		}
	}
}
