<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Timeout prevention timer
 *
 * @since  3.0.0
 */
class Timer
{
	/**
	 * Maximum execution time allowance per step
	 *
	 * @var   int
	 * @since 3.0.0
	 */
	private $max_exec_time;

	/**
	 * Timestamp of execution start
	 *
	 * @var   int
	 * @since 3.0.0
	 */
	private $start_time;

	/**
	 * Public constructor, creates the timer object and calculates the execution
	 * time limits.
	 *
	 * @param   int  $max_exec_time  Maximum execution time, in seconds
	 * @param   int  $runtime_bias   Runtime bias factor, as percent points of the max execution time
	 *
	 * @since   3.0.0
	 */
	public function __construct(int $max_exec_time = 5, int $runtime_bias = 75)
	{
		// Initialize start time
		$this->start_time = microtime(true);

		$this->max_exec_time = $max_exec_time * $runtime_bias / 100;
	}

	/**
	 * Wake-up function to reset internal timer when we get unserialized
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function __wakeup()
	{
		// Re-initialize start time on wake-up
		$this->start_time = microtime(true);
	}

	/**
	 * Gets the number of seconds left, before we hit the "must break" threshold
	 *
	 * @return  float
	 * @since   3.0.0
	 */
	public function getTimeLeft(): float
	{
		return $this->max_exec_time - $this->getRunningTime();
	}

	/**
	 * Gets the time elapsed since object creation/unserialization, effectively
	 * how long this step is running
	 *
	 * @return  float
	 * @since   3.0.0
	 */
	public function getRunningTime(): float
	{
		return microtime(true) - $this->start_time;
	}

	/**
	 * Reset the timer
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	public function resetTime(): void
	{
		$this->start_time = microtime(true);
	}
}
