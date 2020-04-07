<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Engage\Site\Helper\Meta;
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

    <?php if($this->perms['create'] && !$this->areCommentsClosed): ?>
	    <?php echo $this->loadAnyTemplate('any:com_engage/Comments/default_form') ?>
    <?php endif; ?>
</section>