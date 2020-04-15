<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the threaded display of comments.
 *
 * Called from default.php
 */

use FOF30\Date\Date;
use Joomla\CMS\Language\Text;

/**
 * @var \Akeeba\Engage\Site\View\Comments\Html $this
 * @var \FOF30\Model\DataModel\Collection      $items
 * @var \Akeeba\Engage\Site\Model\Comments     $comment
 */

$previousLevel = 0;
$openListItem  = 0;
$parentIds     = [0 => 0];
$parentNames   = [0 => ''];

foreach ($this->getItems() as $comment):
$parentIds[$comment->getLevel()] = $comment->getId();
$parentNames[$comment->getLevel()] = $comment->getUser()->name;
// Deeper level comment. Indent with <ul> tags
if ($comment->getLevel() > $previousLevel):
	?>
	<?php for ($level = $previousLevel + 1; $level <= $comment->getLevel(); $level++): ?>
	<ul class="akengage-comment-list akengage-comment-list--level<?= $level ?>">
<?php endfor; ?>
<?php // Shallower level comment. Outdent with </ul> tags
elseif ($comment->getLevel() < $previousLevel): ?>
	<?php if ($openListItem): $openListItem--; ?>
		</li>
	<?php endif; ?>
	<?php for ($level = $previousLevel - 1; $level >= $comment->getLevel(); $level--): ?>
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
$previousLevel = $comment->getLevel();
$user          = $comment->getUser();
$avatar        = $comment->getAvatarURL(32);
$profile       = $comment->getProfileURL();
$commentDate   = new Date($comment->created_on);
$ipLookupURL  = $this->getIPLookupURL($comment->ip);
$openListItem++;
$this->ensureHasParentInfo($comment, $parentIds, $parentNames);
?>
<li class="akengage-comment-item">
	<div
		class="akengage-comment--<?= ($comment->enabled == 1) ? 'published' : (($comment->enabled == -3) ? 'spam' : 'unpublished') ?>"
		id="akengage-comment-<?= $comment->getId() ?>">

		<div class="akengage-comment-properties">
			<h4 class="akengange-commenter-name">
				<?= $this->escape($user->name) ?>
				<?php if ($user->authorise('core.manage', $comment->asset_id)): ?>
					<span aria-hidden="true">⭐</span>
				<?php elseif (!$user->guest): ?>
					<span aria-hidden="true">👤</span>
				<?php endif; ?>
			</h4>
			<p class="akengage-comment-permalink">
				<?= $commentDate->format(Text::_('DATE_FORMAT_LC2'), true) ?>
			</p>
		</div>

		<div class="akengage-comment-body">
			<?= $comment->body ?>
		</div>
	</div>


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
