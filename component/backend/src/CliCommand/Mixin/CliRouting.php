<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\CliCommand\Mixin;

use Joomla\CMS\Component\ComponentHelper;
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
		$instances           = $refInstances->getValue();
		$instances['SERVER'] = $uri;
		$refInstances->setValue($instances);

		$base = [
			'prefix' => $uri->toString(['scheme', 'host', 'port']),
			'path'   => rtrim($uri->toString(['path']), '/\\'),
		];

		$refBase = $refClass->getProperty('base');
		$refBase->setAccessible(true);
		$refBase->setValue($base);

		// DO NOT REMOVE — This initialises the internal object cache of the CMS Router.
		$siteRouter = Router::getInstance('site');
		$refClass   = new ReflectionClass(Route::class);
		$refCache   = $refClass->getProperty('_router');
		$refCache->setAccessible(true);
		$cache         = $refCache->getValue();
		$cache['site'] = $siteRouter;
		$cache['cli']  = $siteRouter;
		$refCache->setValue($cache);
	}

}