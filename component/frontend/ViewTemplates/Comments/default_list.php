<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

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
// Deeper level comment. Indent with <ol> tags
if ($comment->getLevel() > $previousLevel):
	?>
	<?php for ($level = $previousLevel + 1; $level <= $comment->getLevel(); $level++): ?>
	<ol class="akengage-comment-list akengage-comment-list--level<?= $level ?>">
<?php endfor; ?>
<?php // Shallower level comment. Outdent with </ol> tags
elseif ($comment->getLevel() < $previousLevel): ?>
	<?php if ($openListItem): $openListItem--; ?>
		</li>
	<?php endif; ?>
	<?php for ($level = $previousLevel - 1; $level >= $comment->getLevel(); $level--): ?>
		</ol>
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
$avatar        = $comment->getAvatarURL();
$profile       = $comment->getProfileURL();
$commentDate   = new Date($comment->created_on);
$ipLookupURL  = $this->getIPLookupURL($comment->ip);
$openListItem++;
$this->ensureHasParentInfo($comment, $parentIds, $parentNames);
?>
<li class="akengage-comment-item">

	<article
			class="akengage-comment--<?= ($comment->enabled == 1) ? 'published' : (($comment->enabled == -3) ? 'spam' : 'unpublished') ?>"
			id="akengage-comment-<?= $comment->getId() ?>">
		<footer class="akengage-comment-properties">
			<?php if (!empty($avatar)): ?>
				<?php if (empty($profile)): ?>
					<img src="<?= $avatar ?>" alt="<?= Text::sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name) ?>"
						 class="akengage-commenter-avatar">
				<?php else: ?>
					<a href="<?= $profile ?>" class="akengage-commenter-profile">
						<img src="<?= $avatar ?>"
							 alt="<?= Text::sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name) ?>"
							 class="akengage-commenter-avatar">
					</a>
				<?php endif; ?>
			<?php endif; ?>
			<div class="akengange-commenter-name">
				<?= $this->escape($user->name) ?>
				<?php if ($user->authorise('core.manage', $comment->asset_id)): ?>
					<span class="akengage-commenter-ismoderator icon icon-star"></span>
				<?php elseif (!$user->guest): ?>
					<span class="akengage-commenter-isuser icon icon-user"></span>
				<?php endif; ?>
				<?php if (!$user->guest): ?>
					<span class="akengage-commenter-username"><?= $this->escape($user->username) ?></span>
				<?php endif; ?>
			</div>
			<div class="akengage-comment-info">
                <span class="akengage-comment-permalink">
                    <?= $commentDate->format(Text::_('DATE_FORMAT_LC2'), true) ?>
                </span>
				<?php if ($this->perms['state']): ?>
					<span class="akengage-comment-publish_unpublish">
                    <?php if ($comment->enabled == 1): ?>
						<button class="akengage-comment-unpublish-btn" data-akengageid="<?= $comment->getId() ?>">
                            <?= Text::_('COM_ENGAGE_COMMENTS_BTN_UNPUBLISH') ?>
                        </button>
                    <?php else: ?>
						<button class="akengage-comment-publish-btn" data-akengageid="<?= $comment->getId() ?>">
                            <?= Text::_('COM_ENGAGE_COMMENTS_BTN_PUBLISH') ?>
                        </button>
                    <?php endif; ?>
                </span>
				<?php endif; ?>
				<?php if ($this->perms['edit'] || (($this->user->id === $user->id) && $this->perms['own'])): ?>
					<span class="akengage-comment-edit">
                    <button class="akengage-comment-edit-btn" data-akengageid="<?= $comment->getId() ?>">
                        <?= Text::_('COM_ENGAGE_COMMENTS_BTN_EDIT') ?>
                    </button>
                </span>
				<?php endif; ?>
				<?php if ($this->perms['edit'] || $user->authorise('core.manage', $comment->asset_id)): ?>
					<br/>
					<?php if (!empty($ipLookupURL)): ?>
						<span class="akengage-comment-ip">
							<a href="<?= $ipLookupURL ?>" target="_blank">
								<?= Text::sprintf('COM_ENGAGE_COMMENTS_IP', $comment->ip ?? '???') ?>
							</a>
						</span>
					<?php else: ?>
						<span class="akengage-comment-ip">
							<?= Text::sprintf('COM_ENGAGE_COMMENTS_IP', $comment->ip ?? '???') ?>
						</span>
					<?php endif; ?>
					<span class="akengage-comment-publish-type">
						<?= Text::_('COM_ENGAGE_COMMENTS_TYPE_' . (($comment->enabled == 1) ? 'published' : (($comment->enabled == -3) ? 'spam' : 'unpublished'))) ?>
					</span>
				<?php endif; ?>
			</div>
		</footer>

		<div class="akengage-comment-body">
			<?= $comment->body ?>
		</div>

		<?php if ($this->perms['create']): ?>
			<div class="akengage-comment-reply">
				<?php // You can reply to $this->maxLevel - 1 level comments only. Replies to deeper nested comments are to the $this->maxLevel - 1 level parent. ?>
				<button class="akengage-comment-reply-btn"
						data-akengageid="<?= ($comment->getLevel() < $this->maxLevel) ? $comment->getId() : $parentIds[$this->maxLevel - 1] ?>"
						data-akengagereplyto="<?= $this->escape(($comment->getLevel() < $this->maxLevel) ? $user->name : $parentNames[$this->maxLevel - 1]) ?>"
				>
					<?= Text::_('COM_ENGAGE_COMMENTS_BTN_REPLY') ?>
				</button>
			</div>
		<?php endif; ?>
	</article>


	<?php endforeach; ?>

	<?php if ($openListItem): ?>
	<?php $openListItem--; ?>
</li>
<?php endif; ?>
<?php for ($level = $previousLevel; $level >= 1; $level--): ?>
	</ol>
	<?php if ($openListItem): ?>
		<?php $openListItem--; ?>
		</li>
	<?php endif; ?>
<?php endfor; ?>
