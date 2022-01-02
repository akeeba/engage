<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Component\Engage\Administrator\View\Emailtemplates\HtmlView $this */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

$token = Factory::getApplication()->getFormToken();
?>

<div class="card mb-3">
	<h3 class="card-header bg-info text-white">
		<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_LBL_WHERE_HEAD') ?>
	</h3>
	<div class="card-body">
		<p>
			<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_LBL_WHERE_TEXT') ?>
		</p>
		<p>
			<a href="<?= Route::_('index.php?option=com_mails&filter[extension]=com_engage') ?>"
			   class="btn btn-primary">
				<span class="fa fa-envelope"></span>
				<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_BTN_MAILTEMPLATES') ?>
			</a>
		</p>
	</div>
</div>

<div class="card">
	<h3 class="card-header bg-primary text-white">
		<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_LBL_MANAGE_HEAD') ?>
	</h3>
	<div class="card-body">
		<div class="row cols-2">
			<div class="col d-flex flex-column align-items-center">
				<div class="mb-2">
					<a href="<?= Route::_('index.php?option=com_engage&view=Emailtemplates&task=updateEmails&' . $token . '=1') ?>"
					   class="btn btn-success d-flex flex-column" style="min-width: 10em"
					>
						<span class="fa fa-check fs-1 p-2"></span>
						<span>
							<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_BTN_UPDATE') ?>
						</span>
					</a>
				</div>
				<div class="text-muted">
					<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_LBL_UPDATE') ?>
				</div>
			</div>
			<div class="col d-flex flex-column align-items-center">
				<div class="mb-2">
					<a href="<?= Route::_('index.php?option=com_engage&view=Emailtemplates&task=resetEmails&' . $token . '=1') ?>"
					   class="btn btn-danger d-flex flex-column" style="min-width: 10em"
					>
						<span class="fa fa-redo-alt fs-1 p-2"></span>
						<span>
							<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_BTN_RESET') ?>
						</span>
					</a>
				</div>
				<div class="text-muted">
					<?= Text::_('COM_ENGAGE_EMAILTEMPLATES_LBL_RESET') ?>
				</div>
			</div>
		</div>
	</div>
</div>