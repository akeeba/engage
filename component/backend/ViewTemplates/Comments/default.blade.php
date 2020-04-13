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
@extends('admin:com_engage/Common/browse')

@section('browse-page-top')
    {{-- Old PHP version reminder --}}
    @include('admin:com_engage/Common/phpversion_warning', [
        'softwareName'  => 'Akeeba Engage',
        'minPHPVersion' => '7.1.0',
    ])
@stop

@section('browse-ordering')
    {{-- Table ordering --}}
    @jhtml('FEFHelper.browse.orderjs', $this->lists->order)

    <div class="akeeba-filter-element akeeba-form-group">
        <label for="limit" class="element-invisible">
            @lang('JFIELD_PLG_SEARCH_SEARCHLIMIT_DESC')
        </label>
        {{ $this->pagination->getLimitBox() }}
    </div>
@stop

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
		$numComments = \Akeeba\Engage\Site\Helper\Meta::getNumCommentsForAsset($item->asset_id);
		$ipLookupUrl = \Akeeba\Engage\Admin\Helper\Format::getIPLookupURL($item->ip);
		$jBrowser = \Joomla\CMS\Environment\Browser::getInstance($item->user_agent);
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
                            <a href="{{ $ipLookupUrl }}" target="_blank">
                                <span class="engage-sr-only">
                                    @lang('COM_ENGAGE_COMMENTS_FILTER_IP')
                                </span>
                                {{{ $item->ip }}}
                            </a>
                        @else
                            {{{ $item->ip }}}
                        @endunless
                    </span>
                    <a href="@route('index.php?option=com_engage&view=Comments&ip=' . urlencode($item->ip))&limitstart=0"
                       class="akeeba-btn--mini hasTooltip"
                       title="@lang('JSEARCH_FILTER_LABEL')"
                    >
                        <span class="akion-search" aria-hidden="true"></span>
                        <span class="engage-sr-only">
                            @lang('JSEARCH_FILTER_LABEL')
                        </span>
                    </a>
                </div>
            </td>
            <td class="engage-comment-preview">
                @if ($item->getLevel() > 1)
                <?php
                    try
                    {
                        $parent     = $item->getParent();
                        $limitStart = \Akeeba\Engage\Site\Helper\Meta::getLimitStartForComment($parent);
                        $public_uri = new \Joomla\CMS\Uri\Uri($meta['public_url']);
                        $public_uri->setFragment('akengage-comment-' . $parent->getId());
                        $public_uri->setVar('akengage_limitstart', $limitStart);
                    }
                    catch (Exception $e)
                    {
                    	$parent = null;
                    }
		            ?>
                    @unless(is_null($parent))
                    <div class="engage-in-reply-to">
                        @sprintf('COM_ENGAGE_COMMENTS_LBL_INREPLYTO', $public_uri->toString(), $parent->getUser()->name)
                    </div>
                    @endunless
                @endif
                {{ $purifier->purify($item->body) }}
                <div class="engage-edit-link">
                    <a href="@route('index.php?option=com_engage&view=Comments&task=edit&id=' . $item->getId())">
                        @lang('JGLOBAL_EDIT')
                    </a>
                </div>
            </td>
            <td>
                <div class="engage-content-title">
                    <a href="{{ $meta['url'] }}">
                        {{{ $meta['title'] }}}
                    </a>
                </div>
                <div class="engage-content-link">
                    <a href="{{ $meta['public_url'] }}" target="_blank" class="hasTooltip"
                        title="@lang('COM_ENGAGE_COMMENTS_LBL_VIEWCONTENT_DESC')">
                        @lang('COM_ENGAGE_COMMENTS_LBL_VIEWCONTENT')
                    </a>
                </div>
                <div class="engage-content-comments-filter">
                    <a href="@route('index.php?option=com_engage&view=Comments&asset_title=' . urlencode($meta['title']))&limitstart=0"
                       class="engage-filter-by-content">
                        <span aria-hidden="true">
                            {{ $numComments }}
                        </span>
                        <span class="engage-sr-only">
                            @plural('COM_ENGAGE_COMMENTS_LBL_NUMCOMMENTS', $numComments)
                        </span>
                    </a>
                </div>
            </td>
            <td>
                {{ (new FOF30\Date\Date($item->created_on))->format(\Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'), true, true) }}
            </td>
            <td>
                {{ \Akeeba\Engage\Admin\Helper\Grid::published($item->enabled, $i, '', $canChange) }}
            </td>
        </tr>
    @endforeach
@stop