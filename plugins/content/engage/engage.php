<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Engage\Admin\Model\Comments;
use Akeeba\Engage\Site\Helper\Meta;
use FOF30\Container\Container;
use FOF30\Input\Input;
use FOF30\Layout\LayoutHelper;
use FOF30\Utils\CacheCleaner;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Table;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Registry\Registry;

/**
 * Akeeba Engage – Configure and show comments in Joomla core content (articles) and categories
 *
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
class plgContentEngage extends CMSPlugin
{
	/**
	 * Database driver
	 *
	 * @var   JDatabaseDriver|null
	 */
	protected $db;

	/**
	 * Application objecy
	 *
	 * @var   CMSApplication
	 */
	protected $app;

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

	/**
	 * A cache of basic article information keyed to the asset ID
	 *
	 * @var  object[]
	 */
	private $cachedArticles = [];

	/**
	 * The keys to the settings known to Akeeba Engage (see forms/engage.xml)
	 *
	 * @var  string[]
	 */
	private $parametersKeys = [];

	/**
	 * Default values of the component's parameters
	 *
	 * @var  array
	 */
	private $parameterDefaults = [];

	/**
	 * Cache of parameters per article ID
	 *
	 * @var  array
	 */
	private $parametersCache = [];

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

		$this->autoCleanSpam();
	}

	public function onContentBeforeDisplay(?string $context, &$row, &$params, ?int $page = 0): string
	{
		return $this->renderCommentCount($params, $row, $context, true);
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
		return
			$this->renderCommentCount($params, $row, $context, false) .
			$this->renderComments($params, $row, $context);
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
		if (!in_array($form->getName(), ['com_categories.categorycom_content', 'com_content.article']))
		{
			return true;
		}

		// Add the registration fields to the form.
		Form::addFormPath(__DIR__ . '/forms');
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
	public function onContentBeforeSave(?string $context, $table, $isNew = false, $data = null)
	{
		if (!in_array($context, ['com_categories.category', 'com_content.article']))
		{
			return true;
		}

		if (!isset($data['engage']))
		{
			return true;
		}

		$key = ($context === 'com_categories.category') ? 'params' : 'attribs';

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['engage' => $data['engage']]));

		return true;
	}

	/**
	 * Executes after Joomla deleted a content item. Used to delete attached comments.
	 *
	 * @param   string|null           $context
	 * @param   Content|object|mixed  $data
	 *
	 * @return  void
	 *
	 * @see     https://docs.joomla.org/Plugin/Events/Content#onContentAfterDelete
	 */
	public function onContentAfterDelete(?string $context, $data)
	{
		if ($context != 'com_content.article')
		{
			return;
		}

		if (!is_object($data))
		{
			return;
		}

		if (!property_exists($data, 'asset_id'))
		{
			return;
		}

		$assetId = $data->asset_id;
		$db      = $this->db;
		$query   = $db->getQuery(true)
			->delete($db->qn('#__engage_comments'))
			->where($db->qn('asset_id') . ' = ' . $db->q($assetId));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// It's not the end of the world if this fails
		}
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Engage configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   string|null  $context  Context for the content being loaded
	 * @param   object       $data     Data being saved
	 *
	 * @return  bool
	 */
	public function onContentPrepareData(?string $context, &$data)
	{
		if (!in_array($context, ['com_categories.category', 'com_content.article']))
		{
			return true;
		}

		$key = ($context === 'com_categories.category') ? 'params' : 'attribs';

		if (!isset($data->{$key}) || !isset($data->{$key}['engage']))
		{
			return true;
		}

		$data->engage = $data->{$key}['engage'];
		unset ($data->{$key}['engage']);
	}

	/**
	 * Returns the asset access information for an asset recognized by this plugin
	 *
	 * @param   int   $assetId         The asset ID to get the information for
	 * @param   bool  $loadParameters  Should I load the comment parameters? (It's slow!)
	 *
	 * @return  array|null  Asset access information. NULL when the asset is invalid or not recognized.
	 */
	public function onAkeebaEngageGetAssetMeta(int $assetId = 0, bool $loadParameters = false): ?array
	{
		if (empty($assetId))
		{
			return null;
		}

		$row = $this->getArticleByAssetId($assetId, $loadParameters);

		if (is_null($row))
		{
			return null;
		}

		// Get the link to the article
		$container = Container::getInstance('com_engage');
		$url       = '';

		$public_url = Route::link('site', sprintf("index.php?option=com_content&view=article&id=%s&catid=%s", $row->id, $row->catid), false, Route::TLS_IGNORE, true);

		if ($container->platform->isFrontend())
		{
			$url = $public_url;
		}
		elseif ($container->platform->isBackend())
		{
			$url = 'index.php?option=com_content&task=article.edit&id=' . $row->id;
		}


		$publishUp = new Date();
		$db        = $this->db;

		if (!empty($row->publish_up) && ($row->publish_up != $db->getNullDate()))
		{
			$publishUp = new Date($row->publish_up);
		}

		return [
			'type'          => 'article',
			'title'         => $row->title,
			'category'      => $row->category_title,
			'author_name'   => $row->author_name,
			'author_email'  => $row->author_email,
			'url'           => $url,
			'public_url'    => $public_url,
			'published'     => $this->isRowPublished($row),
			'published_on'  => $publishUp,
			'access'        => $row->access ?? 0,
			'parent_access' => $row->category_access,
			'parameters'    => $row->parameters,
		];
	}

	public function onAkeebaEngageGetAssetIDsByTitle(?string $filter): ?array
	{
		$filter = trim($filter ?? '');

		if (empty($filter))
		{
			return [];
		}

		if (strpos($filter, '%') === false)
		{
			$filter = '%' . $filter . '%';
		}

		try
		{
			$db    = $this->db;
			$query = $db->getQuery(true)
				->select([$db->qn('asset_id')])
				->from($db->qn('#__content'))
				->where($db->qn('title') . ' LIKE ' . $db->q($filter));

			return $db->setQuery($query)->loadColumn();
		}
		catch (Exception $e)
		{
			return [];
		}
	}

	/**
	 * Triggered when Akeeba Engage cleans the cache after modifying a comment in a way that affects comments display.
	 *
	 * @return  void
	 */
	public function onEngageClearCache()
	{
		/**
		 * We need to clear the com_content cache.
		 *
		 * Sounds a bit too much? Well, this is how Joomla itself does it. For real.
		 *
		 * @see ContentModelArticle::cleanCache()
		 */
		CacheCleaner::clearCacheGroups([
			'com_content',
		], [0]);

	}

	/**
	 * Get the Akeeba Engage container, preloaded for comments display
	 *
	 * @return  Container
	 */
	private function getContainer(): Container
	{
		if (!empty($this->container))
		{
			return $this->container;
		}

		$masterContainer = Container::getInstance('com_engage');
		$appInput        = new Input();

		$defaultListLimit = $masterContainer->params->get('default_limit', 20);
		$defaultListLimit = ($defaultListLimit == -1) ? 20 : $defaultListLimit;

		// Get the container singleton instance
		$this->container = Container::getInstance('com_engage', [
			// We create a temporary instance to avoid messing with the master container's input
			'tempInstance' => true,
			// Passing these objects from the master container optimizes the number of database queries
			'params'       => $masterContainer->params,
			'mediaVersion' => $masterContainer->mediaVersion,
			// Custom input for the temporary container instance
			'input'        => [
				'option'              => 'com_engage',
				'view'                => 'Comments',
				'task'                => 'browse',
				'asset_id'            => 0,
				'access'              => 0,
				'akengage_limitstart' => ($appInput)->getInt('akengage_limitstart', 0),
				'akengage_limit'      => ($appInput)->getInt('akengage_limit', $defaultListLimit),
				'layout'              => $this->isAMP() ? 'amp' : 'default',
				'tpl'                 => null,
				'tmpl'                => null,
				'format'              => 'html',
			],
		]);

		return $this->container;
	}

	/**
	 * WbAMP support. Is this an AMP page?
	 *
	 * @return  bool
	 * @see     https://weeblr.com/documentation/products.wbamp/1/going-further/api/index.html
	 */
	private function isAMP(): bool
	{
		if (!class_exists('\WbAMP'))
		{
			return false;
		}

		try
		{
			return WbAMP::isAMPRequest();
		}
		catch (Throwable $e)
		{
			return false;
		}
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
		if (($row->state ?? 0) <= 0)
		{
			return false;
		}

		$db = $this->db;

		// Do we have a publish up date?
		if (!empty($row->publish_up) && ($row->publish_up != $db->getNullDate()))
		{
			try
			{
				$publishUp = new Date($row->publish_up);

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
				$publishDown = new Date($row->publish_down);

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
	 * @param   int   $assetId         The asset ID to use
	 * @param   bool  $loadParameters  Should I load the comment parameters? (It's slow!)
	 *
	 * @return  object|null  Partial article information. NULL when there is no article associated with this asset ID.
	 */
	private function getArticleByAssetId(int $assetId, bool $loadParameters = false)
	{
		if (empty($assetId))
		{
			return null;
		}

		$metaKey    = md5($assetId . '_' . ($loadParameters ? 'with' : 'without') . '_parameters');
		$altMetaKey = md5($assetId . '_with_parameters');

		if (isset($this->cachedArticles[$metaKey]))
		{
			return $this->cachedArticles[$metaKey];
		}

		if (isset($this->cachedArticles[$altMetaKey]))
		{
			return $this->cachedArticles[$altMetaKey];
		}

		$this->cachedArticles[$metaKey] = null;

		$db    = $this->db;
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
			if (version_compare(JVERSION, '3.999.999', 'le'))
			{
				if (!class_exists('ContentModelArticle'))
				{
					BaseDatabaseModel::addIncludePath(JPATH_BASE . '/components/com_content/models');
				}

				$model = BaseDatabaseModel::getInstance('Article', 'ContentModel');
			}
			else
			{
				/** @var MVCFactoryInterface $factory */
				$factory = $this->app->bootComponent('com_content')->getMVCFactory();
				/** @var ArticleModel $model */
				$model = $factory->createModel('Article', 'Administrator');
			}

			$row = $model->getItem($articleId);
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

		$this->cacheArticleRow($row, $loadParameters);

		return $this->cachedArticles[$metaKey];
	}

	/**
	 * Get the keys for the per-category and per-article comment parameters.
	 *
	 * These are automatically retrieved from the forms/engage.xml file.
	 *
	 * @return  array
	 */
	private function getParametersKeys(): array
	{
		if (!empty($this->parametersKeys))
		{
			return $this->parametersKeys;
		}

		$form     = new Form('engage_form');
		$formData = file_get_contents(__DIR__ . '/forms/engage.xml');

		if (!$form->load($formData))
		{
			return $this->parametersKeys;
		}

		$fields               = $form->getFieldset('engage');
		$this->parametersKeys = array_map(function ($x) {
			if (substr($x, 0, 7) == 'engage_')
			{
				$x = substr($x, 7);
			}

			return $x;
		}, array_keys($fields));

		return $this->parametersKeys;
	}

	/**
	 * Get the default values for the component parameters.
	 *
	 * This is required to set the value of inherited options when the corresponding component parameter does not have a
	 * concrete value (the user has not yet saved the component's configuration).
	 *
	 * @return  array
	 */
	private function getParameterDefaults(): array
	{
		if (!empty($this->parameterDefaults))
		{
			return $this->parameterDefaults;
		}

		$form     = new Form('engage_component');
		$formData = file_get_contents(JPATH_ADMINISTRATOR . '/components/com_engage/config.xml');
		$formData = str_replace('<config', '<form', $formData);
		$formData = str_replace('</config', '</form', $formData);

		if (!$form->load($formData))
		{
			return $this->parameterDefaults;
		}

		$fieldSets               = $form->getFieldsets();
		$this->parameterDefaults = [];

		foreach (array_keys($fieldSets) as $fieldSet)
		{
			$fields = $form->getFieldset($fieldSet);

			foreach ($fields as $name => $field)
			{
				$this->parameterDefaults[$name] = $field->value ?? null;
			}
		}

		return $this->parameterDefaults;
	}

	/**
	 * Get the comment parameters for an article. This method uses caching whereas getParametersForArticle_Real doesn't.
	 *
	 * Inherited parameters will be retrieved from the category. If the category has inherited parameters they will
	 * retrieved from its parent category. If we exhaust parent categories we will retrieve the inherited parameters
	 * from the component configuration. If the component configuration values are not yet set (e.g. the user has not
	 * yet saved the component's Options page) we will use the default values defined in config.xml.
	 *
	 * @param   object  $row  The article to get the parameters for
	 *
	 * @return  Registry
	 */
	private function getParametersForArticle($row): Registry
	{
		if (!array_key_exists($row->id, $this->parametersCache))
		{
			$this->parametersCache[$row->id] = $this->getParametersForArticle_Real($row);
		}

		return $this->parametersCache[$row->id];
	}

	/**
	 * Get the comment parameters for an article.
	 *
	 * Inherited parameters will be retrieved from the category. If the category has inherited parameters they will
	 * retrieved from its parent category. If we exhaust parent categories we will retrieve the inherited parameters
	 * from the component configuration. If the component configuration values are not yet set (e.g. the user has not
	 * yet saved the component's Options page) we will use the default values defined in config.xml.
	 *
	 * @param   object  $row  The article to get the parameters for
	 *
	 * @return  Registry
	 */
	private function getParametersForArticle_Real($row): Registry
	{
		// Create a comment parameters array consisting of null values
		$parametersKeys = $this->getParametersKeys();
		$ret            = array_combine($parametersKeys, array_fill(
			0, count($parametersKeys), null
		));

		// Populate the comment parameters with those defined in the article
		$articleParams      = new Registry($row->attribs);
		$hasInheritedParams = false;

		foreach ($parametersKeys as $key)
		{
			$ret[$key]          = $articleParams->get('engage.' . $key, $ret[$key]);
			$hasInheritedParams = $hasInheritedParams || $this->isUseGlobal($ret[$key]);
		}

		// If there are no "Use Global" parameters return what we've got so far.
		if (!$hasInheritedParams)
		{
			return new Registry();
		}

		// Go through the categories hierarchy, replacing inherited parameters
		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			if (!class_exists('CategoriesModelCategory'))
			{
				BaseDatabaseModel::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/models');
			}

			if (!class_exists('CategoriesTableCategory'))
			{
				Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/tables');
			}
		}

		$catId = $row->catid;

		if (version_compare(JVERSION, '3.999.999', 'le'))
		{
			/** @var CategoriesModelCategory $model */
			$model = BaseDatabaseModel::getInstance('Category', 'CategoriesModel');
		}
		else
		{
			/** @var MVCFactoryInterface $factory */
			$factory = $this->app->bootComponent('com_categories')->getMVCFactory();
			/** @var CategoryModel $model */
			$model = $factory->createModel('Category', 'Administrator');
		}

		while (true)
		{
			$cat                = $model->getItem($catId);
			$params             = new Registry($cat->params);
			$hasInheritedParams = false;

			foreach ($ret as $k => $v)
			{
				if (!$this->isUseGlobal($v))
				{
					continue;
				}

				$ret[$k]            = $params->get('engage.' . $k, $ret[$k]);
				$hasInheritedParams = $hasInheritedParams || $this->isUseGlobal($ret[$k]);
			}

			if (!$hasInheritedParams)
			{
				return new Registry($ret);
			}

			if (empty($cat->parent_id))
			{
				break;
			}

			$catId = $cat->parent_id;
		}

		// If I still have inherited parameters go through the component parameters
		$defaults = new Registry($this->getParameterDefaults());

		foreach ($ret as $k => $v)
		{
			if (!$this->isUseGlobal($v))
			{
				continue;
			}

			$ret[$k] = $this->getContainer()->params->get($k, $defaults->get($k));
		}

		return new Registry($ret);
	}

	/**
	 * Is the value of a settings field equivalent to "Use Global"?
	 *
	 * This happens if the value if null, an empty string or the integer value -1.
	 *
	 * @param   mixed  $value  The value to check
	 *
	 * @return  bool
	 */
	private function isUseGlobal($value): bool
	{
		if (is_null($value))
		{
			return true;
		}

		if (is_string($value) && (trim($value) === ''))
		{
			return true;
		}

		if (is_numeric($value))
		{
			if (((int) $value) === -1)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * Automatically delete spam comments older than the configured age limit at most once every 8 horus
	 *
	 * @return  void
	 */
	private function autoCleanSpam(): void
	{
		// Run once every 8 hours
		$container = $this->getContainer();
		$cParams   = $container->params;
		$lastRun   = $cParams->get('spam_lastRun', 0);
		$nextRun   = $lastRun + (8 * 3600);

		if ($nextRun > time())
		{
			return;
		}

		// I need to run. Save the current timestamp in the component parameters.
		$cParams->set('spam_lastRun', time());
		$cParams->save();

		// Get the model and delete comments. No problem if we fail for any reason.
		try
		{
			$maxDays = $cParams->get('max_spam_age', 15);
			/** @var Comments $model */
			$model = $container->factory->model('Comments')->tmpInstance();
			$model->cleanSpam($maxDays, 1);
		}
		catch (Exception $e)
		{
			return;
		}
	}

	/**
	 * @param          $row
	 * @param   bool   $loadParameters
	 * @param   bool   $force
	 *
	 * @return  void
	 */
	private function cacheArticleRow($row, bool $loadParameters, bool $force = false): void
	{
		$authorUser = self::getContainer()->platform->getUser($row->created_by);
		$metaKey    = md5($row->asset_id . '_' . ($loadParameters ? 'with' : 'without') . '_parameters');

		if (array_key_exists($metaKey, $this->cachedArticles) && !empty($this->cachedArticles[$metaKey]) && !$force)
		{
			return;
		}

		$this->cachedArticles[$metaKey] = (object) [
			'id'              => $row->id,
			'asset_id'        => $row->asset_id,
			'title'           => $row->title,
			'alias'           => $row->alias,
			'state'           => $row->state,
			'catid'           => $row->catid,
			'attribs'         => $row->attribs,
			'publish_up'      => $row->publish_up,
			'publish_down'    => $row->publish_down,
			'access'          => $row->access,
			'category_title'  => $row->category_title ?? '',
			'category_alias'  => $row->category_alias ?? 0,
			'category_access' => $row->category_access ?? $row->access,
			'author_name'     => !empty($row->created_by_alias) ? $row->created_by_alias : $authorUser->name,
			'author_email'    => $authorUser->email,
			'parameters'      => $loadParameters ? $this->getParametersForArticle($row) : new Registry(),
		];
	}

	/**
	 * Get the asset ID given an article ID
	 *
	 * @param   int|null  $id
	 *
	 * @return  int|null
	 *
	 * @since   1.0.0.b3
	 */
	private function getAssetIdByArticleId(?int $id): ?int
	{
		if (empty($id))
		{
			return null;
		}

		$db    = $this->db;
		$query = $db->getQuery(true)
			->select($db->qn('asset_id'))
			->from($db->qn('#__content'))
			->where($db->qn('id') . ' = ' . $db->q($id));

		$assetId = $db->setQuery($query)->loadResult();

		return $assetId ? ((int) $assetId) : null;
	}

	/**
	 * @param                $params
	 * @param                $row
	 * @param   string|null  $context
	 *
	 * @return false|string
	 */
	private function renderComments($params, $row, ?string $context)
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

		if (empty($row->id ?? 0))
		{
			return '';
		}

		if (!property_exists($row, 'asset_id'))
		{
			$row->asset_id = $this->getAssetIdByArticleId($row->id);
		}

		if (empty($row->asset_id ?? 0))
		{
			return '';
		}

		/**
		 * This neat trick allows me to speed up meta queries on the article.
		 *
		 * When this plugin event is called Joomla has already loaded the article for us. I can use this object to
		 * populate the article meta cache so next time I query the article meta I don't have to go through Joomla's
		 * article model which saves me a very expensive query.
		 */
		$this->cacheArticleRow($row, true);

		// Am I supposed to display comments?
		$commentParams = $this->getParametersForArticle($row);

		if ($commentParams->get('comments_show', 1) != 1)
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
		$input->set('filter_order_Dir', $commentParams->get('comments_ordering'));

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
	 * Render the comments count
	 *
	 * @param   Registry|mixed  $params
	 * @param   object|mixed    $row
	 * @param   string|null     $context
	 * @param   bool            $before  Am I asked to render this before the content?
	 *
	 * @return  string
	 */
	private function renderCommentCount($params, $row, ?string $context, bool $before = true): string
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
		if (!in_array($context, ['com_content.category', 'com_content.featured']))
		{
			return '';
		}

		// Make sure this is really an article
		if (!property_exists($row, 'introtext'))
		{
			return '';
		}

		if (empty($row->id ?? 0))
		{
			return '';
		}

		/**
		 * Joomla does not make the asset_id available when displaying articles in the featured or blog view display
		 * modes. Unfortunately, we need to do one extra DB query for each article in this case.
		 */
		if (!property_exists($row, 'asset_id'))
		{
			$row->asset_id = $this->getAssetIdByArticleId($row->id);
		}

		if (empty($row->asset_id ?? 0))
		{
			return '';
		}

		/**
		 * This neat trick allows me to speed up meta queries on the article.
		 *
		 * When this plugin event is called Joomla has already loaded the article for us. I can use this object to
		 * populate the article meta cache so next time I query the article meta I don't have to go through Joomla's
		 * article model which saves me a very expensive query.
		 */
		$this->cacheArticleRow($row, true);

		// Am I supposed to display comments at all?
		$commentParams = $this->getParametersForArticle($row);
		$showComments  = $commentParams->get('comments_show', 1);

		if ($showComments != 1)
		{
			return '';
		}

		// Am I supposed to display the comments count? Uses the keys comments_show_feature, comments_show_category
		$area              = substr($context, 12);
		$optionKey         = sprintf("comments_show_%s", $area);
		$showCommentsCount = $commentParams->get($optionKey, 0);

		if ($showCommentsCount == 0)
		{
			return '';
		}

		if ($before && ($showCommentsCount != 1))
		{
			return '';
		}

		if (!$before && ($showCommentsCount != 2))
		{
			return '';
		}

		// Use a Layout file to display the appropriate summary
		$basePath    = __DIR__ . '/layouts';
		$layoutFile  = sprintf("akeeba.engage.content.%s", $area);
		$displayData = [
			'container' => $container,
			'row'       => $row,
			'meta'      => Meta::getAssetAccessMeta($row->asset_id),
		];

		return LayoutHelper::render($container, $layoutFile, $displayData, $basePath);
	}
}
