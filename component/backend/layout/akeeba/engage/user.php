<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

// Protect from unauthorized access
use Akeeba\Component\Engage\Administrator\Helper\Avatar;

defined('_JEXEC') || die();

/**
 * @var array  $displayData  Incoming display data. These set the following variables.
 * @var int    $user_id      User ID. Required when showLink is true.
 * @var string $username     Username. Required for $showUsername.
 * @var string $name         Full name. Required for $showName.
 * @var string $email        Email address. Required for $showEmail and $showAvatar.
 * @var bool   $showUsername Should I display the username?
 * @var bool   $showLink     Should I make the username linked? Requires $showUsername.
 * @var string $link         The link to the username. Use [USER_ID], [USERNAME], [NAME] or [EMAIL] in the link.
 * @var bool   $showName     Should I show the full name?
 * @var bool   $showEmail    Should I show the email address?
 * @var bool   $showUserId   Should I show the user ID?
 * @var bool   $showAvatar   Should I show the avatar of the user?
 * @var int    $avatarSize   Avatar size in pixels. Default: 48.
 */

extract(array_merge([
	'user_id'      => 0,
	'username'     => '',
	'name'         => '',
	'email'        => '',
	'showUsername' => true,
	'showLink'     => false,
	'link'         => 'index.php?option=com_users&task=user.edit&id=[USER_ID]',
	'showName'     => true,
	'showEmail'    => true,
	'showUserId'   => false,
	'showAvatar'   => true,
	'avatarSize'   => 48,
], $displayData));

$showUsername = $showUsername && !empty($name);
$showLink     = $showLink && $showUsername;
$showName     = $showName && !empty($name);
$showEmail    = $showEmail && !empty($email);
$showUserId   = $showUserId && !empty($user_id);
$showAvatar   = $showAvatar && !empty($email);

$link = $showLink
	? str_replace(['[USER_ID]', '[USERNAME]', '[NAME]', '[EMAIL]'], [$user_id, $username, $name, $email], $link)
	: '';

$avatarUrl  = $showAvatar ? Avatar::getUserAvatar($user_id, $avatarSize, $email) : null;
$email      = str_replace(['@', '.'], ['<wbr>@', '<wbr>.'], $this->escape($email));
$showAvatar = $showAvatar && !empty($avatarUrl);

?>
<?php if ($showAvatar && !$showName && !$showUsername && !$showUserId && !$showEmail): ?>
	<img src="<?= $avatarUrl ?>" alt="" width="<?= $avatarSize ?>" class="img-fluid rounded rounded-3">
<?php else: ?>
	<div class="d-flex">
		<?php if ($showAvatar): ?>
			<div class="pe-2 pb-1">
				<img src="<?= $avatarUrl ?>" alt="" class="img-fluid rounded rounded-3">
			</div>
		<?php endif; ?>

		<div>
			<?php if ($showUsername): ?><strong>
				<?php if ($showLink): ?>
					<a href="<?= $link ?>"><?= $this->escape($name) ?></a>
				<?php else: ?>
					<?= $this->escape($name) ?>
				<?php endif; ?>
				</strong><?php endif; ?>
			<?php if ($showUserId): ?><small class="text-muted fst-italic ps-1">[<?= $user_id ?>]</small><?php endif; ?>
			<?php if (($showUsername || $showUserId) && ($showUsername || $showEmail)): ?><br /><?php endif; ?>
			<?php if ($showUsername): ?>
			<span class="text-success">
				<?= $this->escape($username) ?>
			</span>
			<?php endif; ?>
		</div>
	</div>
	<?php if ($showEmail): ?>
	<span class="text-muted fst-italic fs-6">
		<?= $email ?>
	</span>
	<?php endif; ?>
<?php endif; ?>


