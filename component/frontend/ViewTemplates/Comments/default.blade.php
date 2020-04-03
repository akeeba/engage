<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

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
        <div class="akengage-pagination-pages">
            {{ $this->getPagination()->getListFooter() }}
        </div>
    </div>

    @if($this->container->platform->getUser()->authorise('core.create', 'com_engage'))
        @include('any:com_engage/Comments/default_form')
    @endif
</section>