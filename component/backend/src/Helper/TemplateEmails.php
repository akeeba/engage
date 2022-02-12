<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseDriver;
use Exception;
use stdClass;

/**
 * Manage and send emails with Joomla's email templates component
 */
abstract class TemplateEmails
{
	/**
	 * Email templates known to Akeeba Ticket System.
	 */
	private const EMAIL_DEFINITIONS = [
		'com_engage.manage'       => [
			'subject'       => 'COM_ENGAGE_MAIL_MANAGE_SUBJECT',
			'bodyPlaintext' => 'COM_ENGAGE_MAIL_MANAGE_BODY',
			'bodyHtml'      => 'COM_ENGAGE_MAIL_MANAGE_BODY_HTML',
			'variables'     => [
				'SITEURL',
				'SITENAME',
				'RECIPIENT_NAME',
				'RECIPIENT_EMAIL',
				'CONTENT_LINK',
				'CONTENT_TITLE',
				'CONTENT_CATEGORY',
				'AVATAR_URL',
				'NAME',
				'EMAIL',
				'IP',
				'USER_AGENT',
				'DATE_LOCAL',
				'COMMENT_SANITIZED',
				'COMMENT_PLAINTEXT',
				'COMMENT_LINK',
				'PUBLISH_URL',
				'UNPUBLISH_URL',
				'DELETE_URL',
				'POSSIBLESPAM_URL',
				'SPAM_URL',
				'UNSPAM_URL',
				'UNSUBSCRIBE_URL',
			],
		],
		'com_engage.spam'       => [
			'subject'       => 'COM_ENGAGE_MAIL_SPAM_SUBJECT',
			'bodyPlaintext' => 'COM_ENGAGE_MAIL_SPAM_BODY',
			'bodyHtml'      => 'COM_ENGAGE_MAIL_SPAM_BODY_HTML',
			'variables'     => [
				'SITEURL',
				'SITENAME',
				'RECIPIENT_NAME',
				'RECIPIENT_EMAIL',
				'CONTENT_LINK',
				'CONTENT_TITLE',
				'CONTENT_CATEGORY',
				'AVATAR_URL',
				'NAME',
				'EMAIL',
				'IP',
				'USER_AGENT',
				'DATE_LOCAL',
				'COMMENT_SANITIZED',
				'COMMENT_PLAINTEXT',
				'COMMENT_LINK',
				'PUBLISH_URL',
				'UNPUBLISH_URL',
				'DELETE_URL',
				'POSSIBLESPAM_URL',
				'SPAM_URL',
				'UNSPAM_URL',
				'UNSUBSCRIBE_URL',
			],
		],
		'com_engage.notify'       => [
			'subject'       => 'COM_ENGAGE_MAIL_NOTIFY_SUBJECT',
			'bodyPlaintext' => 'COM_ENGAGE_MAIL_NOTIFY_BODY',
			'bodyHtml'      => 'COM_ENGAGE_MAIL_NOTIFY_BODY_HTML',
			'variables'     => [
				'SITEURL',
				'SITENAME',
				'RECIPIENT_NAME',
				'RECIPIENT_EMAIL',
				'CONTENT_LINK',
				'CONTENT_TITLE',
				'CONTENT_CATEGORY',
				'AVATAR_URL',
				'NAME',
				'EMAIL',
				'IP',
				'USER_AGENT',
				'DATE_LOCAL',
				'COMMENT_SANITIZED',
				'COMMENT_PLAINTEXT',
				'COMMENT_LINK',
				'PUBLISH_URL',
				'UNPUBLISH_URL',
				'DELETE_URL',
				'POSSIBLESPAM_URL',
				'SPAM_URL',
				'UNSPAM_URL',
				'UNSUBSCRIBE_URL',
			],
		],
		'com_engage.notify_managers'       => [
			'subject'       => 'COM_ENGAGE_MAIL_NOTIFY_MANAGERS_SUBJECT',
			'bodyPlaintext' => 'COM_ENGAGE_MAIL_NOTIFY_MANAGERS_BODY',
			'bodyHtml'      => 'COM_ENGAGE_MAIL_NOTIFY_MANAGERS_BODY_HTML',
			'variables'     => [
				'SITEURL',
				'SITENAME',
				'RECIPIENT_NAME',
				'RECIPIENT_EMAIL',
				'CONTENT_LINK',
				'CONTENT_TITLE',
				'CONTENT_CATEGORY',
				'AVATAR_URL',
				'NAME',
				'EMAIL',
				'IP',
				'USER_AGENT',
				'DATE_LOCAL',
				'COMMENT_SANITIZED',
				'COMMENT_PLAINTEXT',
				'COMMENT_LINK',
				'PUBLISH_URL',
				'UNPUBLISH_URL',
				'DELETE_URL',
				'POSSIBLESPAM_URL',
				'SPAM_URL',
				'UNSPAM_URL',
				'UNSUBSCRIBE_URL',
			],
		],
		'com_engage.notify_author'       => [
			'subject'       => 'COM_ENGAGE_MAIL_NOTIFY_AUTHOR_SUBJECT',
			'bodyPlaintext' => 'COM_ENGAGE_MAIL_NOTIFY_AUTHOR_BODY',
			'bodyHtml'      => 'COM_ENGAGE_MAIL_NOTIFY_AUTHOR_BODY_HTML',
			'variables'     => [
				'SITEURL',
				'SITENAME',
				'RECIPIENT_NAME',
				'RECIPIENT_EMAIL',
				'CONTENT_LINK',
				'CONTENT_TITLE',
				'CONTENT_CATEGORY',
				'AVATAR_URL',
				'NAME',
				'EMAIL',
				'IP',
				'USER_AGENT',
				'DATE_LOCAL',
				'COMMENT_SANITIZED',
				'COMMENT_PLAINTEXT',
				'COMMENT_LINK',
				'PUBLISH_URL',
				'UNPUBLISH_URL',
				'DELETE_URL',
				'POSSIBLESPAM_URL',
				'SPAM_URL',
				'UNSPAM_URL',
				'UNSUBSCRIBE_URL',
			],
		],

	];

	/**
	 * Returns the number of the known email templates.
	 *
	 * @return  int
	 */
	public static function countKnownTemplates(): int
	{
		return count(self::EMAIL_DEFINITIONS);
	}

	/**
	 * Returns the number of the know templates configured in the database.
	 *
	 * Remember that this may include templates which are out-of-date!
	 *
	 * @return  int
	 */
	public static function countTemplates(): int
	{
		return self::actOnTemplates('return');
	}

	/**
	 * Removes all email templates we know about.
	 *
	 * WARNING! THIS ALSO REMOVES THE USER-GENERATED EMAIL TEMPLATES FOR ALL KEYS WE KNOW.
	 *
	 * @return  int Number of email template keys affected
	 */
	public static function deleteAllTemplates(): int
	{
		return self::actOnTemplates('delete');
	}

	/**
	 * Returns the keys of the known email templates.
	 *
	 * @return  string[]
	 */
	public static function getKnownTemplatesKeys(): array
	{
		return array_keys(self::EMAIL_DEFINITIONS);
	}

	/**
	 * Checks whether the main email template for the specific key exists in the database.
	 *
	 * It does NOT check if the template is up-to-date.
	 *
	 * @param   string  $key
	 *
	 * @return  bool
	 */
	public static function hasTemplate(string $key): bool
	{
		return self::actOnTemplate($key, 'return');
	}

	/**
	 * Resets all email templates we know about.
	 *
	 * WARNING! THIS ALSO REMOVES THE USER-GENERATED EMAIL TEMPLATES FOR ALL KEYS WE KNOW.
	 *
	 * @return  int Number of email template keys affected
	 */
	public static function resetAllTemplates(): int
	{
		return self::actOnTemplates('reset');
	}

	/**
	 * Resets an email template.
	 *
	 * WARNING! THIS ALSO REMOVES THE USER-GENERATED EMAIL TEMPLATES FOR THIS KEY.
	 *
	 * @param   string  $key
	 *
	 * @return  bool
	 */
	public static function resetTemplate(string $key)
	{
		return self::actOnTemplate($key, 'reset');
	}

	/**
	 * Sends an email using a template.
	 *
	 * WARNING! THIS DOES NOT CHECK IF THE TEMPLATE EXISTS. USE TemplateEmails::updateTemplate($key) FIRST.
	 *
	 * @param   string       $key            The email template key to send
	 * @param   array        $data           The variable/tag associative array to include in the email
	 * @param   User|null    $user           The user to send the email to. NULL for the currently logged in user.
	 * @param   string|null  $forceLanguage  Force a specific language tag instead of using the user's preferences.
	 * @param   bool         $throw          False (default) to return false on error, True to throw the exception back
	 *                                       to you.
	 *
	 * @return  bool True if the email was sent.
	 * @throws  Exception When $throw === true and there's an error sending the email
	 */
	public static function sendMail(string $key, array $data, User $user = null, string $forceLanguage = null, bool $throw = false, Mail $mailer = null): bool
	{
		if (empty($user))
		{
			$user = UserFetcher::getUser();
		}

		//if ($user->guest || $user->block || !$user->sendEmail)
		if ($user->guest || $user->block)
		{
			return false;
		}

		try
		{
			/**
			 * We create a custom mailer, setting its priority to normal.
			 *
			 * Even though the Priority is nominally optional, SpamAssassin will reject emails without a priority.
			 * That's a major WTF which even Joomla itself doesn't know about :O
			 */
			if (empty($mailer))
			{
				$mailer           = Factory::getMailer();
				$mailer->Priority = 3;
			}


			$app              = Factory::getApplication();
			$appLang          = $app->getLanguage() ?? null;
			$appLang          = is_object($appLang) ? $appLang->getTag() : null;
			$userLang         = $app->isClient('administrator') ? $user->getParam('administrator_language') : $user->getParam('language');
			$userFrontendLang = $user->getParam('language');
			$langTag          = $userLang ?: $userFrontendLang ?: $appLang ?: 'en-GB';
			$langTag          = $forceLanguage ?: $langTag;

			/**
			 * Try to get the template. Remember that Joomla looks for the specific language tag or the main template
			 * which defines no language and falls back to translation strings.
			 */
			$template = MailTemplate::getTemplate($key, $langTag);

			if (empty($template))
			{
				// Yeah, well, there's no template. I can't send the email, I'm afrain.
				return false;
			}

			$templateMailer = new MailTemplate($key, $langTag, $mailer);
			$templateMailer->addTemplateData($data);
			$templateMailer->addRecipient(trim($user->email), $user->name);

			return $templateMailer->send();
		}
		catch (Exception $e)
		{
			if ($throw)
			{
				throw $e;
			}

			return false;
		}
	}

	/**
	 * Update all email templates we know about.
	 *
	 * This operates only on the mail templates. User–generated templates are kept as-is.
	 *
	 * @return  int
	 */
	public static function updateAllTemplates(): int
	{
		return self::actOnTemplates('fix');
	}

	/**
	 * Updates a specific email template.
	 *
	 * Makes sure that the main email template exists in the database. If it doesn't, it's created. If it exists and its
	 * variables (tags), subject, body (plaintext) or body (HTML) differ it will be updated. Otherwise no further action
	 * is taken.
	 *
	 * @param   string  $key
	 *
	 * @return bool
	 */
	public static function updateTemplate(string $key)
	{
		return self::actOnTemplate($key, 'fix');
	}

	private static function actOnTemplate(string $key, string $action = 'return'): bool
	{
		/**
		 * Note that we are only checking the email template WITHOUT a language. This is considered the "default" email
		 * template from which all the localised email templates are generated. We only care if that email template
		 * exists and is up to date. We don't mess with the user-defined email templates, ever!
		 */
		try
		{
			/** @var DatabaseDriver $db */
			$db    = Factory::getContainer()->get('DatabaseDriver');
			$query = $db->getQuery(true);
			$query->select('*')
				->from($db->quoteName('#__mail_templates'))
				->where($db->quoteName('template_id') . ' = :key')
				->where($db->quoteName('language') . ' = ' . $db->quote(''))
				->order($db->quoteName('language') . ' DESC')
				->bind(':key', $key);

			$templateInDB = $db->setQuery($query)->loadAssoc() ?: [];
			$hasTemplate  = !empty($templateInDB);
		}
		catch (\Exception $e)
		{
			$templateInDB = [];
			$hasTemplate  = false;
		}

		$knownTemplate = array_key_exists($key, self::EMAIL_DEFINITIONS);
		$action        = strtolower($action);

		switch (strtolower($action))
		{
			// Ensures a template exists and its definition is up-to-date
			case 'fix':
				if (!$knownTemplate)
				{
					return false;
				}

				// The template does not exist in the database. Create it.
				if (!$hasTemplate)
				{
					$record = self::EMAIL_DEFINITIONS[$key];
					self::createTemplate($key, $record['subject'], $record['bodyPlaintext'], $record['variables'], $record['bodyHtml'] ?? '');

					return true;
				}

				$record = self::EMAIL_DEFINITIONS[$key];

				// Do I need to update the record? We check the variables, subject and the plaintext and HTML bodies.
				try
				{
					$params         = json_decode($templateInDB['params'], true);
					$variablesInDB  = array_map('strtoupper', (array) $params['tags'][0] ?? []);
					$variablesKnown = array_map('strtoupper', $record['variables'] ?? []);
					$isIdentical    = empty(array_diff($variablesKnown, $variablesInDB));

					$isIdentical = $isIdentical && ($templateInDB['subject'] == $record['subject']);
					$isIdentical = $isIdentical && ($templateInDB['body'] == $record['bodyPlaintext']);
					$isIdentical = $isIdentical && ($templateInDB['htmlbody'] == $record['bodyHtml']);
				}
				catch (\Exception $e)
				{
					// The template is corrupt. We will reset it.
					return self::actOnTemplate($key, 'reset');
				}

				// The template in the DB is up-to-date. Bye-bye!
				if ($isIdentical)
				{
					return true;
				}

				// There were differences. Let's update the template.
				self::updateTemplateInDB($key, $record['subject'], $record['bodyPlaintext'], $record['variables'], $record['bodyHtml'] ?? '');

				return true;
				break;

			// Forcibly update a template if exists
			case 'update':
				if (!$knownTemplate)
				{
					return false;
				}

				if (!$hasTemplate)
				{
					return true;
				}

				$record = self::EMAIL_DEFINITIONS[$key];
				self::updateTemplateInDB($key, $record['subject'], $record['bodyPlaintext'], $record['variables'], $record['bodyHtml'] ?? '');

				return true;
				break;

			// Forcibly reset a template
			case 'reset':
				if (!$knownTemplate)
				{
					return false;
				}

				if ($hasTemplate)
				{
					MailTemplate::deleteTemplate($key);
				}

				$record = self::EMAIL_DEFINITIONS[$key];
				self::createTemplate($key, $record['subject'], $record['bodyPlaintext'], $record['variables'], $record['bodyHtml'] ?? '');

				return true;
				break;

			// Only return whether a template exists
			case 'return':
			default:
				return $hasTemplate;
				break;
		}
	}

	private static function actOnTemplates(string $action = 'return'): int
	{
		$count = 0;

		foreach (array_keys(self::EMAIL_DEFINITIONS) as $key)
		{
			if ($action === 'delete')
			{
				MailTemplate::deleteTemplate($key);

				continue;
			}

			if (self::actOnTemplate($key, $action))
			{
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Fork of MailTemplate::createTemplate WHICH ACTUALLY WORKS WITHOUT THROWING ERRORS.
	 *
	 * Insert a new mail template into the system
	 *
	 * @param   string  $key       Mail template key
	 * @param   string  $subject   A default subject (normally a translatable string)
	 * @param   string  $body      A default body (normally a translatable string)
	 * @param   array   $tags      Associative array of tags to replace
	 * @param   string  $htmlbody  A default htmlbody (normally a translatable string)
	 *
	 * @return  bool  True on success, false on failure
	 *
	 * @since   7.0.0
	 */
	private static function createTemplate(string $key, string $subject, string $body, array $tags, string $htmlbody = ''): bool
	{
		/** @var DatabaseDriver $db */
		$db = Factory::getContainer()->get('DatabaseDriver');

		$template              = new stdClass();
		$template->template_id = $key;
		$template->language    = '';
		$template->subject     = $subject;
		$template->body        = $body;
		$template->htmlbody    = $htmlbody;
		$template->attachments = '';
		$template->extension   = explode('.', $key, 2)[0];
		$params                = new stdClass();
		$params->tags          = $tags;
		$template->params      = json_encode($params);

		return $db->insertObject('#__mail_templates', $template);
	}

	/**
	 * Fork of MailTemplate::updateTemplate WHICH ACTUALLY WORKS WITHOUT THROWING ERRORS.
	 *
	 * Update an existing mail template
	 *
	 * @param   string  $key       Mail template key
	 * @param   string  $subject   A default subject (normally a translatable string)
	 * @param   string  $body      A default body (normally a translatable string)
	 * @param   array   $tags      Associative array of tags to replace
	 * @param   string  $htmlbody  A default htmlbody (normally a translatable string)
	 *
	 * @return  bool  True on success, false on failure
	 *
	 * @since   7.0.0
	 */
	private static function updateTemplateInDB($key, $subject, $body, $tags, $htmlbody = '')
	{
		/** @var DatabaseDriver $db */
		$db = Factory::getContainer()->get('DatabaseDriver');

		$template              = new stdClass();
		$template->template_id = $key;
		$template->language    = '';
		$template->subject     = $subject;
		$template->body        = $body;
		$template->htmlbody    = $htmlbody;
		$params                = new stdClass();
		$params->tags          = (array) $tags;
		$template->params      = json_encode($params);

		return $db->updateObject('#__mail_templates', $template, ['template_id', 'language']);
	}

}