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
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Mail\Mail;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use phpmailerException;

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

	public static function loadEmailTemplateFromDB($key, User $user = null): LoadedTemplate
	{
		// Initialise
		$templateText = '';
		$subject      = '';
		$container    = self::getContainer();

		$user = $user ?? $container->platform->getUser();

		// Look for desired languages
		$jLang     = Factory::getLanguage();
		$userLang  = $user->getParam('language', '');
		$languages = [
			$userLang,
			$jLang->getTag(),
			$jLang->getDefault(),
			'en-GB',
			'*',
		];

		/** @var EmailTemplates $model */
		$model = $container->factory->model('EmailTemplates')->tmpInstance();
		$model->key($key);
		$model->enabled(1);
		$model->where('language', 'in', $languages);

		/** @var EmailTemplates $emailTemplate */
		$allTemplates   = $model->get(true);
		$emailTemplate  = $allTemplates->take(1);
		$loadedLanguage = $allTemplates->reduce(function ($ret, EmailTemplates $t) {
			if ($ret !== '*')
			{
				return $ret;
			}

			return ($t->language === '*') ? $ret : $t->language;
		}, '*');

		$return = new LoadedTemplate([
			'subject'        => $emailTemplate->subject,
			'template'       => $emailTemplate->template,
			'loadedLanguage' => $loadedLanguage,
		]);

		// Because SpamAssassin demands there is a body and surrounding html tag even though it's not necessary.
		if (strpos($return->template, '<body') == false)
		{
			$return->template = '<body>' . $return->template . '</body>';
		}

		if (strpos($return->template, '<html') == false)
		{
			$return->template = <<< HTML
<html>
<head>
<title>{$return->subject}</title>
</head>
$return->template
</html>
HTML;

		}

		return $return;
	}

	/**
	 * Returns a new Joomla Mail object, set up to send UTF-8 encoded, HTML emails.
	 *
	 * @return  Mail  The mailer object
	 *
	 * @throws  phpmailerException
	 */
	public static function &getMailer(): Mail
	{
		$mailer = Factory::getMailer();

		// We always send HTML emails
		$mailer->isHtml(true);

		// Required to not get broken characters in emails
		$mailer->CharSet = 'UTF-8';

		return $mailer;
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

		Filter::includeHTMLPurifier();

		$config = HTMLPurifier_Config::createDefault();
		$config->set('Core.Encoding', 'UTF-8');
		$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
		$config->set('Cache.SerializerPath', \Akeeba\Engage\Site\Helper\Filter::getCachePath());
		$config->set('HTML.Allowed', 'p,b,a[href],i,u,strong,em,small,big,ul,ol,li,br,img[src],img[width],img[height],code,pre,blockquote');
		$purifier = new HTMLPurifier($config);

		$router      = Router::getInstance('site');
		$commentUser = $comment->getUser();
		$meta        = Meta::getAssetAccessMeta($comment->asset_id);
		$publicUri   = Uri::getInstance($meta['public_url']);

		$publicUri->setFragment('akengage-comment-' . $comment->getId());
		$publicUri->setVar('akengage_limitstart', Meta::getLimitStartForComment($comment));

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

		$replacements = [
			'[SITENAME]'          => $container->platform->getConfig()->get('sitename'),
			'[SITEURL]'           => Uri::base(false),
			'[NAME]'              => $commentUser->name,
			'[EMAIL]'             => $commentUser->email,
			'[IP]'                => $comment->ip,
			'[USER_AGENT]'        => $comment->user_agent,
			'[COMMENT]'           => $comment->body,
			'[COMMENT_SANITIZED]' => $purifier->purify($comment->body),
			'[DATE_ISO]'          => $jCreatedOn->toISO8601(),
			'[DATE_UTC]'          => $jCreatedOn->format($dateFormat, false),
			'[DATE_LOCAL]'        => $jCreatedOn->format($dateFormat, true),
			'[CONTENT_TITLE]'     => $meta['title'],
			'[CONTENT_LINK]'      => $meta['public_url'],
			'[COMMENT_LINK]'      => $publicUri->toString(),
			'[PUBLISH_URL]'       => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=publish&id=%d&returnurl=%s', $comment->getId(), $returnUrlComment))->toString(),
			'[UNPUBLISH_URL]'     => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=unpublish&id=%d&returnurl=%s', $comment->getId(), $returnUrl))->toString(),
			'[DELETE_URL]'        => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=remove&id=%d&returnurl=%s', $comment->getId(), $returnUrl))->toString(),
			'[POSSIBLESPAM_URL]'  => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=possiblespam&id=%d&returnurl=%s', $comment->getId(), $returnUrl))->toString(),
			'[SPAM_URL]'          => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=reportspam&id=%d&returnurl=%s', $comment->getId(), $returnUrl))->toString(),
			'[UNSPAM_URL]'        => $router->build(sprintf('index.php?option=com_engage&view=Comments&task=reportham&id=%d&returnurl=%s', $comment->getId(), $returnUrl))->toString(),
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
	private static function inlineImages($templateText, Mail $mailer)
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