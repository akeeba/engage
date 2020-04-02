<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * @var \Akeeba\Engage\Site\View\Comments\Html $this
 * @var \Akeeba\Engage\Site\Model\Comments     $parent
 * @var \FOF30\Model\DataModel\Collection      $items
 * @var \Akeeba\Engage\Site\Model\Comments     $comment
 */

// If the parent node is a leaf node exit immediately. It doesn't have any descendants, by definition.
if ($parent->isLeaf())
{
    return;
}

// Get the immediate descendants of the parent node
$levelNodes = $items->filter(function (\Akeeba\Engage\Site\Model\Comments $comment) use ($parent) {
	return $comment->isDescendantOf($parent) && ($comment->getLevel() == $parent->getLevel() + 1);
});
?>
<ol class="akengage-comment-list akengage-comment-list--level{{ $parent->getLevel() }}">
    @foreach ($levelNodes as $comment)
    <?php
		$user = $comment->getUser();
		$avatar = $comment->getAvatarURL();
		$profile = $comment->getProfileURL();
		$commentDate = new \FOF30\Date\Date($comment->created_on);
		$permalink = \Joomla\CMS\Uri\Uri::getInstance();
		$permalink->delVar('akengage_limitstart');
		$permalink->delVar('akengage_limit');
		$permalink->setFragment('akengage-comment-' . $comment->getId());
    ?>
    <li class="akengage-comment-item">
        <article class="akengage-comment" id="akengage-comment-{{ $comment->getId() }}">
            <footer class="akengage-comment-properties">
                @unless(empty($avatar))
                    @if (empty($profile))
                        <img src="{{ $avatar }}" alt="@sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name)" class="akengage-commenter-avatar">
                    @else
                        <a href="{{ $profile }}" class="akengage-commenter-profile">
                            <img src="{{ $avatar }}" alt="@sprintf('COM_ENGAGE_COMMENTS_AVATAR_ALT', $user->name)" class="akengage-commenter-avatar">
                        </a>
                    @endif
                @endunless
                <div class="akengange-commenter-name">
                    {{{ $user->name }}}
                </div>
                <div class="akengage-comment-info">
                    <span class="akengage-comment-permalink">
                        <a href="{{ $permalink->toString() }}">
                            {{ $commentDate->format(\Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'), true) }}
                        </a>
                    </span>
                    <span class="akengage-comment-edit">
                        <button id="akengage-comment-edit-btn" data-akengageid="{{ $comment->getId() }}">
                            @lang('COM_ENGAGE_COMMENTS_BTN_EDIT')
                        </button>
                    </span>
                </div>
            </footer>

            <div class="akengage-comment-body">
                {{ $comment->body }}
            </div>

            <div class="akengage-comment-reply">
                <button id="akengage-comment-reply-btn" data-akengageid="{{ $comment->getId() }}">
                    @lang('COM_ENGAGE_COMMENTS_BTN_REPLY')
                </button>
            </div>
        </article>
        @unless ($comment->isLeaf())
            @include('any:com_engage/Comments/default_list', ['items' => $items, 'parent' => $comment])
        @endunless
    </li>
    @endforeach
</ol>
