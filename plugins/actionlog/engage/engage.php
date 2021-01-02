<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Engage\Admin\Model\Comments as CommentsModel;
use Akeeba\Engage\Site\Helper\Meta;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;

/**
 * User Action Log integration with Joomla.
 *
 * WARNING! Joomla 4 is broken by design. It will use Reflection to scan the method definitions and try to load all
 * typehinted classes using the autoloader. Of course this is idiotic because a. the autoloader will do it anyway and
 * b. it IMMEDIATELY breaks your site if the component defining the classes is disabled or not loaded already. Therefore
 * we have to REMOVE the typehints from the methods and move them to the docblocks as if we're back writing PHP 5 code.
 */
class plgActionlogEngage extends CMSPlugin
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
	 * Currently logged in user
	 *
	 * @var  \Joomla\CMS\User\User
	 */
	private $user;

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

		$this->user = Factory::getUser();
	}

	/**
	 * Log saving changes to existing comments
	 *
	 * @param   CommentsModel  $comment  The comment being saved
	 *
	 * @return  void
	 */
	public function onComEngageModelCommentsAfterUpdate($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		$container = $comment->getContainer();

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_SAVE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log submitting new comments
	 *
	 * @param   CommentsModel  $comment  The comment which was just submitted
	 *
	 * @return  void
	 */
	public function onComEngageModelCommentsAfterCreate($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		// Should I log comment creation?
		if ($this->params->get('log_comments', 0) != 1)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		$container = $comment->getContainer();

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_CREATE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log unpublishing a comment
	 *
	 * @param   CommentsModel  $comment  The comment being unpublished
	 *
	 * @return  void
	 */
	public function onComEngageModelCommentsAfterUnpublish($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();
		$langKey   = 'COM_ENGAGE_USERLOG_COMMENT_UNPUBLISH';
		$langKey   = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log changing the publish status of a comment.
	 *
	 * This method can handle all publish status changes through the comment model's publish() method including
	 * unpublishing (when publish(0) is used instead of unpublish), publishing and marking a comment as possible spam.
	 *
	 * @param   CommentsModel  $comment  The comment whose publish status is changing.
	 *
	 * @return void
	 */
	public function onComEngageModelCommentsAfterPublish($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();

		switch ($comment->enabled)
		{
			case 0:
			default:
				$langKey = 'COM_ENGAGE_USERLOG_COMMENT_UNPUBLISH';
				break;

			case 1:
				$langKey = 'COM_ENGAGE_USERLOG_COMMENT_PUBLISH';
				break;

			case -3:
				$langKey = 'COM_ENGAGE_USERLOG_COMMENT_SPAM';
				break;
		}

		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log the deletion of a comment
	 *
	 * @param   CommentsModel  $comment  The comment being deleted
	 * @param   int|null       $id       The deleted comment's ID (not used here but passed by FOF)
	 *
	 * @return  void
	 */
	public function onComEngageModelCommentsAfterDelete($comment, ?int $id)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();

		unset($info['comment_edit_link']);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_DELETE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log reporting a comment as positive spam.
	 *
	 * Note that this will cause two or more log messages to appear. One is marking the comment as positive spam. The
	 * other ones are the deletions of the spam comment and any replies it has already received.
	 *
	 * @param   CommentsModel  $comment  The comment which was reported as positive spam
	 *
	 * @return  void
	 */
	public function onAkeebaEngageReportSpam($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();

		unset($info['comment_edit_link']);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_REPORTSPAM';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log reporting a comment as most definitely not spam.
	 *
	 * Note that this will cause two log messages to appear. One is marking the comment as positively non-spam. The
	 * other one is the comment being published automatically.
	 *
	 * @param   CommentsModel  $comment
	 *
	 * @return  void
	 */
	public function onAkeebaEngageReportHam($comment)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_REPORTHAM';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log a user unsubscribing from a comment thread.
	 *
	 * @param   CommentsModel  $comment  The comment which was used as a reference for the conversation
	 * @param   string|null    $email    The email address being unsubscribed
	 */
	public function onEngageUnsubscribeEmail($comment, ?string $email)
	{
		if ($this->user->guest)
		{
			return;
		}

		$info      = $this->getCommentInfo($comment);
		$container = $comment->getContainer();

		unset($info['comment_edit_link']);
		unset($info['comment_id']);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_UNSUBSCRIBE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$container->platform->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Returns the information to be saved with the log entry, used to construct the message the admin sees.
	 *
	 * @param   CommentsModel  $comment  The comment we use as a reference
	 *
	 * @return  array  Information to be sent to Joomla's User Actions Log model.
	 */
	private function getCommentInfo($comment): array
	{
		$meta = Meta::getAssetAccessMeta($comment->asset_id);
		$id   = $comment->getId();

		return [
			'comment_id'        => $id,
			'title'             => $meta['title'],
			'type'              => $meta['type'],
			'public_url'        => $meta['public_url'],
			'comment_edit_link' => 'index.php?option=com_engage&view=Comments&task=edit&id=' . $comment->getId(),
		];
	}

	/**
	 * Try to find a language key specific to the content type defined in $info['type']
	 *
	 * @param   string  $genericKey  The generic language key
	 * @param   array   $info        The action log information
	 *
	 * @return  string  The specific language key to use
	 */
	private function prepareLanguageKey(string $genericKey, array &$info): string
	{
		if (!isset($info['type']) || empty($info['type']))
		{
			return $genericKey;
		}

		$specificKey = strtoupper($genericKey . '_' . $info['type']);

		if (Text::_($specificKey) == $specificKey)
		{
			return $genericKey;
		}

		unset($info['type']);

		return $specificKey;
	}
}
