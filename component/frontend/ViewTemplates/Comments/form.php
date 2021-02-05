<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the editing comments. This is used ONLY on Joomla 3.
 *
 * This View Template allows a user to edit a comment. This happens in two cases:
 * - A user with the Edit Own privilege edits their own comment
 * - A user with the Edit privilege edits their own OR someone else's comment
 *
 * When editing someone else's comment you can also edit their name and email address, as long as the comment was filed
 * by a guest user.
 */

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/**
 * @var \Akeeba\Engage\Site\View\Comments\Html $this
 * @var \Akeeba\Engage\Site\Model\Comments     $item
 */

$item    = $this->item;
$user    = $this->container->platform->getUser();
$editOwn = $user->id == $item->created_by;

HTMLHelper::_('behavior.formvalidator');
?>
<div class="akengage-comment-edit-form" id="akengage-comment-edit-form">
	<h3>
		<?= Text::_('COM_ENGAGE_COMMENTS_EDIT_HEADER'); ?>
	</h3>

	<form class="form-horizontal form-validate"
		  action="<?= Route::_('index.php?option=com_engage&view=Comments', true, Route::TLS_IGNORE, true) ?>"
		  method="post"
		  name="akengageCommentEditForm"
		  id="akengageCommentEditForm">

		<input type="hidden" name="task" value="save">
		<input type="hidden" name="<?= $this->container->platform->getToken(true); ?>" value="1">
		<input type="hidden" name="id" value="<?= (int) $item->getId(); ?>">
		<input type="hidden" name="returnurl" value="<?= base64_encode($this->returnURL); ?>">

		<?php if (!$editOwn): ?>
			<div class="control-group">
				<div class="control-label">
					<label id="akengage-comment-edit-form-name" for="akengage-comment-edit-form-name"
						   class="required"
					>
						<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_NAME_LABEL'); ?>
						<span class="star">&nbsp;*</span>
					</label>
				</div>
				<div class="controls">
					<input type="text"
						   name="name"
						   id="akengage-comment-edit-form-name"
						   value="<?= $this->escape($item->name); ?>"
						   class="inputbox required"
						   required="required"
						   aria-required="true" aria-invalid="false"
						   size="30">
				</div>
			</div>

			<div class="control-group">
				<div class="control-label">
					<label id="akengage-comment-edit-form-email" for="akengage-comment-edit-form-email"
						   class="required"
					>
						<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_EMAIL_LABEL'); ?>
						<span class="star">&nbsp;*</span>
					</label>
				</div>
				<div class="controls">
					<input type="email"
						   name="email"
						   id="akengage-comment-edit-form-email"
						   value="<?= $this->escape($item->email); ?>"
						   class="inputbox required"
						   required="required"
						   aria-required="true" aria-invalid="false"
						   size="30">
				</div>
			</div>
		<?php endif; ?>

		<?php if ($user->authorise('core.edit', 'com_engage')): ?>
			<div class="control-group">
				<div class="control-label">
					<label id="akengage-comment-edit-form-ip" for="akengage-comment-edit-form-ip"
						   class="required"
					>
						<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_IP_LABEL'); ?>
						<span class="star">&nbsp;*</span>
					</label>
				</div>
				<div class="controls">
					<input type="text"
						   name="ip"
						   id="akengage-comment-edit-form-ip"
						   value="<?= $this->escape($item->ip); ?>"
						   class="inputbox required"
						   required="required"
						   aria-required="true" aria-invalid="false"
						   size="30">
				</div>
			</div>
		<?php endif; ?>

		<?= Editor::getInstance($this->container->platform->getConfig()->get('editor', 'tinymce'))
			->display('body', $item->body, '100%', '400', 50, 10, false, 'akengage-comment-edit-form-editor'); ?>

		<div class="btn-toolbar">
			<button type="submit" class="btn btn-primary btn-large">
				<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_BTN_SUBMIT'); ?>
			</button>

			<a href="<?= $this->returnURL ?>" class="btn btn-danger">
				<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_BTN_CANCEL'); ?>
			</a>
		</div>
	</form>
</div>