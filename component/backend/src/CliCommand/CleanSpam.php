<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\CliCommand;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\CliCommand\Mixin\CliRouting;
use Akeeba\Component\Engage\Administrator\CliCommand\Mixin\ConfigureIO;
use Akeeba\Component\Engage\Administrator\CliCommand\Mixin\MemoryInfo;
use Akeeba\Component\Engage\Administrator\CliCommand\Mixin\TimeInfo;
use Akeeba\Component\Engage\Administrator\Model\CommentsModel;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\Router\Route;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseDriver;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanSpam extends AbstractCommand
{
	use ConfigureIO;
	use MemoryInfo;
	use TimeInfo;
	use CliRouting;
	use MVCFactoryAwareTrait;

	/**
	 * The default command name
	 *
	 * @var    string
	 * @since  5.0.0
	 */
	protected static $defaultName = 'engage:cleanspam';

	/**
	 * Configure the command.
	 *
	 * @return  void
	 *
	 * @since   3.0.0
	 */
	protected function configure(): void
	{
		$this->setDescription(Text::_('COM_ENGAGE_CLI_CLEANSPAM_DESC'));
		$this->setHelp(Text::_('COM_ENGAGE_CLI_CLEANSPAM_HELP'));

		$cParams        = ComponentHelper::getParams('com_engage');
		$defaultMaxDays = (int) $cParams->get('max_spam_age', 15);

		$this->addArgument('max-time', InputOption::VALUE_OPTIONAL, Text::_('COM_ENGAGE_CLI_CLEANSPAM_MAX_TIME'), 10);
		$this->addArgument('max-days', InputOption::VALUE_OPTIONAL, Text::_('COM_ENGAGE_CLI_CLEANSPAM_MAX_DAYS'), $defaultMaxDays);
	}

	/**
	 * Internal function to execute the command.
	 *
	 * @param   InputInterface   $input   The input to inject into the command.
	 * @param   OutputInterface  $output  The output to inject into the command.
	 *
	 * @return  integer  The command exit code
	 *
	 * @since   3.0.0
	 */
	protected function doExecute(InputInterface $input, OutputInterface $output): int
	{
		// Configure the Symfony I/O
		$this->configureSymfonyIO($input, $output);

		// Disable database query logging (causes out–of–memory errors)
		/** @var DatabaseDriver $db */
		$db = Factory::getContainer()->get('DatabaseDriver');
		$db->setMonitor(null);

		// Initialise the CLI routing
		$this->initCliRouting();

		/**
		 * Register the HTML helper.
		 *
		 * This goes a bit sideways to get there for a good reason.
		 *
		 * Calling the CMS Router boots the component which registers the HTML helper. If we don't do that and try to
		 * register the HTML helper directly the next time we try to build a URL through the Router it will try to boot
		 * the component which will try to re-register the HTML helper. Since the helper is already registered it will
		 * bomb out.
		 */
		Route::_('index.php?option=com_engage');

		$maxTime = $input->getArgument('max-time');
		$maxDays = $input->getArgument('max-days');

		$this->ioStyle->title(Text::_('COM_ENGAGE_CLI_CLEANSPAM_HEAD'));

		$this->ioStyle->comment([
			'Akeeba Engage',
			sprintf('Copyright (c) 2020-%s Akeeba Ltd / Nicholas K. Dionysopoulos', gmdate('Y')),
			'Akeeba Engage is Free Software, distributed under the terms of the GNU General Public License version 3 or, at your option, any later version. This program comes with ABSOLUTELY NO WARRANTY as per sections 15 & 16 of the license. See http://www.gnu.org/licenses/gpl-3.0.html for details.',
			sprintf('PHP %s (%s)', PHP_VERSION, PHP_SAPI),
		]);

		$maxTime = max($maxTime, 1);
		$maxDays = max($maxDays, 0);

		$this->ioStyle->info(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_DELETINGOLDSPAM_N', $maxDays));
		$this->ioStyle->comment(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_RUNNINGFOR_N', $maxTime));

		/** @var CommentsModel $model */
		$model       = $this->getMVCFactory()->createModel('Comments', 'Administrator', ['ignore_request' => true]);
		$spamRemoved = $model->cleanSpam($maxDays, $maxTime);

		if ($spamRemoved)
		{
			$this->ioStyle->success(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_NOSPAM', $maxDays));

			return 0;
		}

		$this->ioStyle->success(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_DELETED', $spamRemoved));

		return 0;
	}
}