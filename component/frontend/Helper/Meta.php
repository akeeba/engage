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
	public static function getAssetMeta(int $assetId = 0)
	{
		$ret = [
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
			return $ret;
		}

		$tempRet = array_shift($pluginResults);

		foreach ($ret as $k => $v)
		{
			if (!array_key_exists($k, $tempRet))
			{
				continue;
			}

			$ret[$k] = $tempRet[$k] ?? $v;
		}

		return $ret;
	}
}