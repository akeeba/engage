<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Mixin\TriggerEventTrait;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Document\HtmlDocument;
use Throwable;

class Dispatcher extends ComponentDispatcher
{
	use TriggerEventTrait;

	/**
	 * Keys of common media files to load.
	 *
	 * The prefixes of each string can be preset, style or script.
	 *
	 * @var   string[]
	 * @since 3.0.0
	 */
	protected $commonMediaKeys = ['preset:com_engage.backend'];

	/**
	 * The default controller (and view), if none is specified in the request.
	 *
	 * @var   string
	 * @since 3.0.0
	 */
	protected $defaultController = 'comments';

	/**
	 * Maps old versions' view names to the current view names.
	 *
	 * IMPORTANT! The keys must be in ALL LOWERCASE.
	 *
	 * @var   array
	 * @since 3.0.0
	 */
	protected $viewMap = [];

	/**
	 * Should I use the error handler to catch and report errors?
	 *
	 * @var   bool
	 * @since 3.0.0
	 */
	private $useErrorHandler = true;

	/** @inheritdoc */
	public function dispatch()
	{
		// Check the minimum supported PHP version
		$minPHPVersion = '7.4.0';
		$softwareName  = 'Akeeba Engage';
		$silentResults = $this->app->isClient('site');

		if (version_compare(PHP_VERSION, $minPHPVersion, 'lt'))
		{
			die(sprintf('%s requires PHP %s or later.', $softwareName, $minPHPVersion));
		}

		try
		{
			$this->triggerEvent('onBeforeDispatch');

			parent::dispatch();

			// This will only execute if there is no redirection set by the Controller
			$this->triggerEvent('onAfterDispatch');
		}
		catch (Throwable $e)
		{
			$title = 'Akeeba Engage';
			$isPro = false;

			// Frontend: forwards errors 401, 403 and 404 to Joomla
			if (in_array($e->getCode(), [401, 403, 404]) && $this->app->isClient('site'))
			{
				throw $e;
			}

			if (!$this->useErrorHandler || !(include_once JPATH_ADMINISTRATOR . '/components/com_engage/tmpl/common/errorhandler.php'))
			{
				throw $e;
			}
		}
	}

	/**
	 * Set the flag to use the error handler
	 *
	 * @param   bool  $useErrorHandler
	 *
	 * @return  self
	 */
	public function setUseErrorHandler(bool $useErrorHandler): self
	{
		$this->useErrorHandler = $useErrorHandler;

		return $this;
	}

	/**
	 * Applies the view and controller to the input object communicated to the MVC objects.
	 *
	 * If we have a controller without view or just a task=controllerName.taskName we populate the view to make things
	 * easier and more consistent for us to handle.
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	protected function applyViewAndController(): void
	{
		$controller = $this->input->getCmd('controller', null);
		$view       = $this->input->getCmd('view', null);
		$task       = $this->input->getCmd('task', 'main');

		if (strpos($task, '.') !== false)
		{
			// Explode the controller.task command.
			[$controller, $task] = explode('.', $task);
		}

		if (empty($controller) && empty($view))
		{
			$controller = $this->defaultController;
			$view       = $this->defaultController;
		}
		elseif (empty($controller) && !empty($view))
		{
			$view       = $this->mapView($view);
			$controller = $view;
		}
		elseif (!empty($controller) && empty($view))
		{
			$view = $controller;
		}

		$controller = strtolower($controller);
		$view       = strtolower($view);

		$this->input->set('view', $view);
		$this->input->set('controller', $controller);
		$this->input->set('task', $task);
	}

	/**
	 * Preload common static media files (CSS, JS) used throughout this side of the application.
	 *
	 * @return  void
	 * @since   3.0.0
	 * @internal
	 */
	final protected function loadCommonStaticMedia(): void
	{
		// Make sure we run under a CMS application
		if (!($this->app instanceof CMSApplication))
		{
			return;
		}

		// Make sure the document is HTML
		$document = $this->app->getDocument();

		if (!($document instanceof HtmlDocument))
		{
			return;
		}

		// Finally, load our 'common' backend preset
		$webAssetManager = $document->getWebAssetManager();

		foreach ($this->commonMediaKeys as $keyString)
		{
			[$prefix, $key] = explode(':', $keyString, 2);

			switch ($prefix)
			{
				case 'preset':
					$webAssetManager->usePreset($key);
					break;

				case 'style':
					$webAssetManager->useStyle($key);
					break;

				case 'script':
					$webAssetManager->useScript($key);
					break;
			}
		}
	}

	/**
	 * Loads the language files for this component.
	 *
	 * Always loads the backend translation file. In the site, CLI and API applications it also loads the frontend
	 * language file and the current application's language file.
	 *
	 * @return  void
	 * @since   3.0.0
	 * @internal
	 */
	final protected function loadLanguage(): void
	{
		$jLang = $this->app->getLanguage();

		// Always load the admin language files
		$jLang->load($this->option, JPATH_ADMINISTRATOR);

		$isAdmin = $this->app->isClient('administrator');
		$isSite  = $this->app->isClient('site');

		// Load the language file specific to the current application. Only applies to site, CLI and API applications.
		if (!$isAdmin)
		{
			$jLang->load($this->option, JPATH_BASE);
		}

		// Load the frontend language files in the CLI and API applications.
		if (!$isAdmin && !$isSite)
		{
			$jLang->load($this->option, JPATH_SITE);
		}
	}

	/**
	 * Loads the version.php file. If it doesn't exist, fakes the version constants to simulate a dev release.
	 *
	 * @return  void
	 * @since   3.0.0
	 * @internal
	 */
	final protected function loadVersion(): void
	{
		$filePath = JPATH_ADMINISTRATOR . '/components/com_engage/version.php';

		if (@file_exists($filePath) && is_file($filePath))
		{
			include_once $filePath;
		}

		if (!defined('AKENGAGE_VERSION'))
		{
			define('AKENGAGE_VERSION', 'dev');
		}

		if (!defined('AKENGAGE_DATE'))
		{
			define('AKENGAGE_DATE', gmdate('Y-m-d'));
		}
	}

	/**
	 * Maps an old view name to a new view name
	 *
	 * @param   string  $view
	 *
	 * @return  string
	 *
	 * @since   3.0.0
	 * @internal
	 */
	protected function mapView(string $view): string
	{
		$view = strtolower($view);

		return $this->viewMap[$view] ?? $view;
	}

	/**
	 * Executes before dispatching a request made to this component
	 *
	 * @return  void
	 * @since   3.0.0
	 */
	protected function onBeforeDispatch(): void
	{
		$this->loadLanguage();

		$this->applyViewAndController();

		$this->loadVersion();

		$this->loadCommonStaticMedia();
	}
}