<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Component\Engage\Site\Helper\Email;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Akeeba\Engage\Admin\Model\Comments;
use FOF40\Container\Container;
use FOF40\Model\DataModel\Exception\RecordNotLoaded;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\User\User;

defined('_JEXEC') or die;

class plgEngageEmail extends CMSPlugin
{
	/**
	 * Automatically triggered right after Akeeba Engage saves a NEW comment record to its database table.
	 *
	 * @param   Comments  $comment  The comment that has just been saved.
	 *
	 * @return  void
	 */
	public function onComEngageModelCommentsAfterCreate(Comments $comment): void
	{
		// No emails in CLI, please
		$container = Container::getInstance('com_engage');

		if ($container->platform->isCli())
		{
			return;
		}

		$meta              = Meta::getAssetAccessMeta($comment->asset_id, true);
		$notifyAuthor      = $meta['parameters']->get('comments_notify_author', 1);
		$notifyUsers       = $meta['parameters']->get('comments_notify_users', 1);
		$honorUnsubscribed = true;

		switch ($comment->enabled)
		{
			// Unpublished or Spam: send an email to Comment Managers
			case 0:
			case -3:
				$recipients        = $this->getCommentManagerRecipients();
				$type              = ($comment->enabled == 0) ? 'manage' : 'spam';
				$honorUnsubscribed = false;

				break;

			// Published: notify users taking part in the conversation
			case 1:
				$recipients     = $notifyUsers ? $this->getUserRecipients($comment) : [];
				$type           = 'notify';
				$notifyManagers = $this->params->get('managers_notify', 0) == 1;

				/**
				 * Notify the content author unless he's already being notified as a user
				 */
				if ($notifyAuthor)
				{
					$this->sendEmailMessages('notify_author', $comment, $this->getRecipientsArrayDiff(
						[$meta['author_email'] => $meta['author_name']], $recipients
					));
				}

				if ($notifyManagers)
				{
					/**
					 * Notify managers unless they are being notified EITHER as a user OR as a content author
					 */
					$managerRecipients = $this->getRecipientsArrayDiff($this->getCommentManagerRecipients(), $recipients);

					if ($notifyAuthor)
					{
						$managerRecipients = $this->getRecipientsArrayDiff(
							$this->getCommentManagerRecipients(), [$meta['author_email'] => $meta['author_name']]
						);
					}

					$this->sendEmailMessages('notify_managers', $comment, $managerRecipients);
				}

				break;
		}

		$this->sendEmailMessages($type, $comment, $recipients, $honorUnsubscribed);
	}

	/**
	 * Generates the difference between two recipient arrays. Each array follows the convention email => name
	 *
	 * @param   array  $allRecipients       The array to filter
	 * @param   array  $excludedRecipients  Recipients to remove from $allRecipients
	 *
	 * @return  array  The filtered array
	 */
	protected function getRecipientsArrayDiff(array $allRecipients, array $excludedRecipients): array
	{
		$nonRepliedToEmails = array_diff(array_keys($allRecipients), array_keys($excludedRecipients));

		return array_filter($allRecipients, function ($v, $k) use ($nonRepliedToEmails) {
			return in_array($k, $nonRepliedToEmails);
		}, ARRAY_FILTER_USE_BOTH);
	}

	/**
	 * Returns the ID of all Joomla user groups which are Comment Managers.
	 *
	 * Any user group that has the core.edit.state privilege on the component is considered a Comment Manager.
	 *
	 * @return  array
	 */
	protected function getCommentManagerGroups(): array
	{
		// Get all groups
		$db    = $this->db ?? Factory::getDbo();
		$query = $db->getQuery(true)
			->select([$db->qn('id')])
			->from($db->qn('#__usergroups'));

		$adminGroups = $db->setQuery($query)->loadColumn(0) ?? [];

		return array_filter($adminGroups, function ($group) {
			return
				Access::checkGroup($group, 'core.edit.state', 'com_engage') ||
				Access::checkGroup($group, 'core.edit.state') ||
				Access::checkGroup($group, 'core.admin');
		});
	}

	/**
	 * Returns the email addresses and names of comment managers.
	 *
	 * Only the email addresses of comments managers with Send Email set to Yes will be returned.
	 *
	 * @return  array  User full names keyed by email
	 */
	protected function getCommentManagerRecipients(): array
	{
		$managerGroups = $this->getCommentManagerGroups();

		if (empty($managerGroups))
		{
			return [];
		}

		$ret = [];

		foreach ($managerGroups as $gid)
		{
			$uids = Access::getUsersByGroup($gid);
			array_walk($uids, function ($uid, $k) use (&$ret) {
				$user              = Factory::getUser($uid);
				$ret[$user->email] = $ret[$user->name];
			});
		}

		return $ret;
	}

	/**
	 * Returns the emails and names of the people being replied to with a give comment
	 *
	 * @param   Comments  $comment
	 *
	 * @return  array  An dictionary following the convention email => name
	 */
	protected function getUserRecipients(Comments $comment): array
	{
		// Top level comments don't have reply-to users
		if (empty($comment->getFieldValue('parent_id')))
		{
			return [];
		}

		// Try to load the parent
		try
		{
			/** @var Comments $parent */
			$parent = $comment->tmpInstance();

			$parent->findOrFail($comment->parent_id);
		}
		catch (RecordNotLoaded $e)
		{
			return [];
		}

		$userEmails = [];

		$user                     = $parent->getUser();
		$userEmails[$user->email] = $user->name;

		return $userEmails;
	}

	/**
	 * Returns a list of email addresses which have been unsubscribed from comment notifications for the given Asset ID.
	 *
	 * @param   int  $asset_id  The asset ID for which email addresses have been unsubscribed.
	 *
	 * @return  string[]  A list of email addresses
	 */
	protected function getUnsubscribedEmails(int $asset_id): array
	{
		$db    = $this->db ?? Factory::getDbo();
		$query = $db->getQuery(true)
			->select($db->qn('email'))
			->from($db->qn('#__engage_unsubscribe'))
			->where($db->qn('asset_id') . ' = ' . $db->q($asset_id));

		return $db->setQuery($query)->loadColumn() ?? [];
	}

	/**
	 * Sends emails to a list of users about the given comment
	 *
	 * @param   string    $type               Email type. Must match the `key` column of the email templates table
	 * @param   Comments  $comment            The comment we are sending emails about
	 * @param   array     $recipients         A dictionary of email => name with the recipients
	 * @param   bool      $honorUnsubscribed  Should I honor unsubscribed emails? Default true.
	 *
	 * @return  void
	 */
	protected function sendEmailMessages(string $type, Comments $comment, array $recipients, bool $honorUnsubscribed = true): void
	{
		// Do I have anything to do?
		if (empty($recipients))
		{
			return;
		}

		// Remove users who have already unsubscribed from this content
		if ($honorUnsubscribed)
		{
			$unsubscribedEmails = $this->getUnsubscribedEmails($comment->asset_id);
			$recipients         = array_filter($recipients, function ($v, $k) use ($unsubscribedEmails) {
				return !in_array($k, $unsubscribedEmails);
			}, ARRAY_FILTER_USE_BOTH);
		}

		if (empty($recipients))
		{
			return;
		}

		try
		{
			$mailer = Factory::getMailer();

			$mailer->isHtml(true);
			$mailer->CharSet = 'UTF-8';
		}
		catch (Exception $e)
		{
			return;
		}

		$userIDs = $this->getUserIDsByEmail(array_keys($recipients));

		foreach ($recipients as $email => $name)
		{
			if (isset($userIDs[$email]))
			{
				$thisUser = Factory::getUser($userIDs[$email]);
			}
			else
			{
				$thisUser        = new User();
				$thisUser->name  = $name;
				$thisUser->email = $email;
			}

			$templateInfo = Email::loadEmailTemplateFromDB($type, $thisUser);

			// Dynamic properties. DO NOT use is_null() or empty()
			if (($templateInfo->subject === null) && ($templateInfo->template === null))
			{
				continue;
			}

			$templateInfo = Email::parseTemplate($templateInfo, $comment, $thisUser);

			try
			{
				$myMailer = clone $mailer;
				$myMailer->addRecipient($email, $name);
				$myMailer->setSubject($templateInfo->subject);
				$myMailer->setBody(Email::inlineImages($templateInfo->template, $myMailer));
				$myMailer->Send();
			}
			catch (Exception $e)
			{
				continue;
			}
		}
	}

	/**
	 * Returns the Joomla user IDs given a list of email addresses
	 *
	 * @param   string[]  $emails  The emails to look for
	 *
	 * @return  array  A dictionary of email => ID for any emails that correspond to Joomla user accounts
	 */
	protected function getUserIDsByEmail(array $emails): array
	{
		$db     = $this->db ?? Factory::getDbo();
		$emails = array_map([$db, 'q'], $emails);
		$query  = $db->getQuery(true)
			->select([
				$db->qn('email'),
				$db->qn('id'),
			])
			->from($db->qn('#__users'))
			->where($db->qn('email') . ' IN (' . implode(',', $emails) . ')');

		return $db->setQuery($query)->loadAssocList('email', 'id') ?? [];
	}
}
