<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Site\Model\Struct;

use ReflectionObject;
use ReflectionProperty;

/**
 * Structure LoadedTemplate
 *
 * @package Akeeba\Engage\Site\Model\Struct
 *
 * @property string|null $subject        The email subject loaded
 * @property string|null $template       The email body loaded
 * @property string|null $loadedLanguage The actual language we loaded (can be '*')
 *
 * @see     \Akeeba\Engage\Site\Helper\Email
 */
final class LoadedTemplate
{
	private $subject;

	private $template;

	private $loadedLanguage;

	public function __construct(array $params = [])
	{
		foreach ($params as $k => $v)
		{
			$this->__set($k, $v);
		}
	}

	public function toArray(): array
	{
		$ret       = [];
		$refObject = new ReflectionObject($this);

		foreach ($refObject->getProperties(ReflectionProperty::IS_PRIVATE) as $property)
		{
			$ret[$property->name] = $this->{$property->name};
		}

		return $ret;
	}

	public function __get($name)
	{
		if (!property_exists($this, $name))
		{
			return null;
		}

		return $this->{$name};
	}

	public function __set($name, $value)
	{
		if (!property_exists($this, $name))
		{
			return;
		}

		$this->{$name} = (string) $value;
	}


}
