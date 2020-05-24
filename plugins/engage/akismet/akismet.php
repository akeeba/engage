<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Engage\Admin\Model\Comments;
use Akeeba\Engage\Admin\Model\Exception\BlatantSpam;
use FOF30\Date\Date;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Http\Response;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;

/**
 * Akeeba Engage – Akismet integration for spam protection
 *
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
class plgEngageAkismet extends CMSPlugin
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
	}

	public function onAkeebaEngageCheckSpam(?Comments $comment, bool $isNew = true): ?bool
	{
		if (is_null($comment))
		{
			return false;
		}

		$checkWhen = $this->params->get('check', 'nonmanagers');
		$user      = $comment->getUser();

		switch ($checkWhen)
		{
			// Check all comments. Skip this check.
			default:
			case 'all':
				break;

			// Only check Guest comments. Check whether the user is a guest.
			case 'guest':
				if (!$user->guest)
				{
					return false;
				}
				break;

			// Only when the user is not a manager. Check if the user is a comments manager (core.edit on comments).
			case 'nonmanager':
				if ($user->authorise('core.edit'))
				{
					return false;
				}
				break;
		}

		try
		{
			$app = Factory::getApplication();

			$additional = [
				'referrer' => $app->input->server->getString('REFERER', null),
			];

			if (!$isNew)
			{
				$additional['recheck_reason'] = 'edit';
			}

			$response = $this->apiCall($comment, 'comment-check', $additional);
		}
		catch (Exception $e)
		{
			return false;
		}

		// Should I discard blatant spam?
		$discardBlatant = $this->params->get('discard_blatant', 1) == 1;

		if ($discardBlatant && array_key_exists('X-akismet-pro-tip', $response->headers) && $response->headers['X-akismet-pro-tip'] == 'discard')
		{
			if (class_exists('Akeeba\Engage\Admin\Model\Exception\BlatantSpam'))
			{
				throw new BlatantSpam();
			}

			throw new RuntimeException('Your comment has been identified as a blatant spam and was discarded without further consideration.');
		}

		return $response->body == 'true';
	}

	private function apiCall(Comments $comment, string $action, array $additional = []): Response
	{
		$apiKey = trim($this->params->get('key', ''));

		if (empty($apiKey))
		{
			throw new RuntimeException('No Akismet API key was provided');
		}

		try
		{
			$createdOn = (new Date($comment->created_on))->toISO8601();
		}
		catch (Exception $e)
		{
			$createdOn = null;
		}

		try
		{
			$jModifiedOn = new Date($comment->modified_on);

			if ($jModifiedOn->toUnix() < 765158400)
			{
				throw new Exception('Not a real date, is it?');
			}

			$modifiedOn = $jModifiedOn->toISO8601();
		}
		catch (Exception $e)
		{
			$modifiedOn = null;
		}

		$struct = array_merge([
			'blog'                      => Uri::base(),
			'user_ip'                   => $comment->ip,
			'user_agent'                => $comment->user_agent,
			'comment_type'              => 'comment',
			'comment_author'            => $comment->getUser()->name,
			'comment_author_email'      => $comment->getUser()->email,
			'comment_content'           => $comment->body,
			'comment_date_gmt'          => $createdOn,
			'comment_post_modified_gmt' => $modifiedOn,
			'blog_lang'                 => $this->getSiteLanguages(),
			'blog_charset'              => 'UTF-8',
		], $additional);

		$struct = array_filter($struct, function ($x) {
			return !is_null($x);
		});

		$apiUrl   = "https://{$apiKey}.rest.akismet.com/1.1/";
		$http     = HttpFactory::getHttp();
		$uri      = new Uri($apiUrl . $action);
		$response = $http->post($uri->toString(), $struct);

		return $response;
	}

	/**
	 * Returns all the content languages of the site as a comma separated string suitable for Akismet.
	 *
	 * @return  string|null
	 */
	private function getSiteLanguages(): ?string
	{
		try
		{
			$joomlaLanguages = LanguageHelper::getContentLanguages();
			$langCodes       = array_map(function ($x) {
				return strtolower(str_replace('-', '_', $x));
			}, array_keys($joomlaLanguages));
		}
		catch (Exception $e)
		{
			$langCodes = [];
		}

		if (empty($langCodes))
		{
			return null;
		}

		return implode(',', $langCodes);
	}
}
