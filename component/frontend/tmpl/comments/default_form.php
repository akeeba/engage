<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the submitting comments.
 *
 * This is called by default.php
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Akeeba\Component\Engage\Site\View\Comments\HtmlView $this */

$cParams = ComponentHelper::getParams('com_engage');
$badUx   = ($cParams->get('comments_reply_bad_ux', 0) == 1) && empty($this->form->getValue('body'));

HTMLHelper::_('behavior.formvalidator');
?>
<?php if ($badUx): ?>
	<div class="akengage-comment-hider" id="akengage-comment-hider">
		<button type="button"
				id="akengage-comment-hider-button"
				class="btn btn-primary">
			<?= Text::_('COM_ENGAGE_COMMENTS_FORM_HEADER'); ?>
		</button>
	</div>
<?php endif; ?>

<form action="<?= Route::_('index.php?option=com_engage&task=comment.save') ?>"
	method="post" name="akengage-comment-form" id="akengageCommentForm"
	class="form-validate <?= $badUx ? 'd-none' : ''; ?>"
	style="<?= $badUx ? 'display: none;' : ''; ?>"
	aria-label="<?= Text::_('COM_ENGAGE_COMMENTS_FORM_HEADER', true) ?>"
>
	<input type="hidden" name="returnurl" value="<?= base64_encode(Uri::getInstance()->toString(['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment'])) ?>">
	<input type="hidden" name="view" value="">
	<input type="hidden" name="id" value="">
	<?= HTMLHelper::_('form.token') ?>

	<div class="mt-3 pt-2 mb-2 border-top border-2 border-dark">
		<h3 class="h1 my-3">
			<?= Text::_('COM_ENGAGE_COMMENTS_FORM_HEADER') ?>
		</h3>

		<?= $this->loadPosition('engage-before-reply'); ?>

		<div id="akengage-comment-inreplyto-wrapper" class="alert alert-info d-none">
			<div class="d-flex flex-wrap">
				<div class="flex-grow-1">
					<?= Text::_('COM_ENGAGE_COMMENTS_FORM_INREPLYTO_LABEL'); ?>
					<span id="akengage-comment-inreplyto-name" class="text-secondary fw-bold">Some User</span>
				</div>

				<button id="akengage-comment-inreplyto-cancel"
						type="button"
						class="ms-2 btn btn-sm btn-outline-danger"
				><?= Text::_('COM_ENGAGE_COMMENTS_FORM_CANCELREPLY'); ?></button>
			</div>
		</div>

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
						class="btn btn-lg btn-primary w-100">
					<span class="fa fa-comment-dots" aria-hidden="true"></span>
					<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EDIT_BTN_SUBMIT') ?>
				</button>
			</div>
		</div>

		<?= $this->loadPosition('engage-after-reply'); ?>
	</div>
</form>