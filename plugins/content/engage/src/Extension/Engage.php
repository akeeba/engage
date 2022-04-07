<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace Joomla\Plugin\Content\Engage\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\CacheCleaner;
use Akeeba\Component\Engage\Administrator\Helper\ComponentParams;
use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Model\CommentsModel;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Exception;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactory;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Table\Content;
use Joomla\CMS\Table\Table;
use Joomla\Component\Categories\Administrator\Model\CategoryModel;
use Joomla\Component\Content\Administrator\Model\ArticleModel;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Input\Input;
use Joomla\Registry\Registry;
use Throwable;

class Engage extends CMSPlugin implements SubscriberInterface
{
	use MVCFactoryAwareTrait;

	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

	/**
	 * Application object
	 *
	 * @var   CMSApplication
	 * @since 1.0.0
	 */
	protected $app;

	/**
	 * Database driver
	 *
	 * @var   DatabaseDriver|null
	 * @since   1.0.0
	 */
	protected $db;

	/**
	 * A cache of basic article information keyed to the asset ID
	 *
	 * @var   object[]
	 * @since 1.0.0
	 */
	private $cachedArticles = [];

	/**
	 * Component Dispatcher factory for the Akeeba Engage component
	 *
	 * @var   ComponentDispatcherFactory
	 * @since 3.0.0
	 */
	private $comDispatcherFactory;

	/**
	 * MVC Factory for the Akeeba Engage component
	 *
	 * @var   MVCFactory
	 * @since 3.0.0
	 */
	private $mvcFactory;

	/**
	 * Default values of the component's parameters
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $parameterDefaults = [];

	/**
	 * Cache of parameters per article ID
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	private $parametersCache = [];

	/**
	 * The keys to the settings known to Akeeba Engage (see forms/engage.xml)
	 *
	 * @var   string[]
	 * @since 1.0.0
	 */
	private $parametersKeys = [];

	/**
	 * Constructor
	 *
	 * @param   DispatcherInterface  &$subject  The object to observe
	 * @param   array                 $config   An optional associative array of configuration settings.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function __construct(&$subject, $config, MVCFactoryInterface $MVCFactory, ComponentDispatcherFactory $comDispatcherFactory)
	{
		parent::__construct($subject, $config);

		$this->setMVCFactory($MVCFactory);

		$this->comDispatcherFactory = $comDispatcherFactory;

		$this->loadLanguage();

		$this->autoCleanSpam();
	}

	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_engage'))
		{
			return [];
		}

		return [
			'onAkeebaEngageGetAssetIDsByTitle' => 'onAkeebaEngageGetAssetIDsByTitle',
			'onAkeebaEngageGetAssetMeta'       => 'onAkeebaEngageGetAssetMeta',
			'onContentAfterDelete'             => 'onContentAfterDelete',
			'onContentAfterDisplay'            => 'onContentAfterDisplay',
			'onContentBeforeDisplay'           => 'onContentBeforeDisplay',
			'onContentBeforeSave'              => 'onContentBeforeSave',
			'onContentPrepareData'             => 'onContentPrepareData',
			'onContentPrepareForm'             => 'onContentPrepareForm',
			'onEngageClearCache'               => 'onEngageClearCache',
		];
	}

	/**
	 * Get the asset IDs matching a partial article title
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function onAkeebaEngageGetAssetIDsByTitle(Event $event): void
	{
		/**
		 * @var ?string $filter The partial article name to match.
		 */
		[$filter] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$filter = trim($filter ?? '');

		if (empty($filter))
		{
			$event->setArgument('result', array_merge($result, []));

			return;
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

			$ret = $db->setQuery($query)->loadColumn();
		}
		catch (Exception $e)
		{
			$ret = [];
		}

		$event->setArgument('result', array_merge($result, $ret));
	}

	/**
	 * Returns the asset access information for an asset recognized by this plugin
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onAkeebaEngageGetAssetMeta(Event $event): void
	{
		/**
		 * @var   int  $assetId        The asset ID to get the information for
		 * @var   bool $loadParameters Should I load the comment parameters? (It's slow!)
		 */
		[$assetId, $loadParameters] = $event->getArguments();
		$result = $event->getArgument('result', []);

		if (empty($assetId))
		{
			$event->setArgument('result', array_merge($result, [null]));

			return;
		}

		$row = $this->getArticleByAssetId($assetId, $loadParameters);

		if (is_null($row))
		{
			$event->setArgument('result', array_merge($result, [null]));

			return;
		}

		// Get the link to the article
		$url = '';

		$nonSefUrl  = "index.php?option=com_content&view=article&id=%s&catid=%s";
		$nonSefUrl  .= empty($row->language) ? '' : "&lang={$row->language}";
		$public_url = Route::link('site', sprintf($nonSefUrl, $row->id, $row->catid), false, Route::TLS_IGNORE, true);

		if ($this->app->isClient('site'))
		{
			$url = $public_url;
		}
		elseif ($this->app->isClient('administrator'))
		{
			$url = 'index.php?option=com_content&task=article.edit&id=' . $row->id;
		}

		$publishUp = new Date();
		$db        = $this->db;

		if (!empty($row->publish_up) && ($row->publish_up != $db->getNullDate()))
		{
			$publishUp = new Date($row->publish_up);
		}

		$event->setArgument('result', array_merge($result, [
			[
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
			],
		]));
	}

	/**
	 * Executes after Joomla deleted a content item. Used to delete attached comments.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 * @see     https://docs.joomla.org/Plugin/Events/Content#onContentAfterDelete
	 */
	public function onContentAfterDelete(Event $event): void
	{
		/**
		 * @var   string|null          $context
		 * @var   Content|object|mixed $data
		 */
		[$context, $data] = $event->getArguments();
		$result = $event->getArgument('result', []);

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
	 * Returns the content to display after an article. Used to render the comments interface.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentAfterDisplay(Event $event): void
	{
		/**
		 * @var   string|null $context The context of the content being prepared. We only respond to
		 *                                 'com_content.article'
		 * @var   object      $row     A simple object with the article information
		 * @var   object      $params  The category parameters, computed through the categories' hierarchy
		 * @var   int|null    $page    Page number for multi-page articles
		 */
		[$context, $row, $params, $page] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$event->setArgument('result', array_merge($result, [
			$this->renderCommentCount($params, $row, $context, false) .
			$this->renderComments($params, $row, $context),
		]));
	}

	/**
	 * Returns the content to display after an article. Used to render the comments count.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentBeforeDisplay(Event $event): void
	{
		/**
		 * @var   string|null $context The context of the content being prepared. We only respond to
		 *                                 'com_content.article'
		 * @var   object      $row     A simple object with the article information
		 * @var   object      $params  The category parameters, computed through the categories' hierarchy
		 * @var   int|null    $page    Page number for multi-page articles
		 */
		[$context, $row, $params, $page] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$event->setArgument('result', array_merge($result, [
			$this->renderCommentCount($params, $row, $context, true),
		]));
	}

	/**
	 * Triggered when Joomla is saving content. Used to save the Engage configuration.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentBeforeSave(Event $event): void
	{
		/**
		 * @var   string|null  $context Context for the content being saved
		 * @var   Table|object $table   Joomla table object where the content is being saved to
		 * @var   bool         $isNew   Is this a new record?
		 * @var   object       $data    Data being saved
		 */
		[$context, $table, $isNew, $data] = $event->getArguments();
		$result = $event->getArgument('result', []);

		$event->setArgument('result', array_merge($result, [
			true,
		]));

		if (!in_array($context, ['com_categories.category', 'com_content.article']))
		{
			return;
		}

		if (!isset($data['engage']))
		{
			return;
		}

		$key = ($context === 'com_categories.category') ? 'params' : 'attribs';

		$params        = @json_decode($table->{$key}, true) ?? [];
		$table->{$key} = json_encode(array_merge($params, ['engage' => $data['engage']]));
	}

	/**
	 * Triggered when Joomla is loading content. Used to load the Engage configuration.
	 *
	 * This is used for both articles and article categories.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareData(Event $event)
	{
		/**
		 * @var   string|null $context Context for the content being loaded
		 * @var   object      $data    Data being saved
		 */
		[$context, $data] = $event->getArguments();
		$result = $event->getArgument('result', []);
		$event->setArgument('result', array_merge($result, [
			true,
		]));

		if (!in_array($context, ['com_categories.category', 'com_content.article']))
		{
			return;
		}

		$key = ($context === 'com_categories.category') ? 'params' : 'attribs';

		if (!isset($data->{$key}) || !isset($data->{$key}['engage']))
		{
			return;
		}

		$data->engage = $data->{$key}['engage'];
		unset ($data->{$key}['engage']);
	}

	/**
	 * Runs when Joomla is preparing a form. Used to add extra fields to the Category edit page.
	 *
	 * Please note that due to frontend editing this MUST run in both the front- and backend of the site.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function onContentPrepareForm(Event $event): void
	{
		/**
		 * @var   Form   $form The Joomla Form object we are manipulating
		 * @var   object $data The data assigned to the form.
		 */
		[$form, $data] = $event->getArguments();
		$result = $event->getArgument('result', []);
		$event->setArgument('result', array_merge($result, [
			true,
		]));

		if (!in_array($form->getName(), ['com_categories.categorycom_content', 'com_content.article']))
		{
			return;
		}

		// Add the registration fields to the form.
		Form::addFormPath(__DIR__ . '/../..//forms');
		$form->loadFile('engage', false);
	}

	/**
	 * Triggered when Akeeba Engage cleans the cache after modifying a comment in a way that affects comments display.
	 *
	 * @param   Event  $event
	 *
	 * @return  void
	 * @throws  Exception
	 * @since        1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function onEngageClearCache(Event $event): void
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
	 * Automatically delete spam comments older than the configured age limit at most once every 8 horus
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function autoCleanSpam(): void
	{
		// Run once every 8 hours
		$cParams = ComponentHelper::getParams('com_engage');
		$lastRun = $cParams->get('spam_lastRun', 0);
		$nextRun = $lastRun + (8 * 3600);

		if ($nextRun > time())
		{
			return;
		}

		// I need to run. Save the current timestamp in the component parameters.
		$cParams->set('spam_lastRun', time());

		ComponentParams::save($cParams);

		// Get the model and delete comments. No problem if we fail for any reason.
		try
		{
			$maxDays = $cParams->get('max_spam_age', 15);
			/** @var CommentsModel $model */
			$model = $this->getMVCFactory()->createModel('Comments', 'Administrator', ['ignore_request' => true]);

			$model->cleanSpam($maxDays, 1);
		}
		catch (Exception $e)
		{
			return;
		}
	}

	/**
	 * Cache an article's row in memory
	 *
	 * @param   object  $row
	 * @param   bool    $loadParameters
	 * @param   bool    $force
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	private function cacheArticleRow($row, bool $loadParameters, bool $force = false): void
	{
		$authorUser = UserFetcher::getUser($row->created_by);
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
			'language'        => $row->language ?? '*',
			'category_title'  => $row->category_title ?? '',
			'category_alias'  => $row->category_alias ?? 0,
			'category_access' => $row->category_access ?? $row->access,
			'author_name'     => !empty($row->created_by_alias) ? $row->created_by_alias : $authorUser->name,
			'author_email'    => $authorUser->email,
			'parameters'      => $loadParameters ? $this->getParametersForArticle($row) : new Registry(),
		];
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
	 * @since   1.0.0
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

		try
		{
			/** @var MVCFactoryInterface $factory */
			$factory = $this->app->bootComponent('com_content')->getMVCFactory();
			/** @var ArticleModel $model */
			$model = $factory->createModel('Article', 'Administrator');

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

		/** @noinspection PhpExpressionAlwaysNullInspection */
		return $this->cachedArticles[$metaKey];
	}

	/**
	 * Get the asset ID given an article ID
	 *
	 * @param   int|null  $id
	 *
	 * @return  int|null
	 *
	 * @since   1.0.0
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
	 * Get the default values for the component parameters.
	 *
	 * This is required to set the value of inherited options when the corresponding component parameter does not have a
	 * concrete value (the user has not yet saved the component's configuration).
	 *
	 * @return  array
	 * @since   1.0.0
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
	 * @since   1.0.0
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
	 * @since   1.0.0
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

		$catId = $row->catid;

		/** @var MVCFactoryInterface $factory */
		$factory = $this->app->bootComponent('com_categories')->getMVCFactory();
		/** @var CategoryModel $model */
		$model = $factory->createModel('Category', 'Administrator');

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
		$cParams  = ComponentHelper::getParams('com_engage');

		foreach ($ret as $k => $v)
		{
			if (!$this->isUseGlobal($v))
			{
				continue;
			}

			$ret[$k] = $cParams->get($k, $defaults->get($k));
		}

		return new Registry($ret);
	}

	/**
	 * Get the keys for the per-category and per-article comment parameters.
	 *
	 * These are automatically retrieved from the forms/engage.xml file.
	 *
	 * @return  array
	 * @since   1.0.0
	 */
	private function getParametersKeys(): array
	{
		if (!empty($this->parametersKeys))
		{
			return $this->parametersKeys;
		}

		$form     = new Form('engage_form');
		$formData = file_get_contents(__DIR__ . '/../../forms/engage.xml');

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
	 * Is this article published?
	 *
	 * This takes into account the publish_up and publish_down dates, not just the publish state.
	 *
	 * @param   object  $row  The article object returned by ContentModelArticle
	 *
	 * @return  bool
	 * @since   1.0.0
	 */
	private function isRowPublished(object $row): bool
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
	 * Is the value of a settings field equivalent to "Use Global"?
	 *
	 * This happens if the value if null, an empty string or the integer value -1.
	 *
	 * @param   mixed  $value  The value to check
	 *
	 * @return  bool
	 * @since   1.0.0
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
	 * Render the comments count
	 *
	 * @param   Registry|mixed  $params
	 * @param   object|mixed    $row
	 * @param   string|null     $context
	 * @param   bool            $before  Am I asked to render this before the content?
	 *
	 * @return  string
	 * @since   1.0.0
	 */
	private function renderCommentCount($params, $row, ?string $context, bool $before = true): string
	{
		// We need to be given the right kind of data
		if (!is_object($params) || !($params instanceof Registry) || !is_object($row))
		{
			return '';
		}

		// We need to be in the frontend of the site
		if (!$this->app->isClient('site'))
		{
			return '';
		}

		/**
		 * When Joomla is rendering an article in a Newsflash module it uses the same context as rendering an article
		 * through com_content (com_content.article). However, we do NOT want the newsflash articles to display comments!
		 *
		 * This is an ugly hack around this problem. It's based on the observation that the newsflash module is passing
		 * its own module options in the $params parameter to this event. As a result it has the `moduleclass_sfx` key
		 * defined, whereas this key does not exist when rendering an article through com_content.
		 */
		$isNewsFlash = ($context === 'com_content.article') && ($params instanceof Registry) && $params->exists('moduleclass_sfx');

		// We need to have a supported context
		if (!$isNewsFlash && !in_array($context, ['com_content.category', 'com_content.featured']))
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

		/**
		 * Am I supposed to display the comments count?
		 *
		 * Uses the keys comments_show_feature, comments_show_category, comments_show_article
		 */
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
		$basePath    = __DIR__ . '/../../layouts';
		$layoutFile  = sprintf("akeeba.engage.content.%s", $area);
		$displayData = [
			'app'   => $this->app,
			'model' => $this->getMVCFactory()->createModel('Comments', 'Administrator', ['ignore_request' => true]),
			'row'   => $row,
			'meta'  => Meta::getAssetAccessMeta($row->asset_id),
		];

		return LayoutHelper::render($layoutFile, $displayData, $basePath);
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
		// We need to be given the right kind of data
		if (!is_object($params) || !($params instanceof Registry) || !is_object($row))
		{
			return '';
		}

		// We need to be in the frontend of the site
		if (!$this->app->isClient('site'))
		{
			return '';
		}

		// We need to have a supported context
		if ($context !== 'com_content.article')
		{
			return '';
		}

		/**
		 * When Joomla is rendering an article in a Newsflash module it uses the same context as rendering an article
		 * through com_content (com_content.article). However, we do NOT want the newsflash articles to display comments!
		 *
		 * This is an ugly hack around this problem. It's based on the observation that the newsflash module is passing
		 * its own module options in the $params parameter to this event. As a result it has the `moduleclass_sfx` key
		 * defined, whereas this key does not exist when rendering an article through com_content.
		 */
		if (($params instanceof Registry) && $params->exists('moduleclass_sfx'))
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

		$input   = new Input($this->app->input->getArray());
		$assetId = $row->asset_id;

		$input->set('option', 'com_engage');
		$input->set('view', 'comments');
		$input->set('task', 'main');
		$input->set('controller', null);
		$input->set('layout', null);
		$input->set('asset_id', $assetId);
		$input->set('akengage_order_Dir', $commentParams->get('comments_ordering'));

		// Is debug mode enabled?
		$debug = defined('JDEBUG') && boolval(JDEBUG);

		// Capture the output instead of pushing it to the browser
		@ob_start();

		try
		{
			/** @var HtmlDocument $doc */
			$doc = Factory::getApplication()->getDocument();

			$doc->getWebAssetManager()->getRegistry()->addRegistryFile('media/com_engage/joomla.asset.json');

			$this->comDispatcherFactory->createDispatcher($this->app, $input)->setUseErrorHandler($debug)->dispatch();

			$comments = @ob_get_contents();
		}
		catch (Throwable $e)
		{
			$comments = '';

			$is403or404 = in_array($e->getCode(), [403, 404]);

			if ($debug && !$is403or404)
			{
				@include JPATH_ADMINISTRATOR . '/components/com_engage/tmpl/common/errorhandler.php';

				$comments = @ob_get_contents();
			}
			elseif ($debug && $is403or404)
			{
				$comments = <<< HTML
<div class="card border-danger">
	<h3 class="card-header bg-danger text-white">
		<span class="badge bg-dark">{$e->getCode()}</span> {$e->getMessage()}
	</h3>
	<div class="card-body">
		<p>{$e->getFile()}({$e->getLine()})</p>
		<pre>{$e->getTraceAsString()}</pre>
	</div>
</div>
HTML;

			}
		}

		@ob_end_clean();

		return $comments;
	}

}