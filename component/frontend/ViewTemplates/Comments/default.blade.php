<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use Akeeba\Engage\Site\Helper\Meta;

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

$closedComments = Meta::areCommentsClosed($this->assetId);
$canComment     = $this->container->platform->getUser()->authorise('core.create', 'com_engage');
?>
<section class="akengage-outer-container">
    <h3 class="akengage-title">
        @plural($this->headerKey, $this->getItemCount(), $this->title)
    </h3>

    @modules('engage-before-comments')

    @if ($this->getItemCount())
        <div class="akengage-list-container">
            @include('any:com_engage/Comments/default_list')
        </div>

        @modules('engage-after-comments')

        <div class="akengage-pagination">
            <div class="akengage-pagination-pages">
                {{ $this->getPagination()->getListFooter() }}
            </div>
        </div>
    @endif

    @if($canComment && !$closedComments)
        @include('any:com_engage/Comments/default_form')
    @endif
</section>