<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/** @var \Akeeba\Component\Engage\Administrator\View\Comment\HtmlView $this */

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');

?>
<form action="<?php echo Route::_('index.php?option=com_engage&view=comment&layout=edit&id=' . $this->item->id); ?>"
      aria-label="<?= Text::_('COM_ENGAGE_TITLE_COMMENTS_EDIT', true) ?>"
      class="form-validate" id="autoreply-form" method="post" name="adminForm">

	<?php foreach ($this->form->getFieldsets() as $fieldset => $info): ?>
		<div class="card mb-3">
			<h3 class="card-header bg-info text-white">
				<?= Text::_($info->label) ?>
			</h3>
			<div class="card-body">
				<?php foreach ($this->form->getFieldset($fieldset) as $field): ?>
					<?= $field->renderField(); ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach ?>

	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
