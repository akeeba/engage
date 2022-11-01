<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\View\Comments;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Akeeba\Component\Engage\Site\Mixin\ModuleRenderAware;
use Akeeba\Component\Engage\Site\Model\CommentModel;
use Akeeba\Component\Engage\Site\Model\CommentsModel;
use DateTimeZone;
use Exception;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;

class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;
	use ModuleRenderAware;

	/**
	 * Are the comments closed for this content item?
	 *
	 * @var   bool
	 * @since 1.0.0
	 */
	public $areCommentsClosed = false;

	/**
	 * The asset ID to display comments for.
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	public $assetId;

	/**
	 * Language key for displaying the header (number of comments).
	 *
	 * Generic default: COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS
	 *
	 * You can define language strings such as COM_ENGAGE_COMMENTS_CONTENTTYPE_HEADER_N_COMMENTS where CONTENTTYPE is
	 * the content type returned by the Akeeba Engage plugins. For example, for Joomla articles you can use the language
	 * key COM_ENGAGE_COMMENTS_ARTICLE_HEADER_N_COMMENTS.
	 *
	 * @var   string
	 * @since 1.0.0
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
	 * @var   int
	 * @since 1.0.0
	 */
	public $maxLevel = 3;

	/**
	 * Currently logged in user's permissions
	 *
	 * @var   array
	 * @since 1.0.0
	 */
	public $perms = [
		// Submit new comments
		'create' => false,
		// Edit any comment
		'edit'   => false,
		// Edit own comments
		'own'    => false,
		// Edit comments' state (pubished, unpublished, spam)
		'state'  => false,
		// Delete comments
		'delete' => false,
	];

	/**
	 * Display title for the resource being commented on
	 *
	 * @var   string|null
	 * @since 1.0.0
	 */
	public $title = null;

	/**
	 * Currently logged in user
	 *
	 * @var   User
	 * @since 1.0.0
	 */
	public $user;

	/**
	 * The current user's preferred timezone
	 *
	 * @var DateTimeZone
	 */
	public $userTimezone = null;

	/**
	 * Are the comments closed because a certain amount of time elapsed since the article's creation?
	 *
	 * @var   bool
	 * @since 3.0.7
	 */
	public $areCommentsClosedAfterTime = false;

	/**
	 * The URL option for the component.
	 *
	 * This is used to automatically determine the template overrides path when using _setPath().
	 *
	 * @var    string
	 * @since  3.0.6
	 */
	protected $option = 'com_engage';

	/**
	 * The comment form
	 *
	 * @var   bool|Form|null
	 * @since 3.0.0
	 */
	private $form;

	/**
	 * Number of comments being displayed
	 *
	 * @var   int
	 * @since 1.0.0
	 */
	private $itemCount;

	/**
	 * Comments to display
	 *
	 * @var   object[]
	 * @since 1.0.0
	 */
	private $items;

	/**
	 * The Joomla pagination object
	 *
	 * @var   Pagination
	 * @since 1.0.0
	 */
	private $pagination;

	/** @inheritDoc */
	public function display($tpl = null)
	{
		$this->setLayout('default');
		$this->_setPath('template', [
			JPATH_SITE . '/components/com_engage/tmpl/comments',
		]);
		$this->_setPath('helper', [
			JPATH_SITE . '/components/com_engage/helpers',
		]);

		// User information
		$this->user         = Factory::getUser();
		$this->perms        = array_merge($this->perms, [
				'create' => $this->user->authorise('core.create', 'com_engage'),
				'edit'   => $this->user->authorise('core.edit', 'com_engage'),
				'own'    => $this->user->authorise('core.edit.own', 'com_engage'),
				'state'  => $this->user->authorise('core.edit.state', 'com_engage'),
				'delete' => $this->user->authorise('core.delete', 'com_engage'),
			]
		);
		$this->userTimezone = $this->getUserTimezone();

		// Load the model and persist its state in the session
		/** @var CommentsModel $model */
		$model = $this->getModel();
		$model->setState('filter.asset_id', $this->assetId);

		// Only show unpublished comments to users who can publish and unpublish comments
		if (!$this->perms['state'])
		{
			$model->setState('filter.enabled', 1);
		}

		// Populate display items and total item count
		$this->items     = $model->commentTreeSlice();
		$this->itemCount = $model->getTreeAwareCount();

		// Populate the pagination object
		$this->pagination         = $model->getPagination();
		$this->pagination->prefix = 'akengage_';
		$this->pagination->setAdditionalUrlParam('akengage_cid', '');

		// Asset metadata-based properties
		$meta        = Meta::getAssetAccessMeta($this->assetId, true);
		$this->title = $meta['title'];

		if (!empty($meta['title']))
		{
			$this->headerKey = $this->getHeaderKey($meta['type']) ?? 'COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS';
		}

		$this->areCommentsClosed          = Meta::areCommentsClosed($this->assetId);
		$this->areCommentsClosedAfterTime = Meta::areCommentsClosedAfterTime($this->assetId);

		// Populate properties based on component parameters
		$params         = ComponentHelper::getParams('com_engage');
		$this->maxLevel = $params->get('max_level', 3);

		// Page parameters
		/** @var SiteApplication $app */
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

		// Script options and language keys
		$doc = $app->getDocument();

		$baseUrl = Uri::getInstance(Route::_('index.php?option=com_engage'));
		$baseUrl->setVar('returnurl', base64_encode(Uri::getInstance()->toString()));
		$baseUrl->setVar($app->getFormToken(), 1);

		$baseUrl->setVar('id', '__ID__');
		$baseUrl->setVar('task', 'comment.edit');
		$doc->addScriptOptions('akeeba.Engage.Comments.editURL', $baseUrl->toString());
		$baseUrl->delVar('id');

		$baseUrl->setVar('cid[]', '__ID__');

		$baseUrl->setVar('task', 'comments.delete');
		$doc->addScriptOptions('akeeba.Engage.Comments.deleteURL', $baseUrl->toString());

		$baseUrl->setVar('task', 'comments.publish');
		$doc->addScriptOptions('akeeba.Engage.Comments.publishURL', $baseUrl->toString());

		$baseUrl->setVar('task', 'comments.unpublish');
		$doc->addScriptOptions('akeeba.Engage.Comments.unpublishURL', $baseUrl->toString());

		$baseUrl->setVar('task', 'comments.reportham');
		$doc->addScriptOptions('akeeba.Engage.Comments.markhamURL', $baseUrl->toString());

		$baseUrl->setVar('task', 'comments.reportspam');
		$doc->addScriptOptions('akeeba.Engage.Comments.markspamURL', $baseUrl->toString());

		$baseUrl->setVar('task', 'comments.possiblespam');
		$doc->addScriptOptions('akeeba.Engage.Comments.possiblespamURL', $baseUrl->toString());

		Text::script('COM_ENGAGE_COMMENTS_DELETE_PROMPT');

		// Comment form
		if (!$this->areCommentsClosed && $this->perms['create'])
		{
			/**
			 * Joomla always try to load the form from the current component. However, the current component is
			 * com_content as we're emulating HMVC (Hierarchical Model–View–Controller). As a result we need to hint
			 * Joomla that it should be loading the form from Akeeba Engage's forms folder.
			 */
			$basePath = JPATH_SITE . '/components/com_engage';
			Form::addFormPath($basePath . '/forms');

			/** @var CommentModel $formModel */
			$formModel  = $this->getModel('comment');
			$this->form = $formModel->getForm([
				'id'       => null,
				'asset_id' => $this->assetId,
			], true);
		}

		parent::display($tpl);
	}


	/**
	 * Make sure the comment's parent information is cached in $parentIds and $parentNames.
	 *
	 * This is required when a page starts the listing with a comment of level 'max_level' (see config.xml) or deeper.
	 * In these cases the reply information we need to pass is meant to be that of the last parent with a nesting level
	 * of 'max_depth' minus 1. The following code makes sure that is the case.
	 *
	 * @param   object  $comment
	 * @param   array   $parentIds
	 * @param   array   $parentNames
	 *
	 * @throws  Exception
	 * @since   1.0.0
	 */
	protected function ensureHasParentInfo(object $comment, array &$parentIds, array &$parentNames): void
	{
		$parentLevel = $comment->depth - 1;

		if (isset($parentIds[$parentLevel]) && isset($parentNames[$parentLevel]))
		{
			return;
		}

		$myComment = $comment;
		$maxLevel  = ComponentHelper::getParams('com_engage')->get('max_level', 3);
		$maxLevel  = max($maxLevel, 1);

		$table = $this->getModel()->getTable('Comment', 'Administrator');

		do
		{
			$newDepth  = $myComment->depth - 1;
			$myComment = clone $table;
			$myComment->reset();
			if (!$myComment->load($myComment->parent_id))
			{
				break;
			};
			$myComment->depth = $newDepth;

			$parentNames[$myComment->depth] = $myComment->created_by
				? UserFetcher::getUser($myComment->created_by)->name : $myComment->name;
			$parentIds[$myComment->depth]   = $myComment->id;
		} while ($myComment->depth > ($maxLevel - 1));
	}

	/**
	 * Get an IP lookup URL for the provided IP address
	 *
	 * @param   string|null  $ip  The IP address to look up
	 *
	 * @return  string  The lookup URL, empty if not applicable.
	 * @since   1.0.0
	 */
	protected function getIPLookupURL(?string $ip): string
	{
		return HTMLHelper::_('engage.getIPLookupURL', $ip);
	}

	/**
	 * Get the appropriate language key for the content type provided
	 *
	 * @param   string  $type  Content type, e.g. 'article'
	 *
	 * @return  string|null  The custom language key or null if no appropriate key is found.
	 * @since   1.0.0
	 */
	private function getHeaderKey(string $type): ?string
	{
		$key = sprintf('COM_ENGAGE_COMMENTS_%s_HEADER_N_COMMENTS', strtoupper($type));

		try
		{
			$lang = Factory::getApplication()->getLanguage();
		}
		catch (Exception $e)
		{
			return null;
		}

		return $lang->hasKey($key) ? $key : null;
	}

	/**
	 * Get the timezone for the currently logged in user (site's timezone for guest users).
	 *
	 * @return  DateTimeZone
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function getUserTimezone(): DateTimeZone
	{
		try
		{
			$siteTimezone = Factory::getApplication()->get('offset', 'UTC');
		}
		catch (Exception $e)
		{
			$siteTimezone = 'UTC';
		}

		$zone = $this->user->guest ? $siteTimezone : $this->user->getParam('timezone', $siteTimezone);

		try
		{
			return new DateTimeZone($zone);
		}
		catch (Exception $e)
		{
			return new DateTimeZone('UTC');
		}
	}
}