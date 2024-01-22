<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\CliCommand\Mixin;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Uri\Uri;
use ReflectionClass;
use function defined;

defined('_JEXEC') || die;

trait CliRouting
{
	protected function initCliRouting(?string $siteURL = null)
	{
		if (empty($siteURL))
		{
			$cParams = ComponentHelper::getParams('com_engage');
			$siteURL = $cParams->get('siteurl', 'https://www.example.com');
		}

		// Set up the base site URL in JUri
		$uri                    = Uri::getInstance($siteURL);
		$_SERVER['HTTP_HOST']   = $uri->toString(['host', 'port']);
		$_SERVER['REQUEST_URI'] = $uri->getPath();

		$refClass     = new ReflectionClass(Uri::class);
		$refInstances = $refClass->getProperty('instances');

		$refInstances->setAccessible(true);

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$instances = $refClass->getStaticPropertyValue('instances');
		}
		else
		{
			$instances = $refInstances->getValue();
		}

		$instances['SERVER'] = $uri;

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$refClass->setStaticPropertyValue('instances', $instances);
		}
		else
		{
			$refInstances->setValue($instances);
		}

		$base = [
			'prefix' => $uri->toString(['scheme', 'host', 'port']),
			'path'   => rtrim($uri->toString(['path']), '/\\'),
		];

		$refBase = $refClass->getProperty('base');
		$refBase->setAccessible(true);

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$refClass->setStaticPropertyValue('base', $base);
		}
		else
		{
			$refBase->setValue($base);
		}

		// DO NOT REMOVE — This initialises the internal object cache of the CMS Router.
		$siteRouter = version_compare(JVERSION, '4.9999.9999', 'lt') ?
			Router::getInstance('site')
			: Factory::getContainer()->get('SiteRouter');

		$refClass = new ReflectionClass(Route::class);
		$refCache = $refClass->getProperty('_router');

		$refCache->setAccessible(true);

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$cache = $refClass->getStaticPropertyValue('_router');
		}
		else
		{
			$cache = $refCache->getValue();
		}

		$cache['site'] = $siteRouter;
		$cache['cli']  = $siteRouter;

		if (version_compare(PHP_VERSION, '8.3.0', 'ge'))
		{
			$refClass->setStaticPropertyValue('_router', $cache);
		}
		else
		{
			$refCache->setValue($cache);
		}
	}

}