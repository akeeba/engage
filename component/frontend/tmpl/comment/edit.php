<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the editing comments.
 *
 * This View Template allows a user to edit a comment. This happens in two cases:
 * - A user with the Edit Own privilege edits their own comment
 * - A user with the Edit privilege edits their own OR someone else's comment
 *
 * When editing someone else's comment you can also edit their name and email address, as long as the comment was filed
 * by a guest user.
 */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Akeeba\Component\Engage\Site\View\Comment\HtmlView $this */

HTMLHelper::_('behavior.formvalidator');
?>

<form action="<?= Route::_('index.php?option=com_engage&task=comment.save') ?>"
		method="post" name="akengage-comment-edit-form" id="akengage-comment-edit-form"
		class="akengage-comment-edit-form form-validate"
		aria-label="<?= Text::_('COM_ENGAGE_COMMENTS_EDIT_HEADER', true) ?>"
>
	<input type="hidden" name="returnurl" value="<?= base64_encode($this->returnUrl) ?>">
	<input type="hidden" name="view" value="">
	<input type="hidden" name="id" value="<?= $this->item->id ?>">
	<?= HTMLHelper::_('form.token') ?>

	<h3 class="h4 my-3">
		<?= Text::_('COM_ENGAGE_COMMENTS_EDIT_HEADER') ?>
	</h3>

	<?php foreach (array_keys($this->form->getFieldsets()) as $fieldSet)
	{
		echo $this->form->renderFieldset($fieldSet);
	} ?>

	<div class="control-group">
		<div class="control-label d-none d-md-block">
			&nbsp;
		</div>
		<div class="controls">
			<button type="submit"
					class="btn btn-lg btn-primary">
				<span class="fa fa-comment-dots" aria-hidden="true"></span>
				<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_BTN_SUBMIT') ?>
			</button>

			<a href="<?= $this->returnUrl ?>"
					class="btn btn-outline-danger">
				<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_BTN_CANCEL'); ?>
			</a>
		</div>
	</div>
</form>