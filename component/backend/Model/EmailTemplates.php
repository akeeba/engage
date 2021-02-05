<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Model;

use Exception;
use FOF30\Container\Container;
use FOF30\Model\DataModel;
use FOF30\Model\Mixin\Assertions;

/**
 * Email templates model
 * @package Akeeba\Engage\Admin\Model
 *
 * @property int    $engage_emailtemplate_id Primary key
 * @property string $key                     Email template type
 * @property string $language                Language to use this template for. '*' makes it a fallback (all lang.)
 * @property string $subject                 The email subject
 * @property string $template                The email body
 *
 * Filters:
 *
 * @method $this key($key) Filter by email type
 * @method $this language($language) Filter by language
 * @method $this subject($subject) Search by email subject
 * @method $this template($template) Search by email body
 */
class EmailTemplates extends DataModel
{
	use Assertions;

	public function __construct(Container $container, array $config = [])
	{
		parent::__construct($container, $config);

		$this->addBehaviour('Filters');
	}

	public function check()
	{
		$this->assertNotEmpty($this->key, 'COM_ENGAGE_EMAILTEMPLATES_ERR_KEY');

		parent::check();
	}

	/**
	 * Get template key from an id
	 *
	 * @param   int  $id  Template ID
	 *
	 * @return  string|null Template key; NULL if the ID is not found.
	 */
	public function getKey($id): ?string
	{
		$model = $this->tmpInstance();

		try
		{
			return $model->findOrFail($id)->getFieldValue('key', null);
		}
		catch (Exception $e)
		{
			return null;
		}
	}
}
