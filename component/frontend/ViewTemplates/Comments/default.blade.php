<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

?>
<section class="akengage-outer-container">
    <h3 class="akengage-title">
        @plural('COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS', $this->getItemCount())
    </h3>

    <div class="akengage-list-container">
    @if ($this->getItemCount())
        @include('any:com_engage/Comments/default_list')
    @endif
    </div>

    <div class="akengage-pagination">
        {{ $this->getPagination()->getListFooter() }}
    </div>

    <div class="akengage-comment-form" id="akengage-comment-form">
    {{-- TODO -- Comment form --}}
    </div>
</section>