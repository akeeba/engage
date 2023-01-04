<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\View\Emailtemplates;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
	public function display($tpl = null)
	{
		ToolbarHelper::title(sprintf(Text::_('COM_ENGAGE_TITLE_EMAILTEMPLATES')), 'icon-engage');
		ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_engage&view=comments', false));

		parent::display($tpl);
	}

}