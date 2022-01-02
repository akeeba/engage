<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
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

$darkMode       = $this->container->params->get('dark_mode_backend', -1);
$paginationData = $this->getPagination()->getData();
?>
<section id="akengage-comments-section" class="akengage-outer-container<?= ($darkMode == 1) ? '--dark' : '' ?>" aria-label="<?= Text::_('COM_ENGAGE_COMMENTS_SECTION_HEADER') ?>">
	<h3 class="akengage-title">
		<?= Text::plural($this->headerKey, $this->getItemCount(), $this->title) ?>
	</h3>

	<?= $this->container->template->loadPosition('engage-before-comments') ?>

	<?php if ($this->getItemCount()): ?>
		<div class="akengage-list-container">
			<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_list') ?>
		</div>

		<?= $this->container->template->loadPosition('engage-after-comments') ?>

		<?php if ($this->pagination->pagesTotal > 1): ?>
		<div class="akengage-pagination">
			<div class="akengage-pagination-pages pagination" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
				<?= $this->pagination->getPagesLinks() ?>
			</div>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (!$this->areCommentsClosed && $this->user->guest && !$this->perms['create']): ?>
		<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_login') ?>
	<?php endif; ?>

	<?php if ($this->perms['create'] && !$this->areCommentsClosed): ?>
		<?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_form') ?>
	<?php endif; ?>
</section>