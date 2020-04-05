<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\View\Comments;

defined('_JEXEC') or die();

use Akeeba\Engage\Site\Helper\Meta;
use Akeeba\Engage\Site\Model\Comments;
use Exception;
use FOF30\View\DataView\Html as DataHtml;
use Joomla\CMS\Factory;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Router;
use Joomla\Registry\Registry;

class Html extends DataHtml
{
	/**
	 * Root node for all comments.
	 *
	 * @var Comments
	 */
	public $rootNode;

	/**
	 * The asset ID to display comments for.
	 *
	 * @var int
	 */
	public $assetId;

	/**
	 * Display title for the resource being commented on
	 *
	 * @var string|null
	 */
	public $title = null;

	/**
	 * Language key for displaying the header (number of comments).
	 *
	 * Generic default: COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS
	 *
	 * You can define language strings such as COM_ENGAGE_COMMENTS_CONTENTTYPE_HEADER_N_COMMENTS where CONTENTTYPE is
	 * the content type returned by the Akeeba Engage plugins. For example, for Joomla articles you can use the language
	 * key COM_ENGAGE_COMMENTS_ARTICLE_HEADER_N_COMMENTS.
	 *
	 * @var string
	 */
	public $headerKey = 'COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS';

	/**
	 * Maximum comment nesting level.
	 *
	 * This is only used for replies. You can reply directly to $maxLevel-1 level comments. Replies to $maxLevel or
	 * deeper comments will be in reply to the $maxLevel-1 parent.
	 *
	 * For example, if $maxLevel = 3 (default) you can file a new top level comment or reply to the first and second
	 * level comments. Replying to a third level comment will actually be in reply to its second level parent comment.
	 *
	 * Imposing a nesting cap prevents excessive margins when displaying comments in a hot conversation, e.g. when two
	 * users are clearly exchanging banter. Think about what happens on YouTube comments and how it caps nesting to two
	 * levels only. It's the same idea here.
	 *
	 * @var int
	 */
	public $maxLevel = 3;

	/**
	 * The last submitted comment's name, in case the validation failed.
	 *
	 * @var string
	 */
	public $storedName = '';

	/**
	 * The last submitted comment's email address, in case the validation failed.
	 *
	 * @var string
	 */
	public $storedEmail = '';

	/**
	 * The last submitted comment's text, in case the validation failed.
	 *
	 * @var string
	 */
	public $storedComment = '';

	/**
	 * Executes before rendering the page for the Browse task.
	 *
	 * @throws  Exception
	 */
	protected function onBeforeBrowse()
	{
		// Load the CSS and JavaScript
		$this->addCssFile('media://com_engage/css/comments.min.css', $this->container->mediaVersion);
		$this->addJavascriptFile('media://com_engage/js/system.min.js', $this->container->mediaVersion, 'text/javascript', true);
		$this->addJavascriptFile('media://com_engage/js/comments.min.js', $this->container->mediaVersion, 'text/javascript', true);

		// Get the current user
		$platform = $this->container->platform;
		$user     = $platform->getUser();

		// Load the model and persist its state in the session
		/** @var Comments $model */
		$model = $this->getModel();

		$model->savestate(1);

		// Display limits
		$defaultLimit = $this->getDefaultListLimit();

		$this->lists             = new \stdClass();
		$this->lists->limitStart = $this->input->getInt('akengage_limitstart', 0);
		$this->lists->limit      = $model->getState('akengage_limit', $defaultLimit, 'int');

		// Pass the display limits to the model
		$model->limitstart = $this->lists->limitStart;
		$model->limit      = $this->lists->limit;

		// Get the tree root node
		$model          = $model->getRoot();
		$this->rootNode = $model->getClone()->bind(['depth' => 0]);

		// Filter by comments belonging to the specific asset
		$model->scopeAssetCommentTree($this->assetId);

		// Only show unpublished comments to users who can publish and unpublish comments
		if (!$user->authorise('core.edit.state', 'com_engage'))
		{
			$model->enabled(1);
		}

		// Populate display items and total item count
		$this->items     = $model->get(false);
		$this->itemCount = $model->count();

		// Populate the pagination object
		$this->pagination = new Pagination($this->itemCount, $this->lists->limitStart, $this->lists->limit, 'akengage_');

		// Asset metadata-based properties
		$meta        = Meta::getAssetAccessMeta($this->assetId);
		$this->title = $meta['title'];

		if (!empty($meta['title']))
		{
			$this->headerKey = $this->getHeaderKey($meta['type']) ?? 'COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS';
		}

		// Populate properties based on component parameters
		$params         = $this->container->params;
		$this->maxLevel = $params->get('max_level', 3);

		// Page parameters
		/** @var \JApplicationSite $app */
		try
		{
			$app        = Factory::getApplication();
			$pageParams = $app->getParams();

			if (is_object($pageParams) && ($pageParams instanceof Registry))
			{
				$this->pageParams = $pageParams;
			}
		}
		catch (Exception $e)
		{
			$this->pageParams = null;
		}

		$this->pageParams = $this->pageParams ?? new Registry();

		// Script options
		$router = Router::getInstance('site');
		$platform->addScriptOptions('akeeba.Engage.Comments.editURL', $router->build('index.php?option=com_engage&view=Comments&task=edit&id='));
	}

	/**
	 * Get the default list limit configured by the site administrator
	 *
	 * @return  int
	 */
	protected function getDefaultListLimit(): int
	{
		$defaultLimit = 20;

		if ($this->container->platform->isCli() || !class_exists('Joomla\CMS\Factory'))
		{
			return $defaultLimit;
		}

		try
		{
			$app = Factory::getApplication();
		}
		catch (Exception $e)
		{
			return $defaultLimit;
		}

		if (is_object($app) && method_exists($app, 'get'))
		{
			$defaultLimit = (int) $app->get('list_limit', 20);
		}

		return $defaultLimit;
	}

	/**
	 * Get the appropriate language key for the content type provided
	 *
	 * @param   string  $type  Content type, e.g. 'article'
	 *
	 * @return  string|null  The custom language key or null if no appropriate key is found.
	 */
	private function getHeaderKey(string $type): ?string
	{
		$key  = sprintf('COM_ENGAGE_COMMENTS_%s_HEADER_N_COMMENTS', strtoupper($type));
		$lang = $this->container->platform->getLanguage();

		return $lang->hasKey($key) ? $key : null;
	}
}