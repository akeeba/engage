<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Admin\View\EmailTemplates\Html $this */

use Akeeba\Component\Engage\Administrator\Helper\Select;

$keyOptions = Select::emailTemplateKey(true);
?>
@extends('any:lib_fof40/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @selectfilter('key', $keyOptions)
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('subject', 'subject', 'COM_ENGAGE_EMAILTEMPLATES_FIELD_SUBJECT')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        {{ FOF40\Html\FEFHelper\BrowseView::publishedFilter('enabled', 'JENABLED') }}
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @selectfilter('language', FOF40\Html\SelectOptions::getOptions('languages'))
    </div>
@stop

@section('browse-table-header')
    <tr>
        <th width="40px">
            @sortgrid('engage_emailtemplate_id', 'JGLOBAL_NUM')
        </th>
        <th>
            @sortgrid('key')
        </th>
        <th>
            @sortgrid('subject')
        </th>
        <th>
            @sortgrid('enabled', 'JENABLED')
        </th>
        <th>
            @sortgrid('language')
        </th>
    </tr>
@stop

@section('browse-table-body-withrecords')
	<?php
	/** @var \Akeeba\Engage\Admin\Model\EmailTemplates $row */
	$i = 0;
	?>
    @foreach($this->getItems() as $row)
        <tr>
            <td>
                @jhtml('FEFHelp.browse.id', ++$i, $row->getId())
            </td>
            <td>
                {{ \FOF40\Html\FEFHelper\BrowseView::getOptionName($row->key, $keyOptions) }}
            </td>
            <td>
                <a href="@route(FOF40\Html\FEFHelper\BrowseView::parseFieldTags('index.php?option=com_engage&view=EmailTemplates&task=edit&id=[ITEM:ID]', $row))">
                    {{{ $row->subject }}}
                </a>
            </td>
            <td>
                @jhtml('FEFHelp.browse.published', $row->enabled, $i)
            </td>
            <td>
                {{{ FOF40\Html\FEFHelper\BrowseView::getOptionName($row->language, \FOF40\Html\SelectOptions::getOptions('languages', ['none' => 'COM_ENGAGE_EMAILTEMPLATES_FIELD_LANGUAGE_ALL'])) }}}
            </td>
        </tr>
    @endforeach
@stop
