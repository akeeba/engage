<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Model;

defined('_JEXEC') or die();

use FOF30\Utils\Ip;

class Comments extends \Akeeba\Engage\Admin\Model\Comments
{
	public function check()
	{
		parent::check();

		// Add an IP address if none has been provided yet
		if (empty($this->getFieldValue('ip')))
		{
			$this->ip = Ip::getIp();
		}
	}
}