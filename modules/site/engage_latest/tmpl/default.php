<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/**
 * @var   bool        $hasEngage  Is Akeeba Engage installed and activated
 * @var   stdClass[]  $comments   Latest comments list
 */

use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var CommentTable $commentTable */
$commentTable = Factory::getApplication()
                       ->bootComponent('com_engage')
                       ->getMVCFactory()
                       ->createTable('Comment', 'Administrator');
?>

<?php if (!$hasEngage): ?>
<div class="alert alert-danger">
	<h4 class="alert-heading">
		<?= Text::_('MOD_ENGAGE_LATEST_ERR_NOCOMPONENT_HEAD') ?>
	</h4>
	<p>
		<?= Text::_('MOD_ENGAGE_LATEST_ERR_NOCOMPONENT_BODY') ?>
	</p>
</div>
<?php
	return;
	elseif (empty($comments)): ?>
<div class="alert alert-info">
	<h4 class="alert-heading">
		<?= Text::_('MOD_ENGAGE_LATEST_ERR_NOCOMMENTS_HEAD') ?>
	</h4>
	<p>
		<?= Text::_('MOD_ENGAGE_LATEST_ERR_NOCOMMENTS_BODY') ?>
	</p>
</div>
<?php
	return;
	endif;
?>

<ul class="engage-latest-list list-group list-group-flush">
<?php foreach($comments as $comment): ?>
	<?php
		$meta = Meta::getAssetAccessMeta($comment->asset_id);
		$uri  = Uri::getInstance($meta['public_url']);

		$commentTable->load($comment->id);

		$uri->setFragment('akengage-comment-' . $comment->id);
		$uri->setVar('akengage_cid', $comment->id);
	?>
	<li class="engage-latest-list-item list-group-item d-flex flex-column mb-2">
		<div class="d-flex justify-content-between align-items-start">
			<div class="h5">
				<?= htmlspecialchars($comment->article_title) ?>
			</div>
			<span class="badge bg-primary rounded-pill"><?= \Akeeba\Component\Engage\Site\Helper\Meta::getNumCommentsForAsset($comment->asset_id) ?></span>
		</div>
		<div class="text-muted my-1">
			<?= Text::sprintf(
				'MOD_ENGAGE_LATEST_LBL_COMMENTED_ON',
				$comment->user_name,
				$uri->toString(),
				(new \Joomla\CMS\Date\Date($comment->created_on))->format(Text::_('DATE_FORMAT_LC2'))
			) ?>
		</div>
		<div>
			<?= htmlspecialchars($comment->body) ?>
		</div>
	</li>
<?php endforeach; ?>
</ul>
