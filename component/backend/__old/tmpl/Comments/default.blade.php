<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Engage\Admin\Helper\Format;use Akeeba\Engage\Site\Helper\Filter;use Akeeba\Engage\Site\Helper\Meta;use Joomla\CMS\Environment\Browser;use Joomla\CMS\Language\Text;use Joomla\CMS\Uri\Uri;

/** @var \Akeeba\Engage\Admin\View\Comments\Html $this */

Filter::includeHTMLPurifier();

$config = HTMLPurifier_Config::createDefault();
$config->set('Core.Encoding', 'UTF-8');
$config->set('HTML.Doctype', 'HTML 4.01 Transitional');
$config->set('Cache.SerializerPath', Filter::getCachePath());
$config->set('HTML.Allowed', 'p,b,a[href],i,u,strong,em,small,big,ul,ol,li,br,img[src],img[width],img[height],code,pre,blockquote');
$purifier = new HTMLPurifier($config);

/**
 * Get the asset ID filter value.
 *
 * It can either be an integer (when filtering by asset ID) or an array (when we are telling the model to filter by
 * non-zero entries). In the latter case I need to use an empty string to prevent PHP notices which can break the
 * page display and cause weird issues with no comments being listed.
 */
$filterAssetId = $this->getModel()->getState('asset_id', null) ?? '';
$filterAssetId = is_array($filterAssetId) ? '' : $filterAssetId;

$this->showBrowseOrdering = false;
$this->showBrowseOrderBy  = false;
?>
@extends('any:lib_fof40/Common/browse')

@section('browse-page-top')
    {{-- Old PHP version reminder --}}
    @include('admin:com_engage/Common/phpversion_warning', [
        'softwareName'  => 'Akeeba Engage',
        'minPHPVersion' => '7.2.0',
    ])
@stop

@section('browse-ordering')
    {{-- Table ordering --}}
    @jhtml('FEFHelp.browse.orderjs', $this->lists->order)

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
        @selectfilter('enabled', \Akeeba\Engage\Admin\Helper\Select::published(), 'JENABLED', ['id' => 'filter_published', 'class' => 'akeebaGridViewAutoSubmitOnChange'])
    </div>

    <div class="akeeba-filter-element akeeba-form-group">
        <button type="button" class="akeeba-btn--dark--small" id="comEngageResetFilters">
            <span class="akion-android-refresh" aria-hidden="true"></span>
            @lang('JSEARCH_RESET')
        </button>
    </div>

    <input type="hidden" name="asset_id" id="filter_asset_id" value="<?= $filterAssetId ?>">
@stop

@section('browse-table-header')
    <tr>
        <th width="20px">
            @jhtml('FEFHelp.browse.checkall')
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
		$meta = Meta::getAssetAccessMeta($item->asset_id, false);
		$numComments = Meta::getNumCommentsForAsset($item->asset_id);
		$ipLookupUrl = Format::getIPLookupURL($item->ip);
		$jBrowser = Browser::getInstance($item->user_agent);
		?>
        <tr>
            <td>
                @jhtml('FEFHelp.browse.id', ++$i, $item->getId())
            </td>
            <td>
                @include('admin:com_engage/Common/ShowUser', ['item' => $item, 'showLink' => false, 'avatarSize' => '32'])
                <div style="clear:both"></div>
                <div>
                    <span class="engage_user_agent--{{ $jBrowser->isMobile() ? 'mobile' : 'desktop' }}">
                        <span class="hasTooltip"
                              title="@lang('COM_ENGAGE_COMMENTS_LBL_BROWSERTYPE_' . ($jBrowser->isMobile() ? 'mobile' : 'desktop'))">
                            <span class="akion-{{ $jBrowser->isMobile() ? 'iphone' : 'android-desktop' }}" aria-hidden="true"></span>
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
                @if (!empty($item->getFieldValue('parent_id')))
                <?php
                    try
                    {
                        $parent     = $item->getParent();
                        $limitStart = Meta::getLimitStartForComment($parent, null, $user->authorise('core.edit.state', 'com_engage'));
                        $public_uri = new Uri($meta['public_url']);
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
                {{ $purifier->purify(\Akeeba\Engage\Admin\Helper\Format::processCommentTextForDisplay($item->body)) }}
                <div class="engage-edit-link">
                    <a href="@route('index.php?option=com_engage&view=Comments&task=edit&id=' . $item->getId())">
                        @lang('JGLOBAL_EDIT')
                    </a>
                </div>
            </td>
            <td>
                @unless ($meta['type'] === 'unknown')
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
                @else
                <span class="akeeba-label--red"><?= Text::_('COM_ENGAGE_COMMENTS_LBL_INVALIDORDELETED') ?></span>
                <div class="engage-content-comments-filter">
                    <a href="@route('index.php?option=com_engage&view=Comments&asset_id=' . $item->asset_id)&limitstart=0"
                       class="engage-filter-by-content">
                    <span aria-hidden="true">
                        {{ $numComments }}
                    </span>
                        <span class="engage-sr-only">
                        @plural('COM_ENGAGE_COMMENTS_LBL_NUMCOMMENTS', $numComments)
                    </span>
                    </a>
                </div>
                @endunless
            </td>
            <td>
                {{ (new FOF40\Date\Date($item->created_on))->format(\Joomla\CMS\Language\Text::_('DATE_FORMAT_LC2'), true, true) }}
            </td>
            <td>
                {{ \Akeeba\Engage\Admin\Helper\Grid::published($item->enabled, $i, '', $canChange) }}
            </td>
        </tr>
    @endforeach
@stop
