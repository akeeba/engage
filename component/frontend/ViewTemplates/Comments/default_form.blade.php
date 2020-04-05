<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

$captcha = $this->getCaptchaField();

?>
@jhtml('behavior.formvalidator')
<div class="akengage-comment-form" id="akengage-comment-form">
    <h4>
        @lang('COM_ENGAGE_COMMENTS_FORM_HEADER')
    </h4>
    <form class="form-horizontal form-validate" action="index.php" method="post" name="akengageCommentForm"
          id="akengageCommentForm">
        <input type="hidden" name="option" value="com_engage">
        <input type="hidden" name="view" value="Comments">
        <input type="hidden" name="task" value="submit">
        <input type="hidden" name="@token(false)" value="1">
        <input type="hidden" name="asset_id" value="{{ $this->assetId }}">
        <input type="hidden" name="parent_id" value="0">
        <input type="hidden" name="returnurl" value="{{ base64_encode(\Joomla\CMS\Uri\Uri::getInstance()->toString()) }}">

        <div id="akengage-comment-inreplyto-wrapper">
            @lang('COM_ENGAGE_COMMENTS_FORM_INREPLYTO_LABEL')
            <span id="akengage-comment-inreplyto-name">Some User</span>
            <button id="akengage-comment-inreplyto-cancel" type="button">@lang('COM_ENGAGE_COMMENTS_FORM_CANCELREPLY')</button>
        </div>

        @if ($this->getContainer()->platform->getUser()->guest)
        <div class="control-group">
            <div class="control-label">
                <label id="akengage-comment-form-name" for="akengage-comment-form-name"
                       class="required"
                >
                    @lang('COM_ENGAGE_COMMENTS_FORM_NAME_LABEL')
                    <span class="star">&nbsp;*</span>
                </label>
            </div>
            <div class="controls">
                <input type="text" name="name" id="akengage-comment-form-name"
                       value="{{{ $this->storedName }}}" class="inputbox required" required="required"
                       aria-required="true" aria-invalid="false"
                       placeholder="@lang('COM_ENGAGE_COMMENTS_FORM_NAME_PLACEHOLDER')"
                       size="30">
            </div>
        </div>

        <div class="control-group">
            <div class="control-label">
                <label id="akengage-comment-form-email" for="akengage-comment-form-email"
                       class="required"
                >
                    @lang('COM_ENGAGE_COMMENTS_FORM_EMAIL_LABEL')
                    <span class="star">&nbsp;*</span>
                </label>
            </div>
            <div class="controls">
                <input type="text" name="email" id="akengage-comment-form-email"
                       value="{{{ $this->storedEmail }}}" class="inputbox required" required="required"
                       aria-required="true" aria-invalid="false"
                       placeholder="@lang('COM_ENGAGE_COMMENTS_FORM_EMAIL_PLACEHOLDER')"
                       size="30">
            </div>
        </div>
        @endif

        @editor('comment', $this->storedComment, '100%', '400', 50, 10, false, 'akengage-comment-editor')

        @unless (empty($captcha))
        <div class="akengage-comment-captcha-wrapper">
            {{ $captcha }}
        </div>
        <div class="akengage-comment-captcha-clear"></div>
        @endunless

        <div class="btn-toolbar">
            <div class="btn-group">
                <button type="submit" class="btn btn-primary">
                    @lang('COM_ENGAGE_COMMENTS_FORM_BTN_SUBMIT')
                </button>
            </div>
        </div>
    </form>
</div>