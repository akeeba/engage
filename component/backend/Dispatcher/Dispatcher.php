<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Dispatcher;

defined('_JEXEC') or die;

use Exception;
use FOF30\Database\Installer;
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
		$this->assertPermissions();

		// FEF Renderer options. Used to load the common CSS file.
		$this->setupRenderOptions();

		// Load the version.php file and set up the mediaVersion container key
		$this->onBeforeDispatchLoadComponentVersion();

		// Check the database schema consistency
		if ($this->view == 'Comments')
		{
			$this->checkAndFixDatabase();
		}
	}

	/**
	 * Checks the database schema consistency and updates it if necessary.
	 *
	 * @return  void
	 */
	protected function checkAndFixDatabase(): void
	{
		try
		{
			$db          = $this->container->platform->getDbo();
			$dbInstaller = new Installer($db, $this->container->backEndPath . '/sql/xml');
			$dbInstaller->updateSchema();
		}
		catch (Exception $e)
		{
		}
	}

	/**
	 * Assert that the current user has adequate permissions to access the management interface
	 *
	 * @return  void
	 * @throws  RuntimeException
	 */
	protected function assertPermissions(): void
	{
		if ($this->container->platform->authorise('core.manage', $this->container->componentName))
		{
			return;
		}

		throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 404);
	}

	/**
	 * Setup the HTML renderer options
	 *
	 * @return  void
	 */
	protected function setupRenderOptions(): void
	{
		$darkMode  = $this->container->params->get('dark_mode_backend', -1);
		$customCss = ['media://com_engage/css/backend.css'];

		if ($darkMode != 0)
		{
			$customCss[] = 'media://com_engage/css/backend_dark.css';
		}

		$this->container->renderer->setOptions([
			'load_fef'      => true,
			'fef_reset'     => true,
			'custom_css'    => implode(",", $customCss),
			'fef_dark'      => $darkMode,
			// Render submenus as drop-down navigation bars powered by Bootstrap
			'linkbar_style' => 'classic',
		]);
	}

}
