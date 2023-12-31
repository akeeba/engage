<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engage\Admin\Model\Comments;
use FOF40\Container\Container;
use Joomla\CMS\Language\Text;

// region FOF CLI application boilerplate
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $curdir)
{
	if (file_exists($curdir . '/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/defines.php';

		break;
	}

	if (file_exists($curdir . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be placed in or run from the cli folder of your site.');

require_once JPATH_LIBRARIES . '/fof40/Cli/Application.php';

// endregion

class EngageCleanSpam extends FOFApplicationCLI
{
	protected function doExecute()
	{
		$this->out('Akeeba Engage -- Remove obsolete spam messages');
		$this->out('Copyright 2020-' . gmdate('Y') . ' Akeeba Ltd');
		$this->out(str_repeat('=', 79));

		@include_once JPATH_ADMINISTRATOR . '/components/com_engage/version.php';

		if (!defined('AKENGAGE_PRO'))
		{
			define('AKENGAGE_PRO', '0');
		}

		if (!defined('AKENGAGE_VERSION'))
		{
			define('AKENGAGE_VERSION', 'dev');
		}

		if (!defined('AKENGAGE_DATE'))
		{
			define('AKENGAGE_DATE', date('Y-m-d'));
		}

		// Work around some misconfigured servers which print out notices
		if (function_exists('error_reporting'))
		{
			$oldLevel = error_reporting(0);
		}

		$container = Container::getInstance('com_engage');

		if (function_exists('error_reporting'))
		{
			error_reporting($oldLevel);
		}

		// Initializes the CLI session handler.
		$container->session->get('foobar');

		// Load the language
		$container->platform->loadTranslations('com_engage');

		$defaultMaxDays = (int) $container->params->get('max_spam_age', 15);
		$maxTime        = $this->input->getInt('max-time', 10);
		$maxDays        = $this->input->getInt('max-days', $defaultMaxDays);

		$maxTime = max($maxTime, 1);
		$maxDays = max($maxDays, 0);

		$this->out(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_DELETINGOLDSPAM_N', $maxDays));
		$this->out(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_RUNNINGFOR_N', $maxTime));

		/** @var Comments $model */
		$model = $container->factory->model('Comments')->tmpInstance();

		$spamRemoved = $model->cleanSpam($maxDays, $maxTime);

		if ($spamRemoved)
		{
			$this->out(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_NOSPAM', $maxDays));

			return;
		}

		$this->out(Text::plural('COM_ENGAGE_CRON_SPAM_MSG_DELETED', $spamRemoved));
	}
}

FOFApplicationCLI::getInstance('EngageCleanSpam')->execute();