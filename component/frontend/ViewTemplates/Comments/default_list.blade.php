<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/**
 * @var \Akeeba\Engage\Site\View\Comments\Html $this
 * @var \FOF30\Model\DataModel\Collection      $items
 * @var \Akeeba\Engage\Site\Model\Comments     $comment
 */

$previousLevel = 0;
$openListItem = 0;
$parentIds = [0 => 0];
$parentNames = [0 => ''];
$myUser = $this->container->platform->getUser();
$canCreate = $myUser->authorise('core.create', 'com_engage');
$canEdit = $myUser->authorise('core.edit', 'com_engage');
$canEditOwn = $myUser->authorise('core.edit.own', 'com_engage');
?>
@foreach ($this->getItems() as $comment)
    <?php
    $parentIds[$comment->getLevel()] = $comment->getId();
    $parentNames[$comment->getLevel()] = $comment->getUser()->name;
    ?>
{{-- Deeper level comment. Indent with <ol> tags --}}
@if ($comment->getLevel() > $previousLevel)
    @for($level = $previousLevel + 1; $level <= $comment->getLevel(); $level++)
    <ol class="akengage-comment-list akengage-comment-list--level{{ $level }}">
    @endfor
{{-- Shallower level comment. Outdent with </ol> tags --}}
@elseif($comment->getLevel() < $previousLevel)
    @if($openListItem)
        <?php $openListItem--; ?>
        </li>
    @endif
    @for($level = $previousLevel - 1; $level >= $comment->getLevel(); $level--)
        </ol>
        @if($openListItem)
            <?php $openListItem--; ?>
            </li>
        @endif
    @endfor
{{-- Same level comment. Close the <li> tag. --}}
@else
    <?php $openListItem--; ?>
    </li>
@endif
<?php $previousLevel = $comment->getLevel() ?>

<?php
    $user = $comment->getUser();
    $avatar = $comment->getAvatarURL();
    $profile = $comment->getProfileURL();
    $commentDate = new \FOF30\Date\Date($comment->created_on);
    $openListItem++;
?>
<li class="akengage-comment-item">

    <article class="akengage-comment" id="akengage-comment-{{ $comment->getId() }}">
        <footer class="akengage-comment-properties">
            @unless(empty($avatar))
                @if (empty($profile))
                    <img src="{{ $avatar }}" alt="@sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name)"
                         class="akengage-commenter-avatar">
                @else
                    <a href="{{ $profile }}" class="akengage-commenter-profile">
                        <img src="{{ $avatar }}" alt="@sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name)"
                             class="akengage-commenter-avatar">
                    </a>
                @endif
            @endunless
            <div class="akengange-commenter-name">
                {{{ $user->name }}}
                @if ($user->authorise('core.manage', $comment->asset_id))
                    <span class="akengage-commenter-ismoderator icon icon-star"></span>
                @elseif(!$user->guest)
                    <span class="akengage-commenter-isuser icon icon-user"></span>
                @endif
                @if(!$user->guest)
                    <span class="akengage-commenter-username">{{{ $user->username }}}</span>
                @endif
            </div>
            <div class="akengage-comment-info">
                <span class="akengage-comment-permalink">
                    {{ $commentDate->format(\Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'), true) }}
                </span>
                @if ($canEdit || (($myUser->id === $user->id) && $canEditOwn))
                <span class="akengage-comment-edit">
                    <button class="akengage-comment-edit-btn" data-akengageid="{{ $comment->getId() }}">
                        @lang('COM_ENGAGE_COMMENTS_BTN_EDIT')
                    </button>
                </span>
                @endif
            </div>
        </footer>

        <div class="akengage-comment-body">
            {{ $comment->body }}
        </div>

        @if ($canCreate)
        <div class="akengage-comment-reply">
            {{-- You can reply to $this->maxLevel - 1 level comments only. Replies to deeper nested comments are to the $this->maxLevel - 1 level parent. --}}
            <button class="akengage-comment-reply-btn"
                    data-akengageid="{{ ($comment->getLevel() < $this->maxLevel) ? $comment->getId() : $parentIds[$this->maxLevel - 1] }}"
                    data-akengagereplyto="{{{ ($comment->getLevel() < $this->maxLevel) ? $user->name : $parentNames[$this->maxLevel - 1] }}}"
            >
                @lang('COM_ENGAGE_COMMENTS_BTN_REPLY')
            </button>
        </div>
        @endif
    </article>


@endforeach

@if($openListItem)
    <?php $openListItem--; ?>
    </li>
@endif
@for($level = $previousLevel; $level >= 1; $level--)
    </ol>
    @if($openListItem)
        <?php $openListItem--; ?>
        </li>
    @endif
@endfor
