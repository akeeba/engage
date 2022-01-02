<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Controller;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Controller\Mixin\ControllerEvents;
use Joomla\CMS\MVC\Controller\FormController;

class CommentController extends FormController
{
	use ControllerEvents;

	/** @inheritdoc */
	protected $text_prefix = 'COM_ENGAGE_COMMENT';

}