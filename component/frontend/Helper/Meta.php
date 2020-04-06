<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Helper;

use FOF30\Container\Container;
use Joomla\CMS\Date\Date;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();

final class Meta
{
	/**
	 * Cached results of resource metadata per asset ID
	 *
	 * @var  array
	 */
	private static $cachedMeta = [];

	/**
	 * Returns the metadata of an asset.
	 *
	 * This method goes through the onAkeebaEngageGetAssetMeta plugin event, allowing different plugins to return
	 * information about the asset IDs they recognize. The results are cached to avoid expensive roundtrips to the
	 * Joomla plugin event system and the database.
	 *
	 * The returned keys are:
	 *
	 * * `type`: resource type
	 * * `title`: display title
	 * * `category`: display title for the category / parent item of the resource
	 * * `url`: canonical (frontend) or edit (backend) link for the resource; null if not applicable
	 * * `published`: is the asset published?
	 * * `access`: access level for the resource (e.g. article) this asset ID corresponds to; null if it doesn't apply.
	 * * `parent_access`: access level for the resource's parent (e.g. article category); null if it doesn't apply.
	 *
	 * @param   int  $assetId
	 *
	 * @return array
	 */
	public static function getAssetAccessMeta(int $assetId = 0, bool $loadParameters = false): array
	{
		$metaKey    = md5($assetId . '_' . ($loadParameters ? 'with' : 'without') . '_parameters');
		$altMetaKey = md5($assetId . '_with_parameters');

		if (array_key_exists($metaKey, self::$cachedMeta))
		{
			return self::$cachedMeta[$metaKey];
		}

		if (array_key_exists($altMetaKey, self::$cachedMeta))
		{
			return self::$cachedMeta[$altMetaKey];
		}

		self::$cachedMeta[$metaKey] = [
			'type'          => 'unknown',
			'title'         => '',
			'category'      => null,
			'url'           => null,
			'published'     => false,
			'published_on'  => new Date(),
			'access'        => 0,
			'parent_access' => null,
			'parameters'    => new Registry(),
		];

		$container = Container::getInstance('com_engage');
		$platform  = $container->platform;

		$platform->importPlugin('content');
		$pluginResults = $platform->runPlugins('onAkeebaEngageGetAssetMeta', [$assetId, $loadParameters]);

		$pluginResults = array_filter($pluginResults, function ($x) {
			return is_array($x);
		});

		if (empty($pluginResults))
		{
			return self::$cachedMeta[$metaKey];
		}

		$tempRet = array_shift($pluginResults);

		foreach (self::$cachedMeta[$metaKey] as $k => $v)
		{
			if (!array_key_exists($k, $tempRet))
			{
				continue;
			}

			self::$cachedMeta[$metaKey][$k] = $tempRet[$k] ?? $v;
		}

		return self::$cachedMeta[$metaKey];
	}
}