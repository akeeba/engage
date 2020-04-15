<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Helper;


use Akeeba\Engage\Admin\Model\Comments;
use Akeeba\Engage\Site\Model\EmailTemplates;
use Akeeba\Engage\Site\Model\Struct\LoadedTemplate;
use DateTimeZone;
use Exception;
use FOF30\Container\Container;
use FOF30\Date\Date;
use HTMLPurifier;
use HTMLPurifier_Config;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

final class Email
{
	/**
	 * Allowed image file extensions to inline in sent emails
	 *
	 * @var   array
	 */
	private static $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg'];

	/**
	 * Cached reference to the component container
	 *
	 * @var  Container
	 */
	private static $container;

	/**
	 * Cache of loaded templates by the hash of their type and languages searched
	 *
	 * @var  LoadedTemplate[]
	 */
	private static $loadedTemplates = [];

	/**
	 * Sanitized versions of comments, keyed by comment ID
	 *
	 * @var string[]
	 */
	private static $purifiedComments = [];

	public static function loadEmailTemplateFromDB($key, User $user = null): LoadedTemplate
	{
		// Initialise
		$container = self::getContainer();

		$user = $user ?? $container->platform->getUser();

		// Determine the languages we should be searching for and in which order to search them.
		$jLang     = $container->platform->getLanguage();
		$userLang  = $user->getParam('language', '');
		$languages = array_filter([
			$userLang,
			$jLang->getTag(),
			$jLang->getDefault(),
			'en-GB',
			'*',
		], function ($l) {
			return !empty($l);
		});

		$languages = array_unique($languages);
		$hash      = md5($key . '-' . json_encode($languages));

		if (isset(self::$loadedTemplates[$hash]))
		{
			return self::$loadedTemplates[$hash];
		}

		/** @var EmailTemplates $model */
		$model = $container->factory->model('EmailTemplates')->tmpInstance();
		$model->key($key);
		$model->enabled(1);
		$model->where('language', 'in', $languages);

		$allTemplates = $model->get(true);

		if (empty($allTemplates))
		{
			self::$loadedTemplates[$hash] = new LoadedTemplate();

			return self::$loadedTemplates[$hash];
		}

		/** @var EmailTemplates $emailTemplate */
		$emailTemplate = $allTemplates->first();

		if (empty($emailTemplate))
		{
			self::$loadedTemplates[$hash] = new LoadedTemplate();

			return self::$loadedTemplates[$hash];
		}


		$loadedLanguage = $allTemplates->reduce(function ($ret, EmailTemplates $t) {
			if ($ret !== '*')
			{
				return $ret;
			}

			return ($t->language === '*') ? $ret : $t->language;
		}, '*');

		self::$loadedTemplates[$hash] = new LoadedTemplate([
			'subject'        => $emailTemplate->subject,
			'template'       => $emailTemplate->template,
			'loadedLanguage' => $loadedLanguage,
		]);

		// Because SpamAssassin demands there is a body and surrounding html tag even though it's not necessary.
		if (strpos(self::$loadedTemplates[$hash]->template, '<body') == false)
		{
			self::$loadedTemplates[$hash]->template = '<body>' . self::$loadedTemplates[$hash]->template . '</body>';
		}

		if (strpos(self::$loadedTemplates[$hash]->template, '<html') == false)
		{
			$subject                                = self::$loadedTemplates[$hash]->subject;
			$template                               = self::$loadedTemplates[$hash]->template;
			self::$loadedTemplates[$hash]->template = <<< HTML
<html>
<head>
<title>{$subject}</title>
</head>
{$template}
</html>
HTML;
		}

		return self::$loadedTemplates[$hash];
	}

	/**
	 * Parses template text and subject with email variables
	 *
	 * @param   LoadedTemplate  $emailTemplate  Loaded email template
	 * @param   array           $mailInfo       Associative array of variables to replace in the body and subject text
	 *
	 * @return  LoadedTemplate
	 */
	public static function parseTemplate(LoadedTemplate $emailTemplate, Comments $comment, ?User $recipient): LoadedTemplate
	{
		$container = self::getContainer();

		if (is_null($recipient))
		{
			$recipient = $container->platform->getUser();
		}

		$commentUser = $comment->getUser();
		$meta        = Meta::getAssetAccessMeta($comment->asset_id);
		$publicUri   = Uri::getInstance($meta['public_url']);

		$publicUri->setFragment('akengage-comment-' . $comment->getId());
		$publicUri->setVar('akengage_limitstart', Meta::getLimitStartForComment($comment, null, $recipient->authorise('core.edit.state', 'com_engage')));

		$zone = $recipient->getParam('timezone', $container->platform->getConfig()->get('offset', 'UTC'));

		try
		{
			$tz = new DateTimeZone($zone);
		}
		catch (Exception $e)
		{
			$tz = new DateTimeZone('UTC');
		}

		$jCreatedOn = new Date($comment->created_on);
		$dateFormat = Text::_('DATE_FORMAT_LC2');

		$jCreatedOn->setTimezone($tz);

		$returnUrl        = base64_encode($meta['public_url']);
		$returnUrlComment = base64_encode($publicUri->toString());
		$protoUrl         = 'index.php?option=com_engage&view=Comments&task=%s&returnurl=%s';

		$replacements = [
			'[SITENAME]'          => $container->platform->getConfig()->get('sitename'),
			'[SITEURL]'           => Uri::base(false),
			'[RECIPIENT:NAME]'    => htmlentities($recipient->name),
			'[RECIPIENT:EMAIL]'   => htmlentities($recipient->email),
			'[NAME]'              => htmlentities($commentUser->name),
			'[EMAIL]'             => htmlentities($commentUser->email),
			'[IP]'                => htmlentities($comment->ip),
			'[USER_AGENT]'        => htmlentities($comment->user_agent),
			'[COMMENT]'           => $comment->body,
			'[COMMENT_SANITIZED]' => self::purifyComment($comment),
			'[DATE_ISO]'          => $jCreatedOn->toISO8601(),
			'[DATE_UTC]'          => $jCreatedOn->format($dateFormat, false),
			'[DATE_LOCAL]'        => $jCreatedOn->format($dateFormat, true),
			'[CONTENT_TITLE]'     => htmlentities($meta['title']),
			'[CONTENT_LINK]'      => $meta['public_url'],
			'[COMMENT_LINK]'      => $publicUri->toString(),
			'[PUBLISH_URL]'       => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'publish', $returnUrlComment), $comment, $recipient->email),
			'[UNPUBLISH_URL]'     => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'unpublish', $returnUrl), $comment, $recipient->email),
			'[DELETE_URL]'        => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'remove', $returnUrl), $comment, $recipient->email),
			'[POSSIBLESPAM_URL]'  => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'possiblespam', $returnUrlComment), $comment, $recipient->email),
			'[SPAM_URL]'          => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'reportspam', $returnUrl), $comment, $recipient->email),
			'[UNSPAM_URL]'        => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'reportham', $returnUrlComment), $comment, $recipient->email),
			'[UNSUBSCRIBE_URL]'   => SignedURL::getAbsoluteSignedURL(sprintf($protoUrl, 'unsubscribe', $returnUrl), $comment, $recipient->email),
			'[AVATAR_URL]'        => $comment->getAvatarURL(48),
		];

		foreach (['template', 'subject'] as $prop)
		{
			$emailTemplate->{$prop} = str_replace(array_keys($replacements), array_values($replacements), $emailTemplate->{$prop});
		}

		return $emailTemplate;
	}

	/**
	 * Attach and inline the referenced images in the email message
	 *
	 * @param   string  $templateText
	 * @param   Mail    $mailer
	 *
	 * @return  string
	 */
	public static function inlineImages($templateText, Mail $mailer)
	{
		// RegEx patterns to detect images
		$patterns = [
			// srcset="**URL**" e.g. source tags
			'/srcset=\"?([^"]*)\"?/i',
			// src="**URL**" e.g. img tags
			'/src=\"?([^"]*)\"?/i',
			// url(**URL**) nad url("**URL**") i.e. inside CSS
			'/url\(\"?([^"\(\)]*)\"?\)/i',
		];

		// Cache of images so we don't inline them multiple times
		$foundImages = [];
		// Running counter of images, used to create the attachment IDs in the message
		$imageIndex = 0;

		// Run a RegEx search & replace for each pattern
		foreach ($patterns as $pattern)
		{
			// $matches[0]: the entire string matched by RegEx; $matches[1]: just the path / URL
			$templateText = preg_replace_callback($pattern, function (array $matches) use ($mailer, &$foundImages, &$imageIndex): string {
				// Abort if it's not a file type we can inline
				if (!self::isInlineableFileExtension($matches[1]))
				{
					return $matches[0];
				}

				// Try to get the local absolute filesystem path of the referenced media file
				$localPath = self::getLocalAbsolutePath(self::normalizeURL($matches[1]));

				// Abort if this was not a relative / absolute URL pointing to our own site
				if (empty($localPath))
				{
					return $matches[0];
				}

				// Abort if the referenced file does not exist
				if (!@file_exists($localPath) || !@is_file($localPath))
				{
					return $matches[0];
				}

				// Make sure the inlined image is cached; prevent inlining the same file multiple times
				if (!array_key_exists($localPath, $foundImages))
				{
					$imageIndex++;
					$mailer->AddEmbeddedImage($localPath, 'img' . $imageIndex, basename($localPath));
					$foundImages[$localPath] = $imageIndex;
				}

				return str_replace($matches[1], $toReplace = 'cid:img' . $foundImages[$localPath], $matches[0]);
			}, $templateText);
		}

		// Return the processed email content
		return $templateText;
	}

	/**
	 * Returns a purified version of the comment text.
	 *
	 * The purified version permits a very small, highly controllable subset of HTML to go through. While this removes a
	 * lot of formatting it also protects against security issues if a comment with malicious content is previewed in a
	 * mail client. Normally this should NOT be necessary. However, initial filtering of the HTML content of the comment
	 * is up to Joomla's text filters and the site owner's configuration of Akeeba Engage. Nothing stops a misguided
	 * site owner from allowing unfiltered HTML from strangers without even a CAPTCHA, opening up their site to a host
	 * of vulnerabilities. We can provide sane defaults, we can document things but ultimately it's not up to us to
	 * protect misguided users against themselves.
	 *
	 * @param   Comments  $comment
	 *
	 * @return  string
	 */
	protected static function purifyComment(Comments $comment): string
	{
		$id = $comment->getId();

		if (isset(self::$purifiedComments[$id]))
		{
			return self::$purifiedComments[$id];
		}

		Filter::includeHTMLPurifier();

		$config = HTMLPurifier_Config::createDefault();
		$config->set('Core.Encoding', 'UTF-8');
		$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
		$config->set('Cache.SerializerPath', \Akeeba\Engage\Site\Helper\Filter::getCachePath());
		$config->set('HTML.Allowed', 'p,b,a[href],i,u,strong,em,small,big,ul,ol,li,br,img[src],img[width],img[height],code,pre,blockquote');
		$purifier = new HTMLPurifier($config);

		self::$purifiedComments[$id] = $purifier->purify($comment->body);

		return self::$purifiedComments[$id];
	}

	/**
	 * Does this file / URL have an allowed image extension for inlining?
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  bool
	 */
	private static function isInlineableFileExtension($fileOrUri)
	{
		$dot = strrpos($fileOrUri, '.');

		if ($dot === false)
		{
			return false;
		}

		$extension = substr($fileOrUri, $dot + 1);

		return in_array(strtolower($extension), self::$allowedImageExtensions);
	}

	/**
	 * Normalizes an image relative or absolute URL as an absolute URL
	 *
	 * @param   string  $fileOrUri
	 *
	 * @return  string
	 */
	private static function normalizeURL($fileOrUri)
	{
		// Empty file / URIs are returned as-is (obvious screw up)
		if (empty($fileOrUri))
		{
			return $fileOrUri;
		}

		// Remove leading / trailing slashes
		$fileOrUri = trim($fileOrUri, '/');

		// HTTPS URLs are returned as-is
		if (substr($fileOrUri, 0, 8) == 'https://')
		{
			return $fileOrUri;
		}

		// HTTP URLs are returned upgraded to HTTPS
		if (substr($fileOrUri, 0, 7) == 'http://')
		{
			return 'https://' . substr($fileOrUri, 7);
		}

		// Normalize URLs with a partial schema as HTTPS
		if (substr($fileOrUri, 0, 3) == '://')
		{
			return 'https://' . substr($fileOrUri, 3);
		}

		// This is a file. We assume it's relative to the site's root
		return rtrim(Uri::base(), '/') . '/' . $fileOrUri;
	}

	/**
	 * Return the path to the local file referenced by the URL, provided it's internal.
	 *
	 * @param   string  $url
	 *
	 * @return  string|null  The local file path. NULL if the URL is not internal.
	 */
	private static function getLocalAbsolutePath($url)
	{
		$base = rtrim(Uri::base(), '/');

		if (strpos($url, $base) !== 0)
		{
			return null;
		}

		return JPATH_ROOT . '/' . ltrim(substr($url, strlen($base) + 1), '/');
	}

	/**
	 * Gets the component's container
	 *
	 * @return  Container
	 */
	private static function getContainer(): Container
	{
		if (!is_null(self::$container))
		{
			return self::$container;
		}

		self::$container = Container::getInstance('com_engage');

		return self::$container;
	}
}