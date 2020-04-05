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
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\BaseDatabaseModel as JModelLegacy;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Table\Table;
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

	/**
	 * Returns the content to display after an article. Used to render the comments interface.
	 *
	 * @param   string|null  $context  The context of the content being prepared. We only repond to
	 *                                 'com_content.article'
	 * @param   object       $row      A simple object with the article information
	 * @param   object       $params   The category parameters, computed through the categories' hierarchy
	 * @param   int|null     $page     Page number for multi-page articles
	 *
	 * @return string
	 */
	public function onContentAfterDisplay(?string $context, &$row, &$params, ?int $page = 0): string
	{
		// We need to be enabled
		if (!$this->enabled)
		{
			return '';
		}

		// We need to be given the right kind of data
		if (!is_object($params) || !($params instanceof Registry) || !is_object($row))
		{
			return '';
		}

		// We need to be in the frontend of the site
		$container = $this->getContainer();

		if (!$container->platform->isFrontend())
		{
			return '';
		}

		// We need to have a supported context
		if ($context !== 'com_content.article')
		{
			return '';
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

		return $comments;
	}

	/**
	 * Runs when Joomla is preparing a form. Used to add extra fields to the Category edit page.
	 *
	 * Please note that due to frontend editing this MUST run in both the front- and backend of the site.
	 *
	 * @param   Form    $form  The Joomla Form object we are manipulating
	 * @param   object  $data  The data assigned to the form.
	 *
	 * @return  bool  Always true (we never fail preparing the form)
	 */
	public function onContentPrepareForm(Form $form, $data): bool
	{
		if ($form->getName() != 'com_categories.categorycom_content')
		{
			return true;
		}

		// Add the registration fields to the form.
		JForm::addFormPath(__DIR__ . '/forms');
		$form->loadFile('engage', false);

		return true;
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the Engage configuration.
	 *
	 * @param   string|null   $context  Context for the content being saved
	 * @param   Table|object  $table    Joomla table object where the content is being saved to
	 * @param   bool          $isNew    Is this a new record?
	 * @param   object        $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentBeforeSave(?string $context, &$table, $isNew, $data): bool
	{
		if ($context !== 'com_categories.category')
		{
			return true;
		}

		$params        = @json_decode($table->params, true) ?? [];
		$table->params = json_encode(array_merge($params, ['engage' => $data['engage']]));

		return true;
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Engage configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   string|null   $context  Context for the content being loaded
	 * @param   object        $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentPrepareData(?string $context, &$data)
	{
		if ($context !== 'com_categories.category')
		{
			return true;
		}

		if (!isset($data->params) || !isset($data->params['engage']))
		{
			return true;
		}

		$data->engage = $data->params['engage'];
		unset ($data->params['engage']);
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
			if (!class_exists('ContentModelArticle'))
			{
				JModelLegacy::addIncludePath(JPATH_SITE . '/components/com_content/models');
			}

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