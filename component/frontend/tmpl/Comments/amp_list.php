<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the threaded display of comments.
 *
 * Called from default.php
 */

use Akeeba\Engage\Admin\Helper\Format;
use FOF40\Date\Date;
use Joomla\CMS\Language\Text;

/**
 * @var \Akeeba\Engage\Site\View\Comments\Html $this
 * @var \FOF40\Model\DataModel\Collection      $items
 * @var \Akeeba\Engage\Site\Model\Comments     $comment
 */

$previousLevel = 0;
$openListItem  = 0;
$parentIds     = [0 => 0];
$parentNames   = [0 => ''];

foreach ($this->getItems() as $comment):
$parentIds[$comment->depth] = $comment->getId();
$parentNames[$comment->depth] = $comment->getUser()->name;
// Deeper level comment. Indent with <ul> tags
if ($comment->depth > $previousLevel):
	?>
	<?php for ($level = $previousLevel + 1; $level <= $comment->depth; $level++): ?>
	<ul class="akengage-comment-list akengage-comment-list--level<?= $level ?>">
<?php endfor; ?>
<?php // Shallower level comment. Outdent with </ul> tags
elseif ($comment->depth < $previousLevel): ?>
	<?php if ($openListItem): $openListItem--; ?>
		</li>
	<?php endif; ?>
	<?php for ($level = $previousLevel - 1; $level >= $comment->depth; $level--): ?>
		</ul>
		<?php if ($openListItem): $openListItem--; ?>
			</li>
		<?php endif; ?>
	<?php endfor; ?>
<?php // Same level comment. Close the <li> tag.
else: ?>
	<?php $openListItem--; ?>
	</li>
<?php endif; ?>

<?php
$previousLevel = $comment->depth;
$user          = $comment->getUser();
$avatar        = $comment->getAvatarURL(32);
$profile       = $comment->getProfileURL();
$commentDate   = (new Date($comment->created_on))->setTimezone($this->userTimezone);
$ipLookupURL  = $this->getIPLookupURL($comment->ip);
$openListItem++;
$this->ensureHasParentInfo($comment, $parentIds, $parentNames);
?>
<li class="akengage-comment-item">
	<article
		class="akengage-comment--<?= ($comment->enabled == 1) ? 'published' : (($comment->enabled == -3) ? 'spam' : 'unpublished') ?>"
		id="akengage-comment-<?= $comment->getId() ?>" itemscope itemtype="http://schema.org/Comment">

		<footer class="akengage-comment-properties">
			<h4 class="akengange-commenter-name" itemprop="author" itemscope itemtype="http://schema.org/Person">
				<?php if (!empty($avatar)): ?>
					<link itemprop="image" href="<?= $avatar ?>">
					<?php if (!empty($profile)): ?>
					<link itemprop="url" href="<?= $profile ?>">
					<?php endif; ?>
				<?php endif; ?>
				<span itemprop="name"><?= $this->escape($user->name) ?></span>
				<?php if ($user->authorise('core.manage', $comment->asset_id)): ?>
					<span aria-hidden="true">⭐</span>
				<?php elseif (!$user->guest): ?>
					<span aria-hidden="true">👤</span>
				<?php endif; ?>
			</h4>
			<p class="akengage-comment-permalink" itemprop="dateCreated" content="<?= $commentDate->toISO8601(false) ?>">
				<?= $commentDate->format(Text::_('DATE_FORMAT_LC2'), true) ?>
			</p>
		</footer>

		<div class="akengage-comment-body" itemprop="text">
			<?= Format::processCommentTextForDisplay($comment->body) ?>
		</div>
	</article>


	<?php endforeach; ?>

	<?php if ($openListItem): ?>
	<?php $openListItem--; ?>
</li>
<?php endif; ?>
<?php for ($level = $previousLevel; $level >= 1; $level--): ?>
	</ul>
	<?php if ($openListItem): ?>
		<?php $openListItem--; ?>
		</li>
	<?php endif; ?>
<?php endfor; ?>
