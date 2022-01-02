<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use FOF40\Container\Container;

define('AKEEBA_COMMON_WRONGPHP', 1);
$minPHPVersion         = '7.2.0';
$recommendedPHPVersion = '7.4';
$softwareName          = 'Akeeba Engage';
$silentResults         = true;

if (!require_once(JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/ErrorPages/wrongphp.php'))
{
	return;
}

/**
 * The following code is a neat trick to help us collect the maximum amount of relevant information when a user
 * encounters an unexpected exception or a PHP fatal error. In both cases we capture the generated Throwable and
 * render an error page, making sure that the HTTP response code is set to an appropriate value (4xx or 5xx).
 */
try
{
	if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
	{
		throw new RuntimeException('FOF 4.0 is not installed', 500);
	}

	Container::getInstance('com_engage')->dispatcher->dispatch();
}
catch (Throwable $e)
{
	$title = 'Akeeba Engage';
	$isPro = defined(AKENGAGE_PRO) ? AKENGAGE_PRO : false;

	if (!(include_once JPATH_COMPONENT_ADMINISTRATOR . '/tmpl/ErrorPages/errorhandler.php'))
	{
		throw $e;
	}
}
