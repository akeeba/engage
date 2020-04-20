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

$darkMode       = $this->container->params->get('dark_mode_backend', -1);
$paginationData = $this->getPagination()->getData();
?>
<section class="akengage-outer-container<?= ($darkMode == 1) ? '--dark' : '' ?>">
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
			<div class="akengage-pagination-pages" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
				<?php if (!empty($paginationData->start->link)): ?>
					<a itemprop="hasPart" rel="start" href="<?= $this->rebasePageLink($paginationData->start->link) ?>">
						<span title="<?= $paginationData->start->text ?>">⏮️</span>
					</a>
				<?php endif; ?>
				<?php if (!empty($paginationData->previous->link)): ?>
					<a itemprop="hasPart" rel="prev" href="<?= $this->rebasePageLink($paginationData->previous->link) ?>">
						<span title="<?= $paginationData->previous->text ?>">⏪</span>
					</a>
				<?php endif; ?>

				<?php foreach($paginationData->pages as $page => $def): ?>
					<?php if (!empty($def->link)): ?>
						<a itemprop="hasPart" rel="bookmark" href="<?= $this->rebasePageLink($def->link) ?>" class="<?= $def->active ? 'active' : '' ?>"
						   title="<?= Text::sprintf('COM_ENGAGE_COMMENTS_LBL_PAGE', $def->text) ?>">
							<?= $def->text ?>
						</a>
					<?php else: ?>
						<span class="active">
							<?= $def->text ?>
						</span>
					<?php endif; ?>
				<?php endforeach; ?>

				<?php if (!empty($paginationData->next->link)): ?>
					<a itemprop="hasPart" rel="next" href="<?= $this->rebasePageLink($paginationData->next->link) ?>">
						<span title="<?= $paginationData->next->text ?>">⏩</span>
					</a>
				<?php endif; ?>
				<?php if (!empty($paginationData->end->link)): ?>
					<a itemprop="hasPart" rel="bookmark" href="<?= $this->rebasePageLink($paginationData->end->link) ?>">
						<span title="<?= $paginationData->end->text ?>">⏭️</span>
					</a>
				<?php endif; ?>
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