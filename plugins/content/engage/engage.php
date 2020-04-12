<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Engage\Admin\Model\Comments;
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
 * Akeeba Engage – Configure and show comments in Joomla core content (articles) and categories
 *
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
		$row = $this->getArticleByAssetId($assetId, $loadParameters);

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


		$publishUp = new Joomla\CMS\Date\Date();
		$db        = Factory::getDbo();

		if ($db->getNullDate() != $row->publish_up)
		{
			$publishUp = new Joomla\CMS\Date\Date($row->publish_up);
		}

		return [
			'type'          => 'article',
			'published'     => $this->isRowPublished($row),
			'published_on'  => $publishUp,
			'access'        => $row->access ?? 0,
			'parent_access' => $row->category_access,
			'title'         => $row->title,
			'category'      => $row->category_title,
			'url'           => $url,
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
			$db    = Factory::getDbo();
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
	 * @param   int   $assetId         The asset ID to use
	 * @param   bool  $loadParameters  Should I load the comment parameters? (It's slow!)
	 *
	 * @return  object|null  Partial article information. NULL when there is no article associated with this asset ID.
	 */
	private function getArticleByAssetId(int $assetId, bool $loadParameters = false)
	{
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
				JModelLegacy::addIncludePath(JPATH_BASE . '/components/com_content/models');
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
			'parameters'      => $loadParameters ? $this->getParametersForArticle($row) : new Registry(),
		];

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
	private function getParametersForArticle($row): Registry
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
			$v                  = $articleParams->get('engage.' . $key, $ret[$key]);
			$hasInheritedParams = $hasInheritedParams || $this->isUseGlobal($v);
		}

		// If there are no "Use Global" parameters return what we've got so far.
		if (!$hasInheritedParams)
		{
			return new Registry();
		}

		// Go through the categories hierarchy, replacing inherited parameters
		if (!class_exists('CategoriesModelCategory'))
		{
			JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/models');
		}

		if (!class_exists('CategoriesTableCategory'))
		{
			Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_categories/tables');
		}

		$catId = $row->catid;
		/** @var CategoriesModelCategory $model */
		$model = JModelLegacy::getInstance('Category', 'CategoriesModel');

		while (true)
		{
			$cat                = $model->getItem($catId);
			$params             = new Registry($cat->params);
			$hasInheritedParams = false;

			// If I still have inherited parameters go through the component parameters
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

			if ($hasInheritedParams || empty($cat->parent_id))
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
			if ((int) $value === -1)
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
}