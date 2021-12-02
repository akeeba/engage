<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\View\Comments;

defined('_JEXEC') or die();

use Akeeba\Component\Engage\Administrator\Helper\Format;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Akeeba\Engage\Site\Model\Comments;
use DateTimeZone;
use Exception;
use FOF40\View\DataView\Html as DataHtml;
use Joomla\CMS\Application\SiteApplication as JApplicationSite;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Registry\Registry;
use stdClass;
use Throwable;
use WbAMP;
use WbampHelper_Runtime;

class Html extends DataHtml
{
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
	 * Currently logged in user's permissions
	 *
	 * @var array
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
	 * Currently logged in user
	 *
	 * @var User
	 */
	public $user;

	/**
	 * Are the comments closed for this content item?
	 *
	 * @var bool
	 */
	public $areCommentsClosed = false;

	/**
	 * The current user's preferred timezone
	 *
	 * @var DateTimeZone
	 */
	public $userTimezone = null;

	/**
	 * WbAMP support. Is this an AMP page?
	 *
	 * @return  bool
	 * @see     https://weeblr.com/documentation/products.wbamp/1/going-further/api/index.html
	 */
	public function isAMP(): bool
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
	 * WbAMP support. Get the canonical (non-AMP) URL of this page
	 *
	 * @return  string
	 * @see     https://weeblr.com/documentation/products.wbamp/1/going-further/api/index.html
	 */
	public function getNonAmpURL(): string
	{
		$current = Uri::getInstance()->toString();

		if (!class_exists('\WbAMP'))
		{
			return $current;
		}

		try
		{
			return WbAMP::getCanonicalUrl();
		}
		catch (Throwable $e)
		{
			return $current;
		}
	}

	/**
	 * WbAMP support. Get the AMP URL of this page
	 *
	 * @return  string
	 * @see     https://weeblr.com/documentation/products.wbamp/1/going-further/api/index.html
	 */
	public function getAmpURL(): string
	{
		$current = Uri::getInstance()->toString();

		if (!class_exists('\WbAMP'))
		{
			return $current;
		}

		try
		{
			return WbAMP::getAMPUrl();
		}
		catch (Throwable $e)
		{
			return $current;
		}
	}

	/**
	 * WbAMP support. Rebase a pagination URL to its AMP equivalent
	 *
	 * @param   string  $link
	 *
	 * @return string
	 */
	public function rebasePageLink(string $link): string
	{
		$uri   = Uri::getInstance($link);
		$start = $uri->getVar('akengage_limitstart', null);
		$limit = $uri->getVar('akengage_limit', null);

		$linkUri = clone Uri::getInstance();

		if (!is_null($start))
		{
			$linkUri->setVar('akengage_limitstart', $start);
		}

		if (!is_null($limit))
		{
			$linkUri->setVar('akengage_limit', $limit);
		}

		return $linkUri->toString();
	}

	/**
	 * Returns the consent checkbox' text.
	 *
	 * This supports full HTML and plugin codes.
	 *
	 * @return  string
	 */
	public function getCheckboxText(): string
	{
		$text = trim($this->container->params->get('tos_prompt', '') ?? '');

		if (empty($text))
		{
			$text = Text::_('COM_ENGAGE_COMMENTS_FORM_LBL_ACCEPT');
		}

		return HTMLHelper::_('content.prepare', $text);
	}

	/**
	 * Executes before rendering the page for the Browse task.
	 *
	 * @throws  Exception
	 */
	protected function onBeforeBrowse()
	{
		$this->userTimezone = $this->getUserTimezone();

		$isAMP = $this->isAMP();

		$this->setLayout('default');

		if ($isAMP)
		{
			$this->setLayout('amp');
			$this->injectAMPStyling();
		}

		// Load the CSS and JavaScript
		$this->addJavascriptFile('media://com_engage/js/comments.min.js', $this->container->mediaVersion, 'text/javascript', true);

		// User and permissions
		$platform    = $this->container->platform;
		$this->user  = $platform->getUser();
		$this->perms = array_merge($this->perms, [
				'create' => $this->user->authorise('core.create', 'com_engage'),
				'edit'   => $this->user->authorise('core.edit', 'com_engage'),
				'own'    => $this->user->authorise('core.edit.own', 'com_engage'),
				'state'  => $this->user->authorise('core.edit.state', 'com_engage'),
				'delete' => $this->user->authorise('core.delete', 'com_engage'),
			]
		);

		// Load the model and persist its state in the session
		/** @var Comments $model */
		$model = $this->getModel()->tmpInstance();
		$model->asset_id($this->assetId);

		// Display limits
		$defaultLimit = $this->getDefaultListLimit();

		$this->lists             = new stdClass();
		$this->lists->limitStart = $this->input->getInt('akengage_limitstart', 0);
		$this->lists->limit      = $this->input->getInt('akengage_limit', $defaultLimit);
		$this->lists->limit      = empty($this->lists->limit) ? null : $this->lists->limit;

		// Pass the display limits to the model
		$model->limitstart = $this->lists->limitStart;
		$model->limit      = $this->lists->limit;

		// Only show unpublished comments to users who can publish and unpublish comments (and never in AMP views)
		if (!$this->perms['state'] || $isAMP)
		{
			$model->enabled(1);
		}

		// Populate display items and total item count
		$this->items     = $model->commentTreeSlice($this->lists->limitStart, $this->lists->limit);
		$this->itemCount = $model->getTreeAwareCount();

		// Populate the pagination object
		$this->pagination = new Pagination($this->itemCount, $this->lists->limitStart, $this->lists->limit, 'akengage_');

		// Asset metadata-based properties
		$meta        = Meta::getAssetAccessMeta($this->assetId, true);
		$this->title = $meta['title'];

		if (!empty($meta['title']))
		{
			$this->headerKey = $this->getHeaderKey($meta['type']) ?? 'COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS';
		}

		$this->areCommentsClosed = Meta::areCommentsClosed($this->assetId);

		// Populate properties based on component parameters
		$params         = $this->container->params;
		$this->maxLevel = $params->get('max_level', 3);

		// Page parameters
		/** @var JApplicationSite $app */
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
		$protoURL = 'index.php?option=com_engage&view=Comments&task=%s&id=';
		$platform->addScriptOptions('akeeba.Engage.Comments.returnURL', base64_encode(Uri::getInstance()->toString()));
		$platform->addScriptOptions('akeeba.Engage.Comments.editURL', Route::_(sprintf($protoURL, 'edit'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.deleteURL', Route::_(sprintf($protoURL, 'remove'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.publishURL', Route::_(sprintf($protoURL, 'publish'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.unpublishURL', Route::_(sprintf($protoURL, 'unpublish'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.markhamURL', Route::_(sprintf($protoURL, 'reportham'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.markspamURL', Route::_(sprintf($protoURL, 'reportspam'), false, Route::TLS_IGNORE, true));
		$platform->addScriptOptions('akeeba.Engage.Comments.possiblespamURL', Route::_(sprintf($protoURL, 'possiblespam'), false, Route::TLS_IGNORE, true));

		Text::script('COM_ENGAGE_COMMENTS_DELETE_PROMPT');
	}

	/**
	 * Make sure the comment's parent information is cached in $parentIds and $parentNames.
	 *
	 * This is required when a page starts the listing with a comment of level 'max_level' (see config.xml) or deeper.
	 * In these cases the reply information we need to pass is meant to be that of the last parent with a nesting level
	 * of 'max_depth' minus 1. The following code makes sure that is the case.
	 *
	 * @param   Comments  $comment
	 * @param   array     $parentIds
	 * @param   array     $parentNames
	 */
	protected function ensureHasParentInfo(Comments $comment, array &$parentIds, array &$parentNames): void
	{
		$parentLevel = $comment->depth - 1;

		if (isset($parentIds[$parentLevel]) && isset($parentNames[$parentLevel]))
		{
			return;
		}

		$myComment = $comment;
		$maxLevel  = (int) $this->container->params->get('max_level', 3);
		$maxLevel  = max($maxLevel, 1);

		do
		{
			$newDepth         = $myComment->depth - 1;
			$myComment        = $myComment->getClone()->find($myComment->parent_id);
			$myComment->depth = $newDepth;

			$parentNames[$myComment->depth] = $myComment->getUser()->name;
			$parentIds[$myComment->depth]   = $myComment->getId();
		} while ($myComment->depth > ($maxLevel - 1));
	}

	/**
	 * Get the CAPTCHA field contents
	 *
	 * @return  string
	 */
	protected function getCaptchaField(): string
	{
		$user          = $this->container->platform->getUser();
		$useCaptchaFor = $this->container->params->get('captcha_for', 'guests');
		$useCaptchaFor = in_array($useCaptchaFor, ['guests', 'all', 'nonmanager']) ? $useCaptchaFor : 'guests';

		if (($useCaptchaFor === 'guests') && ($user->guest !== 1))
		{
			return '';
		}

		if (($useCaptchaFor === 'nonmanager') && $user->authorise('core.manage', 'com_engage'))
		{
			return '';
		}

		$captcha = $this->getModel()->getCaptcha();

		if (is_null($captcha))
		{
			return '';
		}

		return $captcha->display('captcha', 'akengage-comments-captcha');
	}

	/**
	 * Get an IP lookup URL for the provided IP address
	 *
	 * @param   string|null  $ip  The IP address to look up
	 *
	 * @return  string  The lookup URL, empty if not applicable.
	 */
	protected function getIPLookupURL(?string $ip): string
	{
		return Format::getIPLookupURL($ip);
	}

	/**
	 * Get the default list limit configured by the site administrator
	 *
	 * @return  int
	 */
	private function getDefaultListLimit(): int
	{
		$defaultLimit = $this->container->params->get('default_limit', 20);
		$defaultLimit = ($defaultLimit > 0) ? $defaultLimit : null;

		if (!is_null($defaultLimit))
		{
			return $defaultLimit;
		}

		if ($this->container->platform->isCli() || !class_exists('Joomla\CMS\Factory'))
		{
			return 20;
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

	/**
	 * Inject the amp.css file into the AMP pages.
	 *
	 * wbAMP sidesteps the Joomla HTML document (for good reason). Moreover, AMP doesn't support linked stylesheets, it
	 * all has to be inline. The only options we have are:
	 *
	 * i.  Ask the user to enter the styling manually (which sucks for the user); or
	 * ii. Use a hack-y way to make wbAMP believe the content of our amp.css file is custom CSS rules the user has
	 *     entered into its configuration.
	 *
	 * I opted for the latter. However, if you are reading this, PLEASE do not do this unless you understand very well
	 * the implications of adding CSS rules to AMP and have an option for your users to disable this behavior. Do as I
	 * say, don't do as I do.
	 */
	private function injectAMPStyling(): void
	{
		// If I'm told to not inject CSS in AMP pages I will bail out early.
		if ($this->container->params->get('amp_css', 1) == 0)
		{
			return;
		}

		if (!class_exists('\WbampHelper_Runtime'))
		{
			return;
		}

		try
		{
			$customCss     = WbampHelper_Runtime::$params->get('custom_css');
			$customCssFile = $this->container->template->parsePath('media://com_engage/css/amp.css', true);
			$content       = @file_get_contents($customCssFile);
			if ($content === false)
			{
				return;
			}
			$content   = str_replace('/*# sourceMappingURL=amp.css.map */', '', $content);
			$customCss .= (empty($customCss) ? "\n" : '') . $content;
			WbampHelper_Runtime::$params->set('custom_css', $customCss);
		}
		catch (Throwable $e)
		{
			// Log the error and continue regardless. It won't be as pretty but it will still be readable!
			Log::add(sprintf("Error injecting AMP styling: %s", $e->getMessage()), Log::CRITICAL, 'com_engage');

			return;
		}
	}

	private function getUserTimezone(): DateTimeZone
	{
		$platform     = $this->container->platform;
		$user         = $platform->getUser();
		$siteTimezone = $platform->getConfig()->get('offset', 'UTC');
		$zone         = $user->guest ? $siteTimezone : $user->getParam('timezone', $siteTimezone);

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
