<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
defined('_JEXEC') or die();

use FOF30\Container\Container;

define('AKEEBA_COMMON_WRONGPHP', 1);
$minPHPVersion         = '7.2.0';
$recommendedPHPVersion = '7.4';
$softwareName          = 'Akeeba Engage';

if (!require_once(__DIR__ . '/View/wrongphp.php'))
{
	return;
}

// HHVM made sense in 2013, now PHP 7 is a way better solution than an hybrid PHP interpreter
if (defined('HHVM_VERSION'))
{
	(include_once __DIR__ . '/View/hhvm.php') or die('We have detected that you are running HHVM instead of PHP. This software WILL NOT WORK properly on HHVM. Please switch to PHP 7 instead.');

	return;
}

// So, FEF is not installed?
if (!@file_exists(JPATH_SITE . '/media/fef/fef.php'))
{
	(include_once __DIR__ . '/View/fef.php') or die('You need to have the Akeeba Frontend Framework (FEF) package installed on your site to display this component. Please visit https://www.akeeba.com/download/official/fef.html to download it and install it on your site.');

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
		(include_once __DIR__ . '/View/fof.php') or die('You need to have the Akeeba Framework-on-Framework (FOF) 3 package installed on your site to use this component. Please visit https://www.akeeba.com/download/fof3.html to download it and install it on your site.');

		return;
	}

	Container::getInstance('com_engage')->dispatcher->dispatch();
}
catch (Throwable $e)
{
	$title = 'Akeeba Engage';
	$isPro = defined(AKENGAGE_PRO) ? AKENGAGE_PRO : false;

	if (!(include_once __DIR__ . '/View/errorhandler.php'))
	{
		throw $e;
	}
}
