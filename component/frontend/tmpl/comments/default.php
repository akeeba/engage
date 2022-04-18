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

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\Engage\Site\View\Comments\HtmlView $this */

$cParams = ComponentHelper::getParams('com_engage');
?>
<section id="akengage-comments-section" class="akengage-outer-container"
		aria-label="<?= Text::_('COM_ENGAGE_COMMENTS_SECTION_HEADER') ?>">

	<h3 class="akengage-title h4 border-bottom mb-2">
		<?= Text::plural($this->headerKey, $this->pagination->total, $this->title) ?>
	</h3>

	<?= $this->loadPosition('engage-before-comments') ?>

	<?php if ($this->pagination->total): ?>
		<div class="akengage-list-container">
			<?= $this->loadTemplate('list') ?>
		</div>

		<?= $this->loadPosition('engage-after-comments') ?>

		<?php if ($this->pagination->pagesTotal > 1): ?>
		<div class="akengage-pagination">
			<div class="akengage-pagination-pages pagination" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
				<?= $this->pagination->getPagesLinks() ?>
			</div>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<?php if (!$this->areCommentsClosed && $this->user->guest && !$this->perms['create']): ?>
		<?= $this->loadTemplate('login') ?>
	<?php endif; ?>

	<?php if ($this->perms['create'] && !$this->areCommentsClosed): ?>
		<?= $this->loadTemplate('form') ?>
	<?php endif; ?>

	<?php if ($this->perms['create'] && $this->areCommentsClosed): ?>
		<div class="alert alert-info">
			<h3 class="alert-heading">
				<?= Text::_('COM_ENGAGE_COMMENTS_LBL_CLOSED_HEADER') ?>
			</h3>
			<p>
				<?php if ($this->areCommentsClosedAfterTime): ?>
					<?= Text::_('COM_ENGAGE_COMMENTS_LBL_CLOSED_AFTERTIME') ?>
				<?php else: ?>
					<?= Text::_('COM_ENGAGE_COMMENTS_LBL_CLOSED_BODY') ?>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>
</section>