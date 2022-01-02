<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\CliCommand\Mixin;

defined('_JEXEC') || die;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Set up the Symfony I/O objects
 *
 * @since  3.0.0
 */
trait ConfigureIO
{
	/**
	 * @var   SymfonyStyle
	 * @since 3.0.0
	 */
	private $ioStyle;

	/**
	 * @var   InputInterface
	 * @since 3.0.0
	 */
	private $cliInput;

	/**
	 * Configure the IO.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	private function configureSymfonyIO(InputInterface $input, OutputInterface $output)
	{
		$this->cliInput = $input;
		$this->ioStyle  = new SymfonyStyle($input, $output);
	}

}
