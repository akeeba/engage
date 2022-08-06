<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Joomla\Module\EngageLatest\Site\Dispatcher;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Extension\ModuleInterface;
use Joomla\Input\Input;
use Joomla\Module\EngageLatest\Site\Helper\EngageLatestHelper;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

class Dispatcher extends AbstractModuleDispatcher
{
	/**
	 * The module extension. Used to fetch the module helper.
	 *
	 * @var   ModuleInterface|null
	 * @since 3.0.9
	 */
	private $moduleExtension;

	/** @inheritdoc */
	public function __construct(\stdClass $module, CMSApplicationInterface $app, Input $input)
	{
		parent::__construct($module, $app, $input);

		$this->moduleExtension = $this->app->bootModule('mod_engage_latest', 'site');
	}

	/** @inheritdoc */
	protected function getLayoutData()
	{
		/** @var EngageLatestHelper $helper */
		$helper    = $this->moduleExtension->getHelper('EngageLatestHelper');
		$hasEngage = $helper->hasEngage();

		if ($hasEngage)
		{
			$this->app->getLanguage()->load('com_engage', JPATH_SITE);
		}

		$params = new Registry($this->module->params);

		return array_merge(parent::getLayoutData(), [
			'hasEngage'          => $hasEngage,
			'comments'           => $helper->getLatestComments($params->get('count', 10)),
			'show_title'         => (int) ($params->get('show_title', 1)) === 1,
			'link_title'         => (int) ($params->get('link_title', 0)) === 1,
			'show_count'         => (int) ($params->get('show_count', 1)) === 1,
			'excerpt'            => (int) ($params->get('excerpt', 1)) === 1,
			'excerpt_words'      => (int) ($params->get('excerpt_words', 50)),
			'excerpt_characters' => (int) ($params->get('excerpt_characters', 350)),
		]);
	}

}