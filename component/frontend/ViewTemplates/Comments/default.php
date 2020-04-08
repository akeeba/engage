<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for comments display
 *
 * This is the main view template used when comments are being displayed e.g. at the end of an article.
 *
 * This provides the outer HTML structure of the comments.
 *
 * It loads the following view templates:
 * - default_list.php  The threaded list of comments
 * - default_login.php  Login form for guest users
 * - default_form.php  Comment / reply submission form
 */

use Joomla\CMS\Language\Text;

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

?>
<section class="akengage-outer-container">
	<h3 class="akengage-title">
		<?= Text::plural($this->headerKey, $this->getItemCount(), $this->title) ?>
	</h3>

	<?= $this->container->template->loadPosition('engage-before-comments') ?>

	<?php if ($this->getItemCount()): ?>
		<div class="akengage-list-container">
			<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_list') ?>
		</div>

		<?= $this->container->template->loadPosition('engage-after-comments') ?>

		<div class="akengage-pagination">
			<div class="akengage-pagination-pages">
				<?= $this->getPagination()->getListFooter() ?>
			</div>
		</div>
	<?php endif; ?>

	<?php if (!$this->areCommentsClosed && $this->user->guest): ?>
		<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_login') ?>
	<?php endif; ?>

	<?php if ($this->perms['create'] && !$this->areCommentsClosed): ?>
		<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_form') ?>
	<?php endif; ?>
</section>