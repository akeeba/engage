<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Plugin\Engage\Akismet\Extension;

defined('_JEXEC') or die();

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Exceptions\BlatantSpam;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Http\Response;
use RuntimeException;

/**
 * Akeeba Engage – Akismet integration for spam protection
 *
 * @since  1.0.0
 */
class Akismet extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Disallow registering legacy listeners since we use SubscriberInterface
	 *
	 * @since 3.0.0
	 * @var   bool
	 */
	protected $allowLegacyListeners = false;

	public static function getSubscribedEvents(): array
	{
		return [
			'onAkeebaEngageCheckSpam'  => 'onAkeebaEngageCheckSpam',
			'onAkeebaEngageReportHam'  => 'onAkeebaEngageReportHam',
			'onAkeebaEngageReportSpam' => 'onAkeebaEngageReportSpam',
		];
	}

	/**
	 * Handle the Akeeba Engage spam check event.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   1.0.0
	 */
	public function onAkeebaEngageCheckSpam(Event $event): void
	{
		/**
		 * @var   CommentTable|null $comment The comment to check
		 * @var   bool              $isNew   Is this a new comment?
		 */
		[$comment, $isNew] = $event->getArguments();
		$result = $event->getArgument('result', []);

		if (is_null($comment))
		{
			$event->setArgument('result', array_merge($result, [false]));

			return;
		}

		$checkWhen = $this->params->get('check', 'nonmanagers');
		$user      = $this->getUser($comment);

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
					$event->setArgument('result', array_merge($result, [false]));

					return;
				}
				break;

			// Only when the user is not a manager. Check if the user is a comments manager (core.edit on comments).
			case 'nonmanager':
				if ($user->authorise('core.edit'))
				{
					$event->setArgument('result', array_merge($result, [false]));

					return;
				}
				break;
		}

		try
		{
			$additional = [
				'referrer' => $this->getApplication()
					->input
					->server
					->getString('REFERER', null),
			];

			if (!$isNew)
			{
				$additional['recheck_reason'] = 'edit';
			}

			$response = $this->apiCall($comment, 'comment-check', $additional);
		}
		catch (Exception $e)
		{
			$event->setArgument('result', array_merge($result, [false]));

			return;
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

		$event->setArgument('result', array_merge($result, [$response->body == 'true']));
	}

	/**
	 * Handle the Akeeba Engage report ham (not spam) event.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function onAkeebaEngageReportHam(Event $event): void
	{
		[$comment] = $event->getArguments();
		$result = $event->getArgument('result', []);

		if (is_null($comment))
		{
			$event->setArgument('result', array_merge($result, [false]));

			return;
		}

		try
		{
			$additional = [
				'referrer' => $this->getApplication()
					->input
					->server
					->getString('REFERER', null),
			];

			$this->apiCall($comment, 'submit-ham', $additional);

			$event->setArgument('result', array_merge($result, [true]));
		}
		catch (Exception $e)
		{
			$event->setArgument('result', array_merge($result, [false]));
		}
	}

	/**
	 * Handle the Akeeba Engage report spam event.
	 *
	 * @param   Event  $event  The event we are handling
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	public function onAkeebaEngageReportSpam(Event $event): void
	{
		[$comment] = $event->getArguments();
		$result = $event->getArgument('result', []);

		if (is_null($comment))
		{
			$event->setArgument('result', array_merge($result, [false]));

			return;
		}

		try
		{
			$additional = [
				'referrer' => $this->getApplication()
					->input
					->server
					->getString('REFERER', null),
			];

			$this->apiCall($comment, 'submit-spam', $additional);

			$event->setArgument('result', array_merge($result, [true]));
		}
		catch (Exception $e)
		{
			$event->setArgument('result', array_merge($result, [false]));
		}
	}

	/**
	 * Execute an Akismet API call
	 *
	 * @param   CommentTable  $comment     The comment to execute an API call against
	 * @param   string        $action      Akismet API action to execute
	 * @param   array         $additional  Additional query string parameters
	 *
	 * @return  Response
	 * @throws  Exception
	 * @since   1.0.0
	 */
	private function apiCall(CommentTable $comment, string $action, array $additional = []): Response
	{
		$apiKey = trim($this->params->get('key', ''));

		if (empty($apiKey))
		{
			throw new RuntimeException('No Akismet API key was provided');
		}

		try
		{
			$createdOn = Factory::getDate($comment->created_on)->toISO8601();
		}
		catch (Exception $e)
		{
			$createdOn = null;
		}

		try
		{
			$meta        = Meta::getAssetAccessMeta();
			$jModifiedOn = $meta['published_on'];
			$modifiedOn  = $jModifiedOn->toISO8601();
		}
		catch (Exception $e)
		{
			$modifiedOn = null;
		}

		$commentUser = $this->getUser($comment);

		$struct = array_merge([
			'blog'                      => Uri::base(),
			'user_ip'                   => $comment->ip,
			'user_agent'                => $comment->user_agent,
			'comment_type'              => 'comment',
			'comment_author'            => $commentUser->name,
			'comment_author_email'      => $commentUser->email,
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
		$http     = HttpFactory::getHttp([
			'userAgent' => sprintf('Joomla/%s | AkeebaEngage/%s', JVERSION, defined('AKENGAGE_VERSION') ? AKENGAGE_VERSION : 'dev'),
		]);
		$uri      = new Uri($apiUrl . $action);
		$response = $http->post($uri->toString(), $struct);

		return $response;
	}

	/**
	 * Returns all the content languages of the site as a comma separated string suitable for Akismet.
	 *
	 * @return  string|null
	 * @since   1.0.0
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

	/**
	 * Get a user object from a comment object
	 *
	 * @param   CommentTable  $comment
	 *
	 * @return  User|null
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function getUser(CommentTable $comment): ?User
	{
		if ($comment->created_by)
		{
			return UserFetcher::getUser($comment->created_by);
		}

		$user        = new User(0);
		$user->name  = $comment->name;
		$user->email = $comment->email;

		return $user;
	}
}
