<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Try to get the path to the Joomla! installation
$joomlaPath = $_SERVER['HOME'] . '/Sites/dev3';

if (isset($_SERVER['JOOMLA_SITE']) && is_dir($_SERVER['JOOMLA_SITE']))
{
	$joomlaPath = $_SERVER['JOOMLA_SITE'];
}

if (!is_dir($joomlaPath))
{
	echo <<< TEXT


CONFIGURATION ERROR

Your configured path to the Joomla site does not exist. Rector requires loading
core Joomla classes to operate properly.

Please set the JOOMLA_SITE environment variable before running Rector.

Example:

JOOMLA_SITE=/var/www/joomla rector process $(pwd) --config rector.yaml \
  --dry-run

I will now error out. Bye-bye!

TEXT;

	throw new InvalidArgumentException("Invalid Joomla site root folder.");
}

// Required to run the boilerplate FOF CLI code
$originalDirectory = getcwd();
chdir($joomlaPath . '/cli');

// Setup and import the base CLI script
$minphp = '7.1.0';

// Boilerplate -- START
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

require_once JPATH_LIBRARIES . '/fof30/Cli/Application.php';
// Boilerplate -- END

// Undo the temporary change for the FOF CLI boilerplate code
chdir($originalDirectory);

// Load FOF 3
if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
{
	throw new RuntimeException('FOF 3.0 is not installed', 500);
}

// Load the component's autoloader through FOF 3
$container = FOF30\Container\Container::getInstance('com_engage');

// Autoload classes which do not conform to Joomla auto-loading
$autoloader = include(__DIR__ . '/../component/backend/vendor/autoload.php');

// -- Auto-add plugins classes to the autoloader
foreach ((new DirectoryIterator(__DIR__ . '/../plugins')) as $folderEntry)
{
	if (!$folderEntry->isDir() || $folderEntry->isDot())
	{
		continue;
	}

	$folder = $folderEntry->getFilename();

	foreach ((new DirectoryIterator($folderEntry->getRealPath())) as $pluginEntry)
	{
		if (!$folderEntry->isDir() || $folderEntry->isDot())
		{
			continue;
		}

		$plugin      = $pluginEntry->getFilename();
		$pluginClass = 'plg' . ucfirst($folder) . ucfirst($plugin);

		$autoloader->addClassMap([
			$pluginClass                     => sprintf("%s/%s.php", $pluginEntry->getRealPath(), $plugin),
			$pluginClass . 'InstallerScript' => sprintf("%s/script.php", $pluginEntry->getRealPath()),
		]);
	}
}

// -- Anything else
$autoloader->addClassMap([
	# Form fields
	'JFormFieldModuleModules'   => __DIR__ . '/../component/backend/fields/modulemodules.php',
	# Post-installation scripts, package and component
	'Pkg_EngageInstallerScript' => __DIR__ . '/../component/script.engage.php',
	'Com_EngageInstallerScript' => __DIR__ . '/../component/script.com_engage.php',
]);