<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Dispatcher;

defined('_JEXEC') or die;

trait ComponentVersionAware
{
	protected function onBeforeDispatchLoadComponentVersion(): void
	{
		// Make sure we have a version loaded
		@include_once($this->container->backEndPath . '/version.php');

		if (!defined('AKENGAGE_VERSION'))
		{
			define('AKENGAGE_PRO', 0);
			define('AKENGAGE_VERSION', 'dev');
			define('AKENGAGE_DATE', date('Y-m-d'));
		}

		// Create a media file versioning tag
		$this->container->mediaVersion = md5(AKENGAGE_VERSION . AKENGAGE_DATE);
	}
}
