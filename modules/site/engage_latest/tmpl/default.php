<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

/**
 * @var   bool       $hasEngage          Is Akeeba Engage installed and activated?
 * @var   bool       $show_title         Show the article title?
 * @var   bool       $link_title         Link the article title?
 * @var   bool       $show_count         Show the count of article comments?
 * @var   bool       $excerpt            Only show an excerpt of the comment?
 * @var   stdClass[] $comments           Latest comments list.
 * @var   int        $excerpt_words      Maximum number of words in the excerpt
 * @var   int        $excerpt_characters Maximum number of characters in the excerpt
 */

use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;

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
	<?php foreach ($comments as $comment): ?>
		<?php
		$meta        = Meta::getAssetAccessMeta($comment->asset_id);
		$commentUri  = Uri::getInstance($meta['public_url']);
		$commentsUri = Uri::getInstance($meta['public_url']);

		$commentTable->load($comment->id);

		$commentUri->setFragment('akengage-comment-' . $comment->id);
		$commentUri->setVar('akengage_cid', $comment->id);

		$commentsUri->setFragment('akengage-comments-section');
		?>
		<li class="engage-latest-list-item list-group-item d-flex flex-column mb-2">
			<?php if ($show_title): ?>
				<div class="d-flex justify-content-between align-items-start">
					<div class="h5">
						<?php if ($link_title): ?>
							<a href="<?= $commentsUri->toString() ?>">
								<?= htmlspecialchars($comment->article_title) ?>
							</a>
						<?php else: ?>
							<?= htmlspecialchars($comment->article_title) ?>
						<?php endif; ?>
					</div>
					<?php if ($show_count): ?>
					<span class="badge bg-primary rounded-pill"><?= Meta::getNumCommentsForAsset($comment->asset_id) ?></span>
					<?php endif ?>
				</div>
			<?php endif; ?>
			<div class="text-muted my-1">
				<?= Text::sprintf(
					'MOD_ENGAGE_LATEST_LBL_COMMENTED_ON',
					$comment->user_name,
					$commentUri->toString(),
					HTMLHelper::_('engage.date', new Date($comment->created))
				) ?>
			</div>
			<div>
				<?php if ($excerpt): ?>
					<?= HTMLHelper::_('engage.textExcerpt', $comment->body, $excerpt_words, $excerpt_characters, '[…]') ?>
				<?php else: ?>
					<?= $comment->body ?>
				<?php endif; ?>
			</div>
		</li>
	<?php endforeach; ?>
</ul>
