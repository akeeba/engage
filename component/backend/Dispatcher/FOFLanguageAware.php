<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Dispatcher;


trait FOFLanguageAware
{
	protected function onBeforeDispatchLoadFOFLanguage(): void
	{
		// Load the FOF language
		$lang = $this->container->platform->getLanguage();
		$lang->load('lib_fof40', JPATH_ADMINISTRATOR, 'en-GB', true, true);
		$lang->load('lib_fof40', JPATH_ADMINISTRATOR, null, true, false);
	}
}
