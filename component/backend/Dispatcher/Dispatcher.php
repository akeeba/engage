<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Dispatcher;

defined('_JEXEC') or die;

use FOF30\Dispatcher\Dispatcher as FOFDispatcher;
use Joomla\CMS\Language\Text;
use RuntimeException;

class Dispatcher extends FOFDispatcher
{
	use ComponentVersionAware, FOFLanguageAware;

	/** @var   string  The name of the default view, in case none is specified */
	public $defaultView = 'Comments';

	public function onBeforeDispatch(): void
	{
		// Load the FOF language strings
		$this->onBeforeDispatchLoadFOFLanguage();

		// Does the user have adequate permissions to access our component?
		if (!$this->container->platform->authorise('core.manage', $this->container->componentName))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 404);
		}

		// FEF Renderer options. Used to load the common CSS file.
		$darkMode  = $this->container->params->get('dark_mode', -1);
		$customCss = ['media://com_engage/css/backend.min.css'];

		if ($darkMode != 0)
		{
			$customCss[] = 'media://com_engage/css/backend_dark.min.css';
		}

		$this->container->renderer->setOptions([
			'load_fef'      => true,
			'fef_reset'     => true,
			'custom_css' => implode(",", $customCss),
			'fef_dark'   => $darkMode,
			// Render submenus as drop-down navigation bars powered by Bootstrap
			'linkbar_style' => 'classic',
		]);

		// Load the version.php file and set up the mediaVersion container key
		$this->onBeforeDispatchLoadComponentVersion();
	}
}