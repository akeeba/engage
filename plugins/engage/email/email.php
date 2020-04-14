<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engage\Admin\Model\Comments;
use Akeeba\Engage\Site\Helper\Email;
use Akeeba\Engage\Site\Helper\Meta;
use FOF30\Container\Container;
use FOF30\Model\DataModel\Collection as DataCollection;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

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

		$meta         = Meta::getAssetAccessMeta($comment->asset_id, true);
		$notifyAuthor = $meta['parameters']->get('comments_notify_author', 1);
		$notifyUsers  = $meta['parameters']->get('comments_notify_users', 1);

		switch ($comment->enabled)
		{
			// Unpublished or Spam: send an email to Comment Managers
			case 0:
			case -3:
				$recipients = $this->getCommentManagerRecipients();
				$type       = ($comment->enabled == 0) ? 'manage' : 'spam';
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

		$this->sendEmailMessages($type, $comment, $recipients);
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
			return Access::checkGroup($group, 'core.edit.state', 'com_engage');
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

		$db         = $this->db ?? Factory::getDbo();
		$innerQuery = $db->getQuery(true)
			->select([$db->qn('user_id')])
			->from($db->qn('#__user_usergroup_map'))
			->where($db->q('group_id') . ' IN (' . implode(',', $managerGroups) . ')');
		$query      = $db->getQuery(true)
			->select([
				$db->qn['email'],
				$db->qn['name'],
			])
			->from($db->qn('#__users'))
			->where($db->qn('id') . ' IN (' . $innerQuery . ')')
			->where($db->qn('sendEmail') . ' = ' . $db->q(1));

		return $db->setQuery($query)->loadAssocList('email', 'name') ?? [];
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
		$userEmails   = [];
		$commentLevel = $comment->getLevel();

		// First level comments are not in reply to anything; can't send emails to any users
		if ($commentLevel == 1)
		{
			return [];
		}

		$container = Container::getInstance('com_engage');
		$maxLevel  = $container->params->get('max_level', 3);

		/**
		 * Which comments are being replied to depends on the level of the comment being filed.
		 *
		 * If it's any level less than max we are ONLY replying to its direct parent.
		 * If it's max level we are replying to its direct parent AND its siblings.
		 */
		$collection = new DataCollection();
		$collection->add($comment->getParent());

		if ($commentLevel == $maxLevel)
		{
			$collection->merge($comment->getSiblings());
		}

		$collection->each(function (Comments $comment) use (&$userEmails, &$unsubscribedEmails) {
			$user                     = $comment->getUser();
			$userEmails[$user->email] = $user->name;
		});

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
	 * @param   string    $type        Email type. Must match the `key` column of the email templates table
	 * @param   Comments  $comment     The comment we are sending emails about
	 * @param   array     $recipients  A dictionary of email => name with the recipients
	 *
	 * @return  void
	 */
	protected function sendEmailMessages(string $type, Comments $comment, array $recipients): void
	{
		// Do I have anything to do?
		if (empty($recipients))
		{
			return;
		}

		// Remove users who have already unsubscribed from this content
		$unsubscribedEmails = $this->getUnsubscribedEmails($comment->asset_id);
		$recipients         = array_filter($recipients, function ($v, $k) use ($unsubscribedEmails) {
			return !in_array($k, $unsubscribedEmails);
		}, ARRAY_FILTER_USE_BOTH);

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
				$thisUser        = new Joomla\CMS\User\User();
				$thisUser->name  = $name;
				$thisUser->email = $email;
			}

			$templateInfo = Email::loadEmailTemplateFromDB($type, $thisUser);

			if (empty($templateInfo->subject) && empty($templateInfo->template))
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
		$db    = $this->db ?? Factory::getDbo();
		$query = $db->getQuery(true)
			->select([
				$db->qn('email'),
				$db->qn('id'),
			])
			->from($db->qn('#__users'))
			->where($db->qn('email') . ' IN (' . implode(',', $emails) . ')');

		return $db->setQuery($query)->loadAssocList('email', 'id') ?? [];
	}
}