<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Actionlog\Engage\Extension;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\Component\Actionlogs\Administrator\Plugin\ActionLogPlugin;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;

/**
 * User Action Log integration with Joomla.
 *
 * @since 1.0.0
 */
class Engage extends ActionLogPlugin implements SubscriberInterface
{
	use DatabaseAwareTrait;

	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	protected $allowLegacyListeners = false;

	/**
	 * Affects constructor behavior. If true, language files will be loaded automatically.
	 *
	 * @var    boolean
	 * @since  3.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * The extension we are logging user actions for.
	 *
	 * @var   string
	 * @since 3.0.0
	 */
	private $defaultExtension = 'com_engage';

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 * @since   3.0.0
	 */
	public static function getSubscribedEvents(): array
	{
		if (!ComponentHelper::isEnabled('com_engage'))
		{
			return [];
		}

		return [
			'onAkeebaEngageReportHam'            => 'onAkeebaEngageReportHam',
			'onAkeebaEngageReportSpam'           => 'onAkeebaEngageReportSpam',
			'onComEngageCommentTableAfterCreate' => 'onComEngageCommentTableAfterCreate',
			'onComEngageCommentTableAfterUpdate' => 'onComEngageCommentTableAfterUpdate',
			'onContentAfterDelete'               => 'onContentAfterDelete',
			'onContentChangeState'               => 'onContentChangeState',
			'onEngageUnsubscribeEmail'           => 'onEngageUnsubscribeEmail',
		];
	}

	/**
	 * Log reporting a comment as most definitely not spam.
	 *
	 * Note that this will cause two log messages to appear. One is marking the comment as positively non-spam. The
	 * other one is the comment being published automatically.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onAkeebaEngageReportHam(Event $event): void
	{
		/** @var   CommentTable $comment */
		[$comment] = $event->getArguments();

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_REPORTHAM';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log reporting a comment as positive spam.
	 *
	 * Note that this will cause two or more log messages to appear. One is marking the comment as positive spam. The
	 * other ones are the deletions of the spam comment and any replies it has already received.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onAkeebaEngageReportSpam(Event $event): void
	{
		/** @var   CommentTable $comment */
		[$comment] = $event->getArguments();

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		unset($info['comment_edit_link']);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_REPORTSPAM';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log submitting new comments
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        3.0.0
	 * @noinspection PhpUnused
	 */
	public function onComEngageCommentTableAfterCreate(Event $event): void
	{
		/** @var   CommentTable $comment */
		[$comment] = $event->getArguments();

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		// Should I log comment creation?
		if ($this->params->get('log_comments', 0) != 1)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_CREATE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log saving changes to existing comments
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        3.0.0
	 * @noinspection PhpUnused
	 */
	public function onComEngageCommentTableAfterUpdate(Event $event): void
	{
		/** @var   CommentTable $comment */
		[$comment] = $event->getArguments();

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_SAVE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log the deletion of a comment
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        3.0.0
	 * @noinspection PhpUnused
	 */
	public function onContentAfterDelete(Event $event): void
	{
		/**
		 * @var   string       $context
		 * @var   CommentTable $comment
		 */
		[$context, $comment] = $event->getArguments();

		if (!in_array($context, ['com_engage.comment', 'com_engage.comments']))
		{
			return;
		}

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		unset($info['comment_edit_link']);

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_DELETE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Log changing the publish status of a comment.
	 *
	 * This method can handle all publish status changes through the comment model's publish() method including
	 * unpublishing, publishing and marking a comment as possible spam.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        3.0.4
	 *
	 * @noinspection PhpUnused
	 */
	public function onContentChangeState(Event $event): void
	{
		/**
		 * @var  string $context
		 * @var  array  $pks
		 * @var  int    $value
		 */
		[$context, $pks, $value] = $event->getArguments();

		if (!in_array($context, ['com_engage.comment', 'com_engage.comments']))
		{
			return;
		}

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		/** @var   CommentTable $comment */
		$comment = new CommentTable($this->getDatabase(), $this->getApplication()->getDispatcher());

		foreach ($pks as $id)
		{
			if (!$comment->load($id))
			{
				continue;
			}

			$info = $this->getCommentInfo($comment);

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

			$this->logUserAction($info, $langKey, 'com_engage');
		}
	}

	/**
	 * Log a user unsubscribing from a comment thread.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @since        1.0.0
	 * @noinspection PhpUnused
	 */
	public function onEngageUnsubscribeEmail(Event $event): void
	{
		/**
		 * @var   CommentTable $comment The comment which was used as a reference for the conversation
		 * @var   string|null  $email   The email address being unsubscribed
		 */
		[$comment, $email] = $event->getArguments();

		if ($this->getApplication()->getIdentity()->guest)
		{
			return;
		}

		$info = $this->getCommentInfo($comment);

		unset($info['comment_edit_link']);
		unset($info['comment_id']);

		$info['email'] = $email;

		$langKey = 'COM_ENGAGE_USERLOG_COMMENT_UNSUBSCRIBE';
		$langKey = $this->prepareLanguageKey($langKey, $info);

		$this->logUserAction($info, $langKey, 'com_engage');
	}

	/**
	 * Returns the information to be saved with the log entry, used to construct the message the admin sees.
	 *
	 * @param   Table|CommentTable  $comment  The comment we use as a reference
	 *
	 * @return  array  Information to be sent to Joomla's User Actions Log model.
	 * @since   1.0.0
	 */
	private function getCommentInfo(Table $comment): array
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
	 * Log a user action.
	 *
	 * This is a simple wrapper around self::addLog
	 *
	 * @param   string|array  $dataOrTitle         Text to use as the title, or an array of data to record in the audit
	 *                                             log.
	 * @param   string        $messageLanguageKey  Language key describing the user action taken.
	 * @param   string|null   $context             The name of the extension being logged.
	 * @param   User|null     $user                User object taking this action.
	 *
	 * @return  void
	 *
	 * @see     self::addLog
	 * @since   3.0.0
	 */
	private function logUserAction($dataOrTitle, string $messageLanguageKey, ?string $context = null, ?User $user = null): void
	{
		// Get the user if not defined
		$user = $user ?? $this->getApplication()->getIdentity();

		// No log for guests
		if (empty($user) || ($user->guest))
		{
			return;
		}

		// Default extension if none defined
		$context = $context ?? $this->defaultExtension;

		if (!is_array($dataOrTitle))
		{
			$dataOrTitle = [
				'title' => $dataOrTitle,
			];
		}

		$this->addLog([$dataOrTitle], $messageLanguageKey, $context, $user->id);
	}

	/**
	 * Try to find a language key specific to the content type defined in $info['type']
	 *
	 * @param   string  $genericKey  The generic language key
	 * @param   array   $info        The action log information
	 *
	 * @return  string  The specific language key to use
	 * @since   1.0.0
	 */
	private function prepareLanguageKey(string $genericKey, array &$info): string
	{
		if (empty($info['type']))
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
