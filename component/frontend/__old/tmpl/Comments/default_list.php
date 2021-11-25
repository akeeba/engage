<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * View Template for the threaded display of comments.
 *
 * Called from default.php
 */

use Akeeba\Component\Engage\Administrator\Helper\Format;
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
// Deeper level comment. Indent with <ol> tags
if ($comment->depth > $previousLevel):
	?>
	<?php for ($level = $previousLevel + 1; $level <= $comment->depth; $level++): ?>
	<ol class="akengage-comment-list akengage-comment-list--level<?= $level ?>">
<?php endfor; ?>
<?php // Shallower level comment. Outdent with </ol> tags
elseif ($comment->depth < $previousLevel): ?>
	<?php if ($openListItem): $openListItem--; ?>
		</li>
	<?php endif; ?>
	<?php for ($level = $previousLevel - 1; $level >= $comment->depth; $level--): ?>
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
$previousLevel = $comment->depth;
$user          = $comment->getUser();
$avatar        = $comment->getAvatarURL();
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
			<div itemprop="author" itemscope itemtype="http://schema.org/Person">
				<?php if (!empty($avatar)): ?>
					<?php if (empty($profile)): ?>
						<img src="<?= $avatar ?>" alt=""
							 class="akengage-commenter-avatar" itemprop="image">
					<?php else: ?>
						<a href="<?= $profile ?>" class="akengage-commenter-profile" itemprop="url" rel="noopener">
							<img src="<?= $avatar ?>"
								 alt=""
								 class="akengage-commenter-avatar" itemprop="image">
						</a>
					<?php endif; ?>
				<?php endif; ?>
				<div class="akengange-commenter-name">
					<span itemprop="name"><?= $this->escape($user->name) ?></span>
					<?php if ($user->authorise('core.manage', $comment->asset_id)): ?>
						<span class="akengage-commenter-ismoderator akion-star" aria-hidden="true"></span>
					<?php elseif (!$user->guest): ?>
						<span class="akengage-commenter-isuser akion-person" aria-hidden="true"></span>
					<?php endif; ?>
					<?php if (!$user->guest): ?>
						<span class="akengage-commenter-username"><?= $this->escape($user->username) ?></span>
					<?php elseif ($this->perms['state']): ?>
						<span class="akengage-commenter-isguest akion-person-stalker" aria-hidden="true"></span>
						<span class="akengage-commenter-email"><?= $this->escape($user->email) ?></span>
					<?php endif; ?>
				</div>
			</div>
			<div class="akengage-comment-info">
                <span class="akengage-comment-permalink" itemprop="dateCreated" content="<?= $commentDate->toISO8601(false) ?>">
                    <?= $commentDate->format(Text::_('DATE_FORMAT_LC2'), true) ?>
                </span>
				<span class="akengage-comment-actions">
					<?php if ($this->perms['state']): ?>
						<?php if ($comment->enabled == 1): ?>
							<span class="akengage-comment-publish_unpublish">
								<button class="akengage-comment-unpublish-btn" data-akengageid="<?= $comment->getId() ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_UNPUBLISH') ?>
								</button>
							</span>
						<?php elseif ($comment->enabled == 0): ?>
							<span class="akengage-comment-publish_unpublish">
								<button class="akengage-comment-publish-btn" data-akengageid="<?= $comment->getId() ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_PUBLISH') ?>
								</button>
							</span>
						<?php endif; ?>
						<?php if($comment->enabled == -3): ?>
							<span class="akengage-comment-mark-ham">
								<button class="akengage-comment-markham-btn" data-akengageid="<?= $comment->getId() ?>"
									title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKHAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKHAM') ?>
								</button>
							</span>
							<?php if ($this->perms['delete']): ?>
							<span class="akengage-comment-mark-spam">
								<button class="akengage-comment-markspam-btn" data-akengageid="<?= $comment->getId() ?>"
									title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKSPAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKSPAM') ?>
								</button>
							</span>
							<?php endif; ?>
						<?php else: ?>
							<span class="akengage-comment-mark-possiblespam">
								<button class="akengage-comment-possiblespam-btn" data-akengageid="<?= $comment->getId() ?>"
									title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_POSSIBLESPAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_POSSIBLESPAM') ?>
								</button>
							</span>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ($this->perms['delete']): ?>
					<span class="akengage-comment-delete">
						<button class="akengage-comment-delete-btn" data-akengageid="<?= $comment->getId() ?>">
							<?= Text::_('COM_ENGAGE_COMMENTS_BTN_DELETE') ?>
						</button>
					</span>
					<?php endif; ?>
					<?php if ($this->perms['edit'] || (($this->user->id === $user->id) && $this->perms['own'])): ?>
					<span class="akengage-comment-edit">
						<button class="akengage-comment-edit-btn" data-akengageid="<?= $comment->getId() ?>">
							<?= Text::_('COM_ENGAGE_COMMENTS_BTN_EDIT') ?>
						</button>
					</span>
					<?php endif; ?>
					<?php if ($this->perms['edit'] || $this->user->authorise('core.manage', $comment->asset_id)): ?>
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
				</span>
			</div>
		</footer>

		<div class="akengage-comment-body" itemprop="text">
			<?= Format::processCommentTextForDisplay($comment->body) ?>
		</div>

		<?php if ($this->perms['create']): ?>
			<div class="akengage-comment-reply">
				<?php // You can reply to $this->maxLevel - 1 level comments only. Replies to deeper nested comments are to the $this->maxLevel - 1 level parent. ?>
				<button class="akengage-comment-reply-btn"
						data-akengageid="<?= ($comment->depth < $this->maxLevel) ? $comment->getId() : $parentIds[$this->maxLevel - 1] ?>"
						data-akengagereplyto="<?= $this->escape(($comment->depth < $this->maxLevel) ? $user->name : $parentNames[$this->maxLevel - 1]) ?>"
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
