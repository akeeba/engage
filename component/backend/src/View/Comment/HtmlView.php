<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\View\Comment;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Mixin\ViewLoadAnyTemplateTrait;
use Akeeba\Component\Engage\Administrator\Model\CommentModel;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * HTML view class for editing a comment
 *
 * @since 3.0.0
 */
class HtmlView extends BaseHtmlView
{
	use ViewLoadAnyTemplateTrait;

	/**
	 * The Form object
	 *
	 * @var    Form
	 * @since  3.0.0
	 */
	protected $form;

	/**
	 * The active item
	 *
	 * @var    object
	 * @since  3.0.0
	 */
	protected $item;

	/**
	 * The model state
	 *
	 * @var    object
	 * @since  3.0.0
	 */
	protected $state;

	/**
	 * Execute and display a template script.
	 *
	 * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
	 *
	 * @return  void
	 *
	 * @throws  Exception
	 * @see     loadTemplate()
	 * @since   3.0/0
	 */
	public function display($tpl = null)
	{
		/** @var CommentModel $model */
		$model       = $this->getModel();
		$this->item  = $model->getItem();
		$this->form  = $model->getForm((array) $this->item);
		$this->state = $model->getState();

		// Check for errors.
		if (count($errors = $this->get('Errors')))
		{
			throw new GenericDataException(implode("\n", $errors), 500);
		}

		$this->addToolbar();

		parent::display($tpl);
	}

	/**
	 * Set up Joomla!'s toolbar.
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   5.0.0
	 */
	protected function addToolbar(): void
	{
		Factory::getApplication()->input->set('hidemainmenu', true);

		ToolbarHelper::title(
			sprintf("%s: <span class=\"fw-bold\">%s</span>", Text::_('COM_ENGAGE'), Text::_('COM_ENGAGE_TITLE_COMMENTS_EDIT')),
			'icon-engage'
		);

		ToolbarHelper::apply('comment.save');
		ToolbarHelper::save('comment.apply');

		ToolbarHelper::cancel('comment.cancel', 'JTOOLBAR_CLOSE');
	}
}