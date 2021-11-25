<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Admin\View\EmailTemplates\Html $this */

/** @var \Akeeba\Engage\Admin\Model\EmailTemplates $item */
$item = $this->item;
$user = $this->container->platform->getUser();
?>
@extends('any:lib_fof40/Common/edit')

@section('edit-form-body')
    <div class="akeeba-form-group">
        <label for="key">
            @fieldtitle('key')
        </label>

        {{ \FOF40\Html\FEFHelper\BrowseView::genericSelect('key', \Akeeba\Component\Engage\Administrator\Helper\Select::emailTemplateKey(), $item->key, ['fof.autosubmit' => false, 'translate' => false]) }}

        <p class="akeeba-help-text">
            @lang('COM_ENGAGE_EMAILTEMPLATES_FIELD_KEY_DESC')
        </p>
    </div>

    <div class="akeeba-form-group">
        <label for="subject">
            @fieldtitle('subject')
        </label>

        <input type="text" name="subject" id="subject" value="{{{ $item->subject }}}" />

        <p class="akeeba-help-text">
            @lang('COM_ENGAGE_EMAILTEMPLATES_FIELD_SUBJECT_DESC')
        </p>
    </div>

    <div class="akeeba-form-group">
        <label for="enabled">
            @lang('JPUBLISHED')
        </label>

        @jhtml('FEFHelp.select.booleanswitch', 'enabled', $item->enabled)
    </div>

    <div class="akeeba-form-group">
        <label for="language">
            @fieldtitle('language')
        </label>

        {{ \FOF40\Html\FEFHelper\BrowseView::genericSelect('language', \FOF40\Html\SelectOptions::getOptions('languages', ['none' => 'COM_ENGAGE_EMAILTEMPLATES_FIELD_LANGUAGE_ALL']), $item->language, ['fof.autosubmit' => false, 'translate' => false]) }}

        <p class="akeeba-help-text">
            @lang('COM_ENGAGE_EMAILTEMPLATES_FIELD_LANGUAGE_DESC')
        </p>
    </div>

    <div class="akeeba-form-group">
        <label for="template">
            @fieldtitle('template')
        </label>

        <div class="akeeba-noreset">
            @jhtml('FEFHelp.edit.editor', 'template', $item->template)
        </div>

        <p class="akeeba-help-text">
            @lang('COM_ENGAGE_EMAILTEMPLATES_FIELD_TEMPLATE_DESC')
        </p>
    </div>

    <div class="akeeba-block--info">
        @lang('COM_ENGAGE_EMAILTEMPLATES_HELP_VARIABLES')
    </div>
@stop
