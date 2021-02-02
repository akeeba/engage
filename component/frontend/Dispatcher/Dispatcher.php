<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Engage\Admin\Dispatcher\ComponentVersionAware;
use Akeeba\Engage\Admin\Dispatcher\FOFLanguageAware;
use FOF40\Dispatcher\Dispatcher as FOFDispatcher;

class Dispatcher extends FOFDispatcher
{
	use ComponentVersionAware, FOFLanguageAware;

	public function onBeforeDispatch(): void
	{
		// Load the FOF language strings
		$this->onBeforeDispatchLoadFOFLanguage();

		// Load the version.php file and set up the mediaVersion container key
		$this->onBeforeDispatchLoadComponentVersion();

		$darkMode  = $this->container->params->get('dark_mode_frontend', -1);
		$customCss = 'media://com_engage/css/comments.css';

		if ($darkMode != 0)
		{
			$customCss .= ', media://com_engage/css/comments_dark.css';
		}

		$this->container->renderer->setOptions([
			'custom_css'          => $customCss,
			'load_fef'            => 0,
			'load_fef_js'         => 1,
			'load_fef_js_minimal' => 1,
		]);

		// Load the Akeeba FEF JavaScript Framework
		//$this->loadMinimalFEFJavaScriptFramework();
	}

	private function loadMinimalFEFJavaScriptFramework()
	{
		$helperFile = JPATH_SITE . '/media/fef/fef.php';

		if (!class_exists('AkeebaFEFHelper') && is_file($helperFile))
		{
			include_once $helperFile;
		}

		if (!class_exists('AkeebaFEFHelper'))
		{
			return;
		}

		\AkeebaFEFHelper::loadJSFramework(true);
	}
}
