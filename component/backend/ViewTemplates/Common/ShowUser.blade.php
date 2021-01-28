<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * User information display field
 * Use it $this->loadAnyTemplate('admin:com_engage/Common/ShowUser', $params)
 *
 * $params is an array defining the following keys (they are expanded into local scope vars automatically):
 *
 * @var Comments                 $item The current row
 * @var string                   $id   The ID of the generated DIV
 * @var string                   $showUsername
 * @var string                   $showEmail
 * @var string                   $showName
 * @var string                   $showID
 * @var string                   $showAvatar
 * @var string                   $showLink
 * @var string                   $linkURL
 * @var string                   $avatarSize
 * @var string                   $class
 *
 * Variables made automatically available to us by FOF:
 *
 * @var \FOF40\View\DataView\Raw $this
 */

use Akeeba\Engage\Admin\Model\Comments;
use FOF40\Html\FEFHelper\BrowseView;

defined('_JEXEC') or die;

global $engageShowUserCache;

if (!isset($engageShowUserCache))
{
	$engageShowUserCache = [];
}

// Get field parameters
$defaultParams = [
	'id'           => '',
	'showUsername' => true,
	'showEmail'    => true,
	'showName'     => true,
	'showID'       => false,
	'showAvatar'   => true,
	'showLink'     => true,
	'linkURL'      => null,
	'avatarSize'   => 48,
	'class'        => 'engage-userfield',
];

foreach ($defaultParams as $paramName => $paramValue)
{
	if (!isset(${$paramName}))
	{
		${$paramName} = $paramValue;
	}
}

unset($defaultParams, $paramName, $paramValue);

// Get the field parameters
if (!$linkURL && $this->getContainer()->platform->isBackend())
{
	$linkURL = 'index.php?option=com_users&task=user.edit&id=[USER:ID]';
}
elseif (!$linkURL)
{
	// If no link is defined in the front-end, we can't create a default link. Therefore, show no link.
	$showLink = false;
}

$user = $item->getUser();

// Post-process the link URL
if ($showLink)
{
	$replacements = array(
		'[USER:ID]'       => $user->id,
		'[USER:USERNAME]' => $user->username,
		'[USER:EMAIL]'    => $user->email,
		'[USER:NAME]'     => $user->name,
	);

	foreach ($replacements as $key => $value)
	{
		$linkURL = str_replace($key, $value, $linkURL);
	}

	$linkURL = BrowseView::parseFieldTags($linkURL, $item);
}

// Get the avatar image, if necessary
$avatarURL = $item->getAvatarURL($avatarSize);

?>
<div id="{{ $id }}" class="{{ $class }}">
    @if($showAvatar && $avatarURL)
        <img src="{{ $avatarURL }}" align="left" class="engage-usersfield-avatar" />
    @endif
    @if($showLink && !$user->guest)
        <a href="{{ $linkURL }}">
	@endif
	<span class="engage-usersfield-usertype">
		@if ($user->authorise('core.edit.state', 'com_engage'))
			<span class="akion-star hasTooltip" title="@lang('COM_ENGAGE_COMMENTS_LBL_USERTYPE_MANAGER')"></span>
		@elseif ($user->guest)
			<span class="akion-person-stalker hasTooltip" title="@lang('COM_ENGAGE_COMMENTS_LBL_USERTYPE_GUEST')"></span>
		@else
			<span class="akion-person hasTooltip" title="@lang('COM_ENGAGE_COMMENTS_LBL_USERTYPE_USER')"></span>
		@endif
	</span>
	@if($showName)
		<span class="engage-usersfield-name">
			{{{ $user->name }}}
		</span>
	@endif
	@unless ($user->guest)
		@if($showUsername)
		<span class="engage-usersfield-username">
			{{{ $user->username }}}
		</span>
		@endif
		@if($showID)
		<span class="engage-usersfield-id">
			{{{ $user->id }}}
		</span>
		@endif
	@endunless
	@if($showEmail)
		<span class="engage-usersfield-email">
			{{{ $user->email }}}
		</span>
	@endif
	@if($showLink && !$user->guest)
		</a>
	@endif
</div>
