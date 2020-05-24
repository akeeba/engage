<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Admin\View\EmailTemplates\Html $this */

use Akeeba\Engage\Admin\Helper\Select;

$keyOptions = Select::emailTemplateKey(true);
?>
@extends('any:lib_fof30/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @selectfilter('key', $keyOptions)
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('subject', 'subject', 'COM_ENGAGE_EMAILTEMPLATES_FIELD_SUBJECT')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        {{ FOF30\Utils\FEFHelper\BrowseView::publishedFilter('enabled', 'JENABLED') }}
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @selectfilter('language', FOF30\Utils\SelectOptions::getOptions('languages'))
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
                @jhtml('FEFHelper.browse.id', ++$i, $row->getId())
            </td>
            <td>
                {{ \FOF30\Utils\FEFHelper\BrowseView::getOptionName($row->key, $keyOptions) }}
            </td>
            <td>
                <a href="@route(FOF30\Utils\FEFHelper\BrowseView::parseFieldTags('index.php?option=com_engage&view=EmailTemplates&task=edit&id=[ITEM:ID]', $row))">
                    {{{ $row->subject }}}
                </a>
            </td>
            <td>
                @jhtml('FEFHelper.browse.published', $row->enabled, $i)
            </td>
            <td>
                {{{ FOF30\Utils\FEFHelper\BrowseView::getOptionName($row->language, \FOF30\Utils\SelectOptions::getOptions('languages', ['none' => 'COM_ENGAGE_EMAILTEMPLATES_FIELD_LANGUAGE_ALL'])) }}}
            </td>
        </tr>
    @endforeach
@stop
