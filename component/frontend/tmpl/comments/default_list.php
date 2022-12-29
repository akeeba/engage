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
 * Loaded from default.php
 */

use Akeeba\Component\Engage\Administrator\Helper\Avatar;
use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

// Maximum avatar width, in pixels.
$maxAvatarWidth = 48;

/** @var \Akeeba\Component\Engage\Site\View\Comments\HtmlView $this  */

$previousLevel = 0;
$openListItem  = 0;
$parentIds     = [0 => 0];
$parentNames   = [0 => ''];

foreach ($this->items as $comment):
$user = !empty($comment->created_by) && empty($comment->name) ? UserFetcher::getUser($comment->created_by) : new User();

if (empty($comment->created_by) || !empty($comment->name)) {
	$user->name  = $comment->name;
	$user->email = $comment->email;
}

$parentIds[$comment->depth] = $comment->id;
$parentNames[$comment->depth] = $user->name;
// Deeper level comment. Indent with <ul> tags
if ($comment->depth > $previousLevel):
	?>
	<?php for ($level = $previousLevel + 1; $level <= $comment->depth; $level++): ?>
	<ul class="akengage-comment-list akengage-comment-list--level<?= $level ?> list-unstyled">
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
$avatar        = Avatar::getUserAvatar($comment->created_by, $maxAvatarWidth, $comment->email);
$profile       = Avatar::getProfileURL($user);
$commentDate   = Factory::getDate($comment->created)->setTimezone($this->userTimezone);
$ipLookupURL  = $this->getIPLookupURL($comment->ip);
$openListItem++;
$this->ensureHasParentInfo($comment, $parentIds, $parentNames);
$bsCommentStateClass =  ($comment->enabled == 1) ? 'secondary' : (($comment->enabled == -3) ? 'warning' : 'danger')
?>
<li class="akengage-comment-item mb-2">

	<article
			class="akengage-comment--<?= ($comment->enabled == 1) ? 'primary' : (($comment->enabled == -3) ? 'spam' : 'unpublished') ?> border-start border-4 border-<?= $bsCommentStateClass ?> ps-2 mb-2"
			id="akengage-comment-<?= $comment->id ?>" itemscope itemtype="http://schema.org/Comment">
		<footer
				itemprop="author" itemscope itemtype="http://schema.org/Person"
				class="akengage-comment-properties d-flex flex-row gap-1 mb-1 bg-light p-1 small border-bottom border-2">
			<?php if (!empty($avatar)): ?>
			<div class="akengage-commenter-avatar-container d-none d-sm-block flex-shrink-1" style="max-width: <?= (int) $maxAvatarWidth ?>px">
				<?php if (empty($profile)): ?>
				<img src="<?= $avatar ?>" alt="" class="akengage-commenter-avatar img-fluid rounded-3 shadow-sm" itemprop="image">
				<?php else: ?>
				<a href="<?= $profile ?>" class="akengage-commenter-profile" itemprop="url" rel="noopener">
					<img src="<?= $avatar ?>"
							alt=""
							class="akengage-commenter-avatar img-fluid rounded-3 shadow-sm" itemprop="image">
				</a>
				<?php endif; ?>
			</div>
			<?php endif; ?>
			<div class="akengange-comment-head d-flex flex-column w-100">
				<div class="akengange-commenter-name d-flex flex-row flex-wrap gap-3 align-items-center mb-1">
					<span itemprop="name" class="fw-bold flex-grow-1"><?= $this->escape($user->name) ?></span>

					<?php if ($this->perms['state']): ?>
					<div>
						<?php if ($user->authorise('core.manage', $comment->asset_id)): ?>
							<span class="akengage-commenter-ismoderator fa fa-star text-warning" aria-hidden="true"></span>
						<?php elseif (!$user->guest): ?>
							<span class="akengage-commenter-isuser fa fa-user text-secondary" aria-hidden="true"></span>
						<?php endif; ?>
						<?php if (!$user->guest): ?>
							<span class="akengage-commenter-username font-monospace text-success"><?= $this->escape($user->username) ?></span>
						<?php elseif ($this->perms['state']): ?>
							<span class="akengage-commenter-isguest fa fa-user-friends text-danger" aria-hidden="true"></span>
							<span class="akengage-commenter-email font-monospace text-muted"><?= $this->escape($user->email) ?></span>
						<?php endif; ?>
					</div>
					<?php endif; ?>
				</div>
				<div class="akengage-comment-info d-flex flex-row flex-wrap gap-2 align-items-center">
					<div class="akengage-comment-permalink flex-grow-1">
						<?php
						$tempUri = clone Uri::getInstance();
						$tempUri->setFragment(sprintf('akengage-comment-%u', $comment->id));
						$tempUri->setVar('akengage_cid', $comment->id);
						?>
						<a href="<?= $tempUri->toString() ?>"
								class="text-body text-decoration-none"
						>
						<span itemprop="dateCreated" content="<?= $commentDate->toISO8601(false) ?>">
							<?= $commentDate->format(Text::_('DATE_FORMAT_LC2'), true) ?>
						</span>
						</a>
					</div>
					<div class="akengage-comment-actions d-flex gap-1">
					<?php if ($this->perms['state']): ?>
						<span class="akengage-comment-publish_unpublish">
						<?php if ($comment->enabled == 1): ?>
							<button class="akengage-comment-unpublish-btn btn btn-sm btn-outline-secondary"
									data-akengageid="<?= $comment->id ?>">
								<?= Text::_('COM_ENGAGE_COMMENTS_BTN_UNPUBLISH') ?>
							</button>
						<?php elseif ($comment->enabled == 0): ?>
							<button class="akengage-comment-publish-btn btn btn-sm btn-outline-secondary"
									data-akengageid="<?= $comment->id ?>">
								<?= Text::_('COM_ENGAGE_COMMENTS_BTN_PUBLISH') ?>
							</button>
						<?php endif; ?>
						</span>

						<?php if($comment->enabled == -3): ?>
							<span class="akengage-comment-mark-ham">
								<button class="akengage-comment-markham-btn btn btn-sm btn-outline-success"
										data-akengageid="<?= $comment->id ?>"
										title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKHAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKHAM') ?>
								</button>
							</span>
							<?php if ($this->perms['delete']): ?>
								<span class="akengage-comment-mark-spam">
								<button class="akengage-comment-markspam-btn btn btn-sm btn-outline-danger"
										data-akengageid="<?= $comment->id ?>"
										title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKSPAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_MARKSPAM') ?>
								</button>
							</span>
							<?php endif; ?>
						<?php else: ?>
							<span class="akengage-comment-mark-possiblespam">
								<button class="akengage-comment-possiblespam-btn btn btn-sm btn-outline-warning"
										data-akengageid="<?= $comment->id ?>"
										title="<?= Text::_('COM_ENGAGE_COMMENTS_BTN_POSSIBLESPAM_TITLE') ?>">
									<?= Text::_('COM_ENGAGE_COMMENTS_BTN_POSSIBLESPAM') ?>
								</button>
							</span>
						<?php endif; ?>
					<?php endif; ?>
					<?php if ($this->perms['delete']): ?>
					<span class="akengage-comment-delete">
						<button class="akengage-comment-delete-btn btn btn-sm btn-outline-danger"
								data-akengageid="<?= $comment->id ?>">
							<?= Text::_('COM_ENGAGE_COMMENTS_BTN_DELETE') ?>
						</button>
					</span>
					<?php endif; ?>
					<?php if ($this->perms['edit'] || (($this->user->id === $user->id) && $this->perms['own'])): ?>
						<span class="akengage-comment-edit">
							<button class="akengage-comment-edit-btn btn btn-sm btn-outline-primary"
									data-akengageid="<?= $comment->id ?>">
								<?= Text::_('COM_ENGAGE_COMMENTS_BTN_EDIT') ?>
							</button>
						</span>
					<?php endif; ?>
					</div>
				</div>
				<?php if ($this->perms['edit'] || $this->user->authorise('core.manage', $comment->asset_id)): ?>
				<div>
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
				</div>
				<?php endif; ?>
			</div>
		</footer>

		<?php if ($comment->enabled == -3): ?>
		<div class="akengage-comment-publish-type bg-warning text-white fw-bold p-2">
			<?= Text::_('COM_ENGAGE_COMMENTS_TYPE_SPAM') ?>
		</div>
		<?php elseif ($comment->enabled != 1): ?>
		<div class="akengage-comment-publish-type bg-danger text-white fw-bold p-2">
			<?= Text::_('COM_ENGAGE_COMMENTS_TYPE_UNPUBLISHED') ?>
		</div>
		<?php endif ?>


		<div class="akengage-comment-body" itemprop="text">
			<?= HTMLHelper::_('engage.processCommentTextForDisplay', $comment->body) ?>
			<?php if (!empty($comment->modified_by)): ?>
			<div class="my-2 border-top border-1 border-muted text-muted small">
				<?= Text::sprintf('COM_ENGAGE_LBL_COMMENT_MODIFIED', Factory::getDate($comment->modified)->setTimezone($this->userTimezone)->format(Text::_('DATE_FORMAT_LC2'), true), $comment->name ?: UserFetcher::getUser($comment->modified_by)->name) ?>
			</div>
			<?php endif; ?>
		</div>

		<?php if ($this->perms['create']): ?>
			<div class="akengage-comment-reply">
				<?php // You can reply to $this->maxLevel - 1 level comments only. Replies to deeper nested comments are to the $this->maxLevel - 1 level parent. ?>
				<button class="akengage-comment-reply-btn btn btn-sm btn-outline-primary mb-1"
						data-akengageid="<?= ($comment->depth < $this->maxLevel) ? $comment->id : $parentIds[$this->maxLevel - 1] ?>"
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
	</ul>
	<?php if ($openListItem): ?>
		<?php $openListItem--; ?>
		</li>
	<?php endif; ?>
<?php endfor; ?>
