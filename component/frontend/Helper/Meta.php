<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Helper;

use FOF30\Container\Container;

defined('_JEXEC') or die();

final class Meta
{
	private static $cachedMeta = [];

	public static function getAssetMeta(int $assetId = 0)
	{
		if (array_key_exists($assetId, self::$cachedMeta))
		{
			return self::$cachedMeta[$assetId];
		}

		self::$cachedMeta[$assetId] = [
			'published'   => false,
			'access'      => 0,
		];

		$container = Container::getInstance('com_engage');
		$platform  = $container->platform;

		$platform->importPlugin('content');
		$pluginResults = $platform->runPlugins('onAkeebaEngageGetAssetAccess', [$assetId]);

		$pluginResults = array_filter($pluginResults, function ($x) {
			return is_array($x);
		});

		if (empty($pluginResults))
		{
			return self::$cachedMeta[$assetId];
		}

		$tempRet = array_shift($pluginResults);

		foreach (self::$cachedMeta[$assetId] as $k => $v)
		{
			if (!array_key_exists($k, $tempRet))
			{
				continue;
			}

			self::$cachedMeta[$assetId][$k] = $tempRet[$k] ?? $v;
		}

		return self::$cachedMeta[$assetId];
	}
}