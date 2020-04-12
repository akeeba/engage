<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Admin\View\Comments\Html $this */

\Akeeba\Engage\Site\Helper\Filter::includeHTMLPurifier();

$config = HTMLPurifier_Config::createDefault();
$config->set('Core.Encoding', 'UTF-8');
$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
$config->set('Cache.SerializerPath', \Akeeba\Engage\Site\Helper\Filter::getCachePath());
$config->set('HTML.Allowed', 'p,b,a[href],i,u,strong,em,small,big,ul,ol,li,br,img[src],img[width],img[height],code,pre,blockquote');
$purifier = new HTMLPurifier($config);


?>
{{-- Old PHP version reminder --}}
@include('admin:com_engage/Common/phpversion_warning', [
    'softwareName'  => 'Akeeba Engage',
    'minPHPVersion' => '7.1.0',
])

@extends('admin:com_engage/Common/browse')

@section('browse-filters')
    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('body', 'body', 'COM_ENGAGE_COMMENTS_FILTER_BODY')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('asset_title', 'asset_title', 'COM_ENGAGE_COMMENTS_FILTER_ASSET_TITLE')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('filter_email', 'filter_email', 'COM_ENGAGE_COMMENTS_FILTER_EMAIL')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @searchfilter('ip', 'ip', 'COM_ENGAGE_COMMENTS_FILTER_IP')
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        @selectfilter('enabled', \Akeeba\Engage\Admin\Helper\Select::published(), 'JENABLED')
    </div>
@stop

@section('browse-table-header')
    <tr>
        <th width="20px">
            @jhtml('FEFHelper.browse.checkall')
        </th>
        <th width="20%">
            @fieldtitle('created_by')
        </th>
        <th>
            @fieldtitle('body')
        </th>
        <th width="20%">
            @fieldtitle('asset_id')
        </th>
        <th width="20%">
            @fieldtitle('created_on')
        </th>
        <th width="8%">
            @lang('JENABLED')
        </th>
    </tr>
@stop

@section('browse-table-body-withrecords')
	<?php
	$i = 0;
	$user = $this->getContainer()->platform->getUser();
	$canChange = $user->authorise('core.edit.state', 'com_engage');
	/** @var \Akeeba\Engage\Admin\Model\Comments $item */
	?>
    @foreach ($this->items as $item)
		<?php
        $meta = \Akeeba\Engage\Site\Helper\Meta::getAssetAccessMeta($item->asset_id, false);
		$ipLookupUrl = \Akeeba\Engage\Admin\Helper\Format::getIPLookupURL($item->ip);
		$jBrowser = \Joomla\CMS\Environment\Browser::getInstance($item->user_agent)
		?>
        <tr>
            <td>
                @jhtml('FEFHelper.browse.id', ++$i, $item->getId())
            </td>
            <td>
                @include('admin:com_engage/Common/ShowUser', ['item' => $item, 'showLink' => false, 'avatarSize' => '32'])
                <div style="clear:both"></div>
                <div>
                    <span class="engage_user_agent--{{ $jBrowser->isMobile() ? 'mobile' : 'desktop' }}">
                        <span class="hasTooltip"
                              title="@lang('COM_ENGAGE_COMMENTS_LBL_BROWSERTYPE_' . ($jBrowser->isMobile() ? 'mobile' : 'desktop'))">
                            <span class="akion-{{ $jBrowser->isMobile() ? 'iphone' : 'android-desktop' }}"></span>
                        </span>
                    </span>
                    <span class="engage_ip">
                        @unless (empty($ipLookupUrl))
                            <a href="{{ $ipLookupUrl }}">
                                {{{ $item->ip }}}
                            </a>
                        @else
                            {{{ $item->ip }}}
                        @endunless
                    </span>
                </div>
            </td>
            <td class="engage-comment-preview">
                {{ $purifier->purify($item->body) }}
            </td>
            <td>
                <a href="{{ $meta['url'] }}">
                    {{{ $meta['title'] }}}
                </a>
            </td>
            <td>
                {{ (new FOF30\Date\Date($item->created_on))->format(\Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'), true, true) }}
            </td>
            <td>
                @if ($item->enabled === -3)
                @else
                    @jhtml('jgrid.published', $item->enabled, $i, '', $canChange)
                @endif
            </td>
        </tr>
    @endforeach
@stop