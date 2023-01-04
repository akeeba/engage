<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Mixin;

defined('_JEXEC') or die;

/**
 * Allows controllers to return information about a redirection which has been set in them.
 */
trait ControllerRedirectionTrait
{
	/**
	 * Get the redirection URL
	 *
	 * @return  string|null
	 *
	 * @since   3.0.0
	 */
	public function getRedirection(): ?string
	{
		return $this->redirect;
	}

	/**
	 * Get the redirection message
	 *
	 * @return  string|null
	 *
	 * @since   3.0.0
	 */
	public function getRedirectionMessage(): ?string
	{
		return $this->message;
	}

	/**
	 * Get the redirection message type
	 *
	 * @return  string|null
	 *
	 * @since   3.0.0
	 */
	public function getRedirectionMessageType(): ?string
	{
		return $this->messageType;
	}
}