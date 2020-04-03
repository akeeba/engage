<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die();

use FOF30\Container\Container;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */
class plgContentEngage extends CMSPlugin
{
	/**
	 * Should this plugin be allowed to run?
	 *
	 * If the runtime dependencies are not met the plugin effectively self-disables even if it's published. This
	 * prevents a WSOD should the user e.g. uninstall a library or the component without unpublishing the plugin first.
	 *
	 * @var  bool
	 */
	private $enabled = true;

	/**
	 * The Akeeba Engage component container
	 *
	 * @var  Container|null
	 */
	private $container;

	/**
	 * Constructor
	 *
	 * @param   object  &$subject  The object to observe
	 * @param   array    $config   An optional associative array of configuration settings.
	 *
	 * @return  void
	 */
	public function __construct(&$subject, $config = [])
	{
		if (!defined('FOF30_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof30/include.php'))
		{
			$this->enabled = false;
		}

		if (!ComponentHelper::isEnabled('com_engage'))
		{
			$this->enabled = false;
		}

		parent::__construct($subject, $config);
	}

	public function onContentPrepare(?string $context, &$row, Registry &$params, ?int $page = 0): bool
	{
		if (!$this->enabled)
		{
			return true;
		}

		if ($context !== 'com_content.article')
		{
			return true;
		}

		$container = $this->getContainer();
		$input     = $container->input;

		$input->set('asset_id', $row->asset_id);
		$input->set('task', 'browse');

		// Capture the output instead of pushing it to the browser
		try
		{
			@ob_start();

			$container->dispatcher->dispatch();

			$comments = @ob_get_contents();

			@ob_end_clean();
		}
		catch (Exception $e)
		{
			$comments = '';
		}

		$row->text .= $comments;

		return true;
	}

	private function getContainer(): Container
	{
		if (empty($this->container))
		{
			$this->container = Container::getInstance('com_engage', [
				'tempInstance' => true,
				'input'        => [
					'View' => 'Comments',
				],
			], 'site');
		}

		return $this->container;
	}
}