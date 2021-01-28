<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\View\Comments;

defined('_JEXEC') or die;

use Akeeba\Engage\Admin\Model\Comments;
use FOF40\View\DataView\Html as HtmlView;
use Joomla\CMS\Language\Text;

class Html extends HtmlView
{
	private $commentsPerAsset = [];

	public function onBeforeBrowse()
	{
		$this->addJavascriptFile('media://com_engage/js/system.js');
		$this->addJavascriptFile('media://com_engage/js/backend.js');

		/** @var Comments $model */
		$model = $this->getModel();

		$model->savestate(true);

		if (!$model->getState('asset_id', null))
		{
			$model->where('asset_id', 'ne', '0');
		}

		$model->orderBy('created_on', 'DESC');

		parent::onBeforeBrowse();

		$this->lists->sortFields = [
			'engage_comment_id' => Text::_('JGLOBAL_FIELD_ID_LABEL'),
			'asset_id'          => Text::_('COM_ENGAGE_COMMENT_FIELD_ASSET_ID'),
			'body'              => Text::_('COM_ENGAGE_COMMENT_FIELD_BODY'),
			'created_on'        => Text::_('JGLOBAL_FIELD_CREATED_LABEL'),
			'modified_on'       => Text::_('JGLOBAL_FIELD_MODIFIED_LABEL'),
		];

	}
}
