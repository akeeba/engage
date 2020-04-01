<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

define('AKEEBA_COMMON_WRONGPHP', 1);
$minPHPVersion         = '7.1.0';
$recommendedPHPVersion = '7.3';
$softwareName          = 'Akeeba Engage';
$silentResults         = true;

if (!require_once(JPATH_COMPONENT_ADMINISTRATOR . '/View/wrongphp.php'))
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
	if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
	{
		throw new RuntimeException('FOF 3.0 is not installed', 500);
	}

	FOF30\Container\Container::getInstance('com_engage')->dispatcher->dispatch();
}
catch (Throwable $e)
{
	$title = 'Akeeba Engage';
	$isPro = defined(AKENGAGE_PRO) ? AKENGAGE_PRO : false;

	if (!(include_once JPATH_COMPONENT_ADMINISTRATOR . '/View/errorhandler.php'))
	{
		throw $e;
	}
}