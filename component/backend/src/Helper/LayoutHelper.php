<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Helper;

use Joomla\CMS\Layout\FileLayout;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

/**
 * A replacement to Joomla's LayoutHelper.
 *
 * Unlike Joomla's unhelpful helper, providing a custom base path will NOT make it impossible to override the layout
 * in your template. Obviously Joomla's helper is only meant to be helpful to the core, not to 3PDs. Sigh.
 *
 * @since  3.2.0
 */
class LayoutHelper
{
	/**
	 * A default base path that will be used if none is provided when calling the render method.
	 *
	 * @var    string
	 * @since  3.2.0
	 */
	public static string $defaultBasePath = '';

	/**
	 * Method to render a layout with debug info
	 *
	 * @param   string               $layoutFile   Dot separated path to the layout file, relative to base path
	 * @param   mixed                $displayData  Object which properties are used inside the layout file to build
	 *                                             displayed output
	 * @param   string               $basePath     Base path to use when loading layout files
	 * @param   Registry|array|null  $options      Optional custom options to load. Registry or array format
	 *
	 * @return  string
	 *
	 * @since   3.2.0
	 */
	public static function debug(string $layoutFile, $displayData = null, string $basePath = '', $options = null)
	{
		$basePath = empty($basePath) ? self::$defaultBasePath : $basePath;

		// Make sure we send null to FileLayout if no path set
		$basePath = empty($basePath) ? null : $basePath;
		$layout   = self::getFileLayout($layoutFile, $basePath, $options);

		return $layout->debug($displayData);
	}

	/**
	 * Method to render the layout.
	 *
	 * @param   string               $layoutFile   Dot separated path to the layout file, relative to base path
	 * @param   mixed                $displayData  Object which properties are used inside the layout file to build
	 *                                             displayed output
	 * @param   string               $basePath     Base path to use when loading layout files
	 * @param   Registry|array|null  $options      Optional custom options to load. Registry or array format
	 *
	 * @return  string
	 *
	 * @since   3.2.0
	 */
	public static function render(string $layoutFile, $displayData = null, string $basePath = '', $options = null)
	{
		$basePath = empty($basePath) ? self::$defaultBasePath : $basePath;

		// Make sure we send null to FileLayout if no path set
		$basePath = empty($basePath) ? null : $basePath;
		$layout   = self::getFileLayout($layoutFile, $basePath, $options);

		return $layout->render($displayData);
	}

	/**
	 * Get a FileLayout object instance.
	 *
	 * @param   string               $layoutFile  Dot separated path to the layout file, relative to base path
	 * @param   string|null          $basePath    Base path to use when loading layout files
	 * @param   Registry|array|null  $options     Optional custom options to load. Registry or array format
	 *
	 * @return  FileLayout
	 *
	 * @since   3.2.0
	 */
	private static function getFileLayout(string $layoutFile, ?string $basePath = '', $options = null)
	{
		$layoutFile = new FileLayout($layoutFile, null, $options);

		if (empty($basePath))
		{
			return $layoutFile;
		}

		$paths   = $layoutFile->getIncludePaths();
		$paths[] = $basePath;

		$layoutFile->clearIncludePaths();
		$layoutFile->addIncludePaths($paths);

		return $layoutFile;
	}
}