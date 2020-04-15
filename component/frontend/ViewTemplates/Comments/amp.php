<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for comments display under AMP
 *
 * This is the main view template used when comments are being displayed e.g. at the end of an article when viewing the
 * page under AMP using wbAMP.
 *
 * This provides the outer HTML structure of the comments.
 *
 * It loads the following view templates:
 * - amp_list.php  The threaded list of comments
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

$darkMode       = $this->container->params->get('dark_mode_backend', -1);
$paginationData = $this->getPagination()->getData();
?>
<div class="akengage-outer-container">
	<h3 class="akengage-title">
		<?= Text::plural($this->headerKey, $this->getItemCount(), $this->title) ?>
	</h3>

	<?php if ($this->getItemCount()): ?>
		<div class="akengage-list-container">
			<?php echo $this->loadAnyTemplate('any:com_engage/Comments/amp_list') ?>
		</div>

		<div class="akengage-pagination">
			<div class="akengage-pagination-pages">
				<?php if (!empty($paginationData->start->link)): ?>
					<a href="<?= $this->rebasePageLink($paginationData->start->link) ?>">
						<span title="<?= $paginationData->start->text ?>">⏮️</span>
					</a>
				<?php endif; ?>
				<?php if (!empty($paginationData->previous->link)): ?>
					<a href="<?= $this->rebasePageLink($paginationData->previous->link) ?>">
						<span title="<?= $paginationData->previous->text ?>">⏪</span>
					</a>
				<?php endif; ?>

				<?php foreach($paginationData->pages as $page => $def): ?>
					<?php if (!empty($def->link)): ?>
						<a href="<?= $this->rebasePageLink($def->link) ?>" class="<?= $def->active ? 'active' : '' ?>"
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
					<a href="<?= $this->rebasePageLink($paginationData->next->link) ?>">
						<span title="<?= $paginationData->next->text ?>">⏩</span>
					</a>
				<?php endif; ?>
				<?php if (!empty($paginationData->end->link)): ?>
					<a href="<?= $this->rebasePageLink($paginationData->end->link) ?>">
						<span title="<?= $paginationData->end->text ?>">⏭️</span>
					</a>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<p class="akengage-visithtml">
		<a href="<?= $this->getNonAmpURL() ?>">
			<?= Text::_('COM_ENGAGE_COMMENTS_LBL_VISIT_HTML_TO_COMMENT') ?>
		</a>
	</p>
</div>