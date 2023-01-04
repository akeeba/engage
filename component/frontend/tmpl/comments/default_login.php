<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the guest users login form
 *
 * Loaded from default.php
 */

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;

/** @var \Akeeba\Component\Engage\Site\View\Comments\HtmlView $this */

$cParams         = ComponentHelper::getParams('com_engage');
$loginModule     = $cParams->get('login_module', '-1');
$moduleContent   = (empty($loginModule) || ($loginModule === '-1')) ? '' : trim($this->loadModule($loginModule));
$positionContent = trim($this->loadPosition('engage-login'));

/**
 * A reason for this to happen is that site owner wants discussion to be open to invitation-only members of the site but
 * visible by anyone. This is mostly relevant in political organizations, NGOs and local / closed community
 * organizations where a small number of people are openly discussing a public interest issue, but they don't want to
 * allow random people to detract the conversation.
 */
if (empty($moduleContent) && empty($positionContent))
{
	return;
}
?>
<footer id="akeeba-engage-login">
	<h4>
		<?= Text::_('COM_ENGAGE_COMMENTS_LOGIN_HEAD') ?>
	</h4>

	<?= $moduleContent ?>
	<?= $positionContent ?>
</footer>