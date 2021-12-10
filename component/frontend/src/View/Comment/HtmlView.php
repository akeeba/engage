<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\View\Comment;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\View\Mixin\ModuleRenderAware;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

class HtmlView extends BaseHtmlView
{
	use ModuleRenderAware;

	/**
	 * The comment editing form.
	 *
	 * @var   Form
	 * @since 3.0.0
	 */
	public $form;

	/**
	 * The URL to return to after saving the comment.
	 *
	 * @var   string
	 * @since 3.0.0
	 */
	public $returnUrl;

	/**
	 * The comment we are editing.
	 *
	 * @var   CommentTable
	 * @since 3.0.0
	 */
	public $item;

	/** @inheritDoc */
	public function display($tpl = null)
	{
		$this->setLayout('edit');
		$this->addTemplatePath(JPATH_SITE . '/components/com_engage/tmpl/comments');

		$this->form = $this->getForm();
		$this->item = $this->get('Item');

		parent::display($tpl);
	}

}