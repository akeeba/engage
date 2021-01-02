<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Model;

defined('_JEXEC') or die();

use Akeeba\Engage\Admin\Model\Comments as AdminCommentsModel;
use Exception;
use FOF30\Model\Mixin\Assertions;
use FOF30\Utils\Ip;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Factory;
use RuntimeException;

/**
 * @inheritDoc
 *
 * @method $this useCaptcha(bool $useCaptcha) Should I use the CAPTCHA when saving a record? DEFAULTS TO FALSE.
 */
class Comments extends AdminCommentsModel
{
	use Assertions;

	/** @inheritDoc */
	public function check()
	{
		// Check the CAPTCHA
		$this->assertCaptchaSolved();

		// Add an IP address if none has been provided yet
		if (empty($this->getFieldValue('ip')))
		{
			$this->ip = Ip::getIp();
		}

		// Log the User Agent string if none was provided
		if (empty($this->getFieldValue('user_agent')))
		{
			$this->user_agent = $this->container->platform->isCli()
				? ''
				: trim($this->input->server->getString('HTTP_USER_AGENT', ''));
		}

		parent::check();
	}

	/**
	 * Get the Joomla! CAPTCHA object
	 *
	 * @param   string  $namespace
	 *
	 * @return  Captcha|null
	 */
	public function getCaptcha($namespace = 'akeeba_engage'): ?Captcha
	{
		// Get the default CAPTCHA set up in Joomla's Global Configuration
		try
		{
			/** @var SiteApplication $app */
			$app     = Factory::getApplication();
			$default = $app->getParams()->get('captcha', $app->get('captcha'));
		}
		catch (Exception $e)
		{
			$default = null;
		}

		// Get the name of the CAPTCHA plugin the admin chose to use, falling back to Joomla's default.
		$captchaPlugin = $this->container->params->get('captcha', $default);

		if (empty($captchaPlugin) || ($captchaPlugin == 'none'))
		{
			return null;
		}

		// Return the CAPTCHA object. If the captcha plugin name is invalid (e.g. unpublished) this will return null.
		try
		{
			return Captcha::getInstance($captchaPlugin, ['namespace' => $namespace]);
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	/**
	 * Checks that a CAPTCHA was meant to be provided and was solved correctly.
	 *
	 * @throws  RuntimeException
	 */
	private function assertCaptchaSolved(): void
	{
		// Should I use a CAPTCHA at all? This is a setting defined per Model instance
		$useCaptcha = (bool) $this->getState('useCaptcha', false);

		if (!$useCaptcha)
		{
			return;
		}

		// Am I only using the CAPTCHA for certain users?
		$useCaptchaFor = $this->container->params->get('captcha_for', 'guests');
		$useCaptchaFor = in_array($useCaptchaFor, ['guests', 'all', 'nonmanager']) ? $useCaptchaFor : 'guests';

		if (($useCaptchaFor === 'guests') && ($this->getUser()->guest !== 1))
		{
			return;
		}

		if (($useCaptchaFor === 'nonmanager') && $this->getUser()->authorise('core.manage', 'com_engage'))
		{
			return;
		}

		/**
		 * Make sure I can actually get a Joomla CAPTCHA object.
		 *
		 * Note that the administrator may have chose no CAPTCHA in our component configuration or Joomla's Global
		 * Configuration.
		 */
		$captcha = $this->getCaptcha();

		if (is_null($captcha))
		{
			return;
		}

		// Finally, check the CAPTCHA solution.
		$captchaValue = $this->getState('captcha', '');

		$this->assert($captcha->checkAnswer($captchaValue), 'COM_ENGAGE_COMMENTS_ERR_CAPTCHA');
	}

}
