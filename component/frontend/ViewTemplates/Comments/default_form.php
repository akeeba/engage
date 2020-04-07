<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

$captcha = $this->getCaptchaField();

HTMLHelper::_('behavior.formvalidator');
?>
<div class="akengage-comment-form" id="akengage-comment-form">
	<h4>
		<?= Text::_('COM_ENGAGE_COMMENTS_FORM_HEADER'); ?>
	</h4>

	<?= $this->container->template->loadPosition('engage-before-reply'); ?>

	<form class="form-horizontal form-validate" action="index.php" method="post" name="akengageCommentForm"
		  id="akengageCommentForm">
		<input type="hidden" name="option" value="com_engage">
		<input type="hidden" name="view" value="Comments">
		<input type="hidden" name="task" value="submit">
		<input type="hidden" name="<?= $this->container->platform->getToken(true); ?>" value="1">
		<input type="hidden" name="asset_id" value="<?= (int) $this->assetId; ?>">
		<input type="hidden" name="parent_id" value="0">
		<input type="hidden" name="returnurl" value="<?= base64_encode(Uri::getInstance()->toString()); ?>">

		<div id="akengage-comment-inreplyto-wrapper">
			<?= Text::_('COM_ENGAGE_COMMENTS_FORM_INREPLYTO_LABEL'); ?>
			<span id="akengage-comment-inreplyto-name">Some User</span>
			<button id="akengage-comment-inreplyto-cancel"
					type="button"
			><?= Text::_('COM_ENGAGE_COMMENTS_FORM_CANCELREPLY'); ?></button>
		</div>

		<?php if ($this->user->guest): ?>
			<div class="control-group">
				<div class="control-label">
					<label id="akengage-comment-form-name" for="akengage-comment-form-name"
						   class="required"
					>
						<?= Text::_('COM_ENGAGE_COMMENTS_FORM_NAME_LABEL'); ?>
						<span class="star">&nbsp;*</span>
					</label>
				</div>
				<div class="controls">
					<input type="text" name="name" id="akengage-comment-form-name"
						   value="<?= $this->escape($this->storedName); ?>" class="inputbox required"
						   required="required"
						   aria-required="true" aria-invalid="false"
						   placeholder="<?= Text::_('COM_ENGAGE_COMMENTS_FORM_NAME_PLACEHOLDER'); ?>"
						   size="30">
				</div>
			</div>

			<div class="control-group">
				<div class="control-label">
					<label id="akengage-comment-form-email" for="akengage-comment-form-email"
						   class="required"
					>
						<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EMAIL_LABEL'); ?>
						<span class="star">&nbsp;*</span>
					</label>
				</div>
				<div class="controls">
					<input type="text" name="email" id="akengage-comment-form-email"
						   value="<?= $this->escape($this->storedEmail); ?>"
						   class="inputbox required"
						   required="required"
						   aria-required="true" aria-invalid="false"
						   placeholder="<?= Text::_('COM_ENGAGE_COMMENTS_FORM_EMAIL_PLACEHOLDER'); ?>"
						   size="30">
				</div>
			</div>
		<?php endif; ?>

		<?= Editor::getInstance($this->container->platform->getConfig()->get('editor', 'tinymce'))
			->display('comment', $this->storedComment, '100%', '400', 50, 10, false, 'akengage-comment-editor'); ?>

		<?php if (!(empty($captcha))): ?>
			<div class="akengage-comment-captcha-wrapper">
				<?= $captcha; ?>
			</div>
			<div class="akengage-comment-captcha-clear"></div>
		<?php endif; ?>

		<div class="btn-toolbar">
			<div class="btn-group">
				<button type="submit" class="btn btn-primary">
					<?= Text::_('COM_ENGAGE_COMMENTS_FORM_BTN_SUBMIT'); ?>
				</button>
			</div>
		</div>
	</form>

	<?= $this->container->template->loadPosition('engage-after-reply'); ?>
</div>