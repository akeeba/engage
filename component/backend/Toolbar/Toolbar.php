<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Toolbar;

defined('_JEXEC') or die;

use FOF30\Toolbar\Toolbar as BaseToolbar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar as JToolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;

class Toolbar extends BaseToolbar
{
	public function onCommentsBrowse()
	{
		$this->renderSubmenu();

		// Set toolbar title
		ToolbarHelper::title(Text::_('COM_ENGAGE') . ' <small>' . Text::_('COM_ENGAGE_TITLE_COMMENTS') . '</small>', 'engage');

		$bar = JToolbar::getInstance();

		if ($this->perms->edit)
		{
			ToolbarHelper::editList();
		}

		if ($this->perms->editstate)
		{
			ToolbarHelper::publishList();
			ToolbarHelper::unpublishList();
			ToolbarHelper::custom('possiblespam', 'akengage-possiblespam', '', 'COM_ENGAGE_COMMENTS_TOOLBAR_POSSIBLESPAM');
			$bar->appendButton('Confirm', 'COM_ENGAGE_COMMENTS_CONFIRM_REPORTHAM', 'akengage-reportham', 'COM_ENGAGE_COMMENTS_TOOLBAR_REPORTHAM', 'reportham', true);

		}

		if ($this->perms->delete)
		{
			$bar->appendButton('Confirm', 'COM_ENGAGE_COMMENTS_CONFIRM_REPORTSPAM', 'akengage-reportspam', 'COM_ENGAGE_COMMENTS_TOOLBAR_REPORTSPAM', 'reportspam', true);
			ToolbarHelper::deleteList('COM_ENGAGE_COMMENTS_CONFIRM_DELETE');
		}

		ToolbarHelper::preferences('com_engage');
		ToolbarHelper::help('foobar', false, 'https://github.com/akeeba/engage/wiki');
	}

	public function onCommentsEdit()
	{
		$this->renderSubmenu();

		// Set toolbar title
		ToolbarHelper::title(Text::_('COM_ENGAGE') . ' <small>' . Text::_('COM_ENGAGE_TITLE_COMMENTS_EDIT') . '</small>', 'engage');

		if ($this->perms->edit || $this->perms->editown)
		{
			// Show the apply button only if I can edit the record, otherwise I'll return to the edit form and get a
			// 403 error since I can't do that
			ToolbarHelper::apply();
		}

		ToolbarHelper::save();

		ToolbarHelper::link('index.php?option=com_engage&view=Comments', 'JCANCEL', 'cancel');
	}

	public function onEmailtemplatesBrowse()
	{
		parent::onBrowse();

		// Set toolbar title
		ToolbarHelper::title(Text::_('COM_ENGAGE') . ' <small>' . Text::_('COM_ENGAGE_TITLE_EMAILTEMPLATES') . '</small>', 'engage');
	}

	public function onEmailtemplatesEdit()
	{
		parent::onEdit();

		// Set toolbar title
		ToolbarHelper::title(Text::_('COM_ENGAGE') . ' <small>' . Text::_('COM_ENGAGE_TITLE_EMAILTEMPLATES_EDIT') . '</small>', 'engage');
	}
}
