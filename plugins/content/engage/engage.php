<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use FOF30\Container\Container;
use FOF30\Input\Input;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Router;
use Joomla\Registry\Registry;

/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
class plgContentEngage extends CMSPlugin
{
	/**
	 * Should this plugin be allowed to run?
	 *
	 * If the runtime dependencies are not met the plugin effectively self-disables even if it's published. This
	 * prevents a WSOD should the user e.g. uninstall a library or the component without unpublishing the plugin first.
	 *
	 * @var  bool
	 */
	private $enabled = true;

	/**
	 * The Akeeba Engage component container
	 *
	 * @var  Container|null
	 */
	private $container;

	private $cachedArticles = [];

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *
	 * @return  void
	 */
	public function __construct(&$subject, $config = [])
	{
		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			$this->enabled = false;
		}

		if (!ComponentHelper::isEnabled('com_engage'))
		{
			$this->enabled = false;
		}

		parent::__construct($subject, $config);

		$this->loadLanguage();
	}

	public function onContentPrepare(?string $context, &$row, &$params, ?int $page = 0): bool
	{
		// We need to be enabled
		if (!$this->enabled)
		{
			return true;
		}

		// We need to be given the right kind of data
		if (!is_object($params) || !($params instanceof Registry) || !is_object($row))
		{
			return true;
		}

		// We need to be in the frontend of the site
		$container = $this->getContainer();

		if (!$container->platform->isFrontend())
		{
			return true;
		}

		// We need to have a supported context
		if ($context !== 'com_content.article')
		{
			return true;
		}

		/**
		 * Set a flag that we're allowed to show the comments browse page.
		 *
		 * Since this is a container flag it can only be set by backend code, not any request parameter. Since this is
		 * not set by default it means that the only way to access the comments is going through this plugin. In other
		 * words, someone trying to bypass the plugin and display all comments regardless would be really disappointed
		 * at the results of their plan to surreptitiously pull comments.
		 */
		$container['commentsBrowseEnablingFlag'] = true;

		$input   = $container->input;
		$assetId = $row->asset_id;

		$input->set('asset_id', $assetId);

		// Capture the output instead of pushing it to the browser
		try
		{
			@ob_start();

			$container->dispatcher->dispatch();

			$comments = @ob_get_contents();

			@ob_end_clean();
		}
		catch (Exception $e)
		{
			$comments = '';
		}

		$row->text .= $comments;

		return true;
	}

	/**
	 * Returns the asset access information for an asset recognized by this plugin
	 *
	 * @param   int  $assetId
	 *
	 * @return  array|null  Asset access information. NULL when the asset is invalid or not recognized.
	 */
	public function onAkeebaEngageGetAssetMeta(int $assetId = 0): ?array
	{
		$row = $this->getArticleByAssetId($assetId);

		if (is_null($row))
		{
			return null;
		}

		// Get the link to the article
		$container = Container::getInstance('com_engage');
		$url       = '';

		if ($container->platform->isFrontend())
		{
			$router = Router::getInstance('site');
			$url    = $router->build('index.php?option=com_content&view=article&id=' . $row->id);
		}
		elseif ($container->platform->isBackend())
		{
			$url = 'index.php?option=com_content&task=article.edit&id=' . $row->id;
		}

		return [
			'type'          => 'article',
			'published'     => $this->isRowPublished($row),
			'access'        => $row->access ?? 0,
			'parent_access' => $row->category_access,
			'title'         => $row->title,
			'category'      => $row->category_title,
			'url'           => $url,

		];
	}

	/**
	 * Get the Akeeba Engage container, preloaded for comments display
	 *
	 * @return  Container
	 */
	private function getContainer(): Container
	{
		if (empty($this->container))
		{
			$this->container = Container::getInstance('com_engage', [
				'tempInstance' => true,
				'input'        => [
					'view'                => 'Comments',
					'task'                => 'browse',
					'asset_id'            => 0,
					'access'              => 0,
					'akengage_limitstart' => (new Input())->getInt('akengage_limitstart', 0),
					'layout'              => null,
					'tpl'                 => null,
					'tmpl'                => null,
					'format'              => 'html',
				],
			], 'site');
		}

		return $this->container;
	}

	/**
	 * Is this article published?
	 *
	 * This takes into account the publish_up and publish_down dates, not just the publish state.
	 *
	 * @param   stdClass  $row  The article object returned by ContentModelArticle
	 *
	 * @return  bool
	 */
	private function isRowPublished($row)
	{
		// The article is unpublished, the point is moot
		if ($row->state <= 0)
		{
			return false;
		}

		$db = Factory::getDbo();

		// Do we have a publish up date?
		if (!empty($row->publish_up) && ($row->publish_up != $db->getNullDate()))
		{
			try
			{
				$publishUp = new Joomla\CMS\Date\Date($row->publish_up);

				if ($publishUp->toUnix() > time())
				{
					return false;
				}
			}
			catch (Exception $e)
			{
			}
		}

		// Do we have a publish down date?
		if (!empty($row->publish_down) && ($row->publish_down != $db->getNullDate()))
		{
			try
			{
				$publishDown = new Joomla\CMS\Date\Date($row->publish_down);

				if ($publishDown->toUnix() < time())
				{
					return false;
				}
			}
			catch (Exception $e)
			{
			}
		}

		return true;
	}

	/**
	 * Returns article information based on the asset ID.
	 *
	 * It will try to use cached results to avoid expensive trips to the database.
	 *
	 * @param   int  $assetId  The asset ID to use
	 *
	 * @return  object|null  Partial article information. NULL when there is no article associated with this asset ID.
	 */
	private function getArticleByAssetId(int $assetId)
	{
		if (isset($this->cachedArticles[$assetId]))
		{
			return $this->cachedArticles[$assetId];
		}

		$this->cachedArticles[$assetId] = null;

		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->qn('id'),
			])
			->from($db->qn('#__content'))
			->where($db->qn('asset_id') . ' = ' . $db->q($assetId));
		try
		{
			$articleId = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			$articleId = null;
		}

		if (is_null($articleId))
		{
			return null;
		}

		/** @var ContentModelArticle $model */
		try
		{
			$model = JModelLegacy::getInstance('Article', 'ContentModel');
			$row   = $model->getItem($articleId);
		}
		catch (Exception $e)
		{
			$row = null;
		}

		if (is_null($row) || ($row === false) || is_object($row) && ($row instanceof Throwable))
		{
			return null;
		}

		if (!is_object($row))
		{
			return null;
		}

		$this->cachedArticles[$assetId] = (object) [
			'id'              => $row->id,
			'asset_id'        => $row->asset_id,
			'title'           => $row->title,
			'alias'           => $row->alias,
			'state'           => $row->state,
			'catid'           => $row->catid,
			'publish_up'      => $row->publish_up,
			'publish_down'    => $row->publish_down,
			'access'          => $row->access,
			'category_title'  => $row->category_title,
			'category_alias'  => $row->category_alias,
			'category_access' => $row->category_access,
		];

		return $this->cachedArticles[$assetId];
	}
}