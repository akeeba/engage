<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
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
<section class="akengage-outer-container" aria-label="<?= Text::_('COM_ENGAGE_COMMENTS_SECTION_HEADER') ?>">
	<h3 class="akengage-title">
		<?= Text::plural($this->headerKey, $this->getItemCount(), $this->title) ?>
	</h3>

	<?php if ($this->getItemCount()): ?>
		<div class="akengage-list-container">
			<?php echo $this->loadAnyTemplate('any:com_engage/Comments/amp_list') ?>
		</div>

		<?php if ($this->pagination->pagesTotal > 1): ?>
		<div class="akengage-pagination">
			<div class="akengage-pagination-pages pagination" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
				<?= $this->pagination->getPagesLinks() ?>
			</div>
		</div>
		<?php endif; ?>
	<?php endif; ?>

	<p class="akengage-visithtml">
		<a href="<?= $this->getNonAmpURL() ?>">
			<?= Text::_('COM_ENGAGE_COMMENTS_LBL_VISIT_HTML_TO_COMMENT') ?>
		</a>
	</p>
</section>