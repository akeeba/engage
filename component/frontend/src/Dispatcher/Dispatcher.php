<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\Dispatcher;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Dispatcher\Dispatcher as AdminDispatcher;
use Joomla\CMS\Component\ComponentHelper;

class Dispatcher extends AdminDispatcher
{
	/**
	 * Keys of common media files to load.
	 *
	 * The prefixes of each string can be preset, style or script.
	 *
	 * @var   string[]
	 * @since 3.0.0
	 */
	protected $commonMediaKeys = ['preset:com_engage.frontend'];

	protected function onBeforeDispatch(): void
	{
		$cParams = ComponentHelper::getParams('com_engage');

		if ($cParams->get('loadCustomCss', 0) == 1)
		{
			$this->commonMediaKeys[] = 'style:com_engage.comments';
		}

		parent::onBeforeDispatch();
	}

	protected function applyViewAndController(): void
	{
		parent::applyViewAndController();

		$controller = $this->input->get('controller', null);

		if (!empty($controller))
		{
			$this->input->set('view', null);
		}
	}


}