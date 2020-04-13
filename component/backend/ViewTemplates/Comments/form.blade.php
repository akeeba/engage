<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Admin\View\Comments\Html $this */

$item = $this->item;
$user = $this->container->platform->getUser();
$editOwn = $user->id == $item->created_by;
?>
@extends('admin:com_engage/Common/edit')

@section('edit-form-body')
    @if (!$item->getUser()->guest && $user->authorise('core.manage', 'com_engage'))
        <div class="akeeba-form-group">
            <label for="akengage-comment-edit-form-username">
                @lang('COM_ENGAGE_COMMENT_FIELD_USERNAME')
            </label>
            <input type="text" id="akengage-comment-edit-form-username"
                   disabled="disabled" value="{{{ $item->getUser()->username }}}">
        </div>

        <div class="akeeba-form-group">
            <label for="akengage-comment-edit-form-name">
                @lang('COM_ENGAGE_COMMENT_FIELD_NAME')
            </label>
            <input type="text" id="akengage-comment-edit-form-name"
                   disabled="disabled" value="{{{ $item->getUser()->name }}}">
        </div>

        <div class="akeeba-form-group">
            <label for="akengage-comment-edit-form-email">
                @lang('COM_ENGAGE_COMMENT_FIELD_EMAIL')
            </label>
            <input type="text" id="akengage-comment-edit-form-email"
                   disabled="disabled" value="{{{ $item->getUser()->email }}}">
        </div>
    @endif

    @unless ($editOwn || !$item->getUser()->guest)
        <div class="akeeba-form-group">
            <label id="akengage-comment-edit-form-name" for="akengage-comment-edit-form-name"
                   class="required"
            >
                @lang('COM_ENGAGE_COMMENT_FIELD_NAME')
            </label>

            <input type="text"
                   name="name"
                   id="akengage-comment-edit-form-name"
                   value="{{{ $item->name }}}"
                   class="required"
                   required="required"
                   aria-required="true" aria-invalid="false"
                   size="30">
        </div>

        <div class="akeeba-form-group">
            <label id="akengage-comment-edit-form-email" for="akengage-comment-edit-form-email"
                   class="required"
            >
                @lang('COM_ENGAGE_COMMENT_FIELD_EMAIL')
            </label>

            <input type="email"
                   name="email"
                   id="akengage-comment-edit-form-email"
                   value="{{{ $item->email }}}"
                   class="inputbox required"
                   required="required"
                   aria-required="true" aria-invalid="false"
                   size="30">
        </div>
    @endunless

    @if ($user->authorise('core.edit', 'com_engage'))
        <div class="akeeba-form-group">
            <label id="akengage-comment-edit-form-ip" for="akengage-comment-edit-form-ip"
                   class="required"
            >
                @lang('COM_ENGAGE_COMMENT_FIELD_IP')
            </label>

            <input type="text"
                   name="ip"
                   id="akengage-comment-edit-form-ip"
                   value="{{{ $item->ip }}}"
                   class="inputbox required"
                   required="required"
                   aria-required="true" aria-invalid="false"
                   size="30">
        </div>

        <div class="akeeba-form-group">
            <label id="akengage-comment-edit-form-user-agent" for="akengage-comment-edit-form-user-agent"
                   class="required"
            >
                @lang('COM_ENGAGE_COMMENT_FIELD_USER_AGENT')
            </label>

            <input type="text"
                   name="user_agent"
                   id="akengage-comment-edit-form-user-agent"
                   value="{{{ $item->user_agent }}}"
                   class="inputbox required"
                   required="required"
                   aria-required="true" aria-invalid="false"
                   size="30">
        </div>
    @endif

	@editor('body', $item->body, '100%', '400', 50, 10, false, 'akengage-comment-edit-form-editor')
@stop
