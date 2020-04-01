<?php
/** @var \Akeeba\Engage\Site\View\Comments\Html $this */

?>
<pre>
@foreach($this->items as $item)
{{ ($item->depth > 1) ? str_repeat('  ', $item->depth - 1) : '' }}{{ $item->getId() }} {{ $item->created_on }} {{ $item->depth }}
@endforeach
</pre>
