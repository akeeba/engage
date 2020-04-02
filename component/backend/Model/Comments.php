<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Model;

use Exception;
use FOF30\Container\Container;
use FOF30\Model\TreeModel;
use JDatabaseQuery;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use RuntimeException;

/**
 * Comments model
 * @package Akeeba\Engage\Admin\Model
 *
 * @property int         $engage_comment_id Primary key
 * @property int         $asset_id          Asset ID the comment belongs to
 * @property string      $body              Comment body
 * @property string|null $name              Commenter's name
 * @property string|null $email             Commenter's email address
 * @property string      $ip                IP address used to file the comment
 * @property int         $enabled           Is this comment published?
 *
 * Filters:
 *
 * @method $this asset_id(int $asset_id) Filter by asset
 * @method $this nested(bool $nested) Should I return nested results by default?
 * @method $this commenter(string $partial) Partial email or name to search a commenter for
 * @method $this ip(string $ip) Search by IP address
 * @method $this enabled(int $enabled) Search by published / unpublished comment
 */
class Comments extends TreeModel
{
	/** @inheritDoc */
	public function __construct(Container $container = null, array $config = [])
	{
		$config['behaviours'] = (isset($config['behaviours']) && is_array($config['behaviours'])) ? $config['behaviours'] : [];

		if (!in_array('filters', $config['behaviours']))
		{
			$config['behaviours'][] = 'filters';
		}

		parent::__construct($container, $config);

		$this->addKnownField('depth', 0);
	}

	/** @inheritDoc */
	public function check()
	{
		parent::check();

		// Make sure we have EITHER a user OR both an email and full name
		$name = $this->getFieldValue('name');

		if (!empty($name) && !empty($this->email))
		{
			$this->created_by = 0;
		}

		if (empty($name) || empty($this->email))
		{
			$this->setFieldValue('name', null);
			$this->email = null;
		}

		if (empty($this->created_by) && empty($name) && empty($this->email))
		{
			throw new RuntimeException(Text::_('COM_ENGAGE_COMMENTS_ERR_NO_NAME_OR_EMAIL'));
		}

		// If we have a guest user, make sure we don't have another user with the same email address
		if (($this->created_by <= 0) && !empty($this->getUserIdByEmail($this->email)))
		{
			throw new RuntimeException(Text::sprintf('COM_ENGAGE_COMMENTS_ERR_EMAIL_IN_USE', $this->email));
		}

		// Make sure we have a hash for the comment record
		if (empty($this->hash))
		{
			$this->hash = $this->createHash();
		}
	}

	/**
	 * Overrides the buildQuery to return depth information when we're scoping a specific asset.
	 *
	 * @param   bool  $overrideLimits  True when we're overriding the query limits
	 *
	 * @return  JDatabaseQuery
	 */
	public function buildQuery($overrideLimits = false)
	{
		if ($this->treeNestedGet)
		{
			$this->setBehaviorParam('tableAlias', 'node');
		}

		$query = parent::buildQuery($overrideLimits);

		if ($this->treeNestedGet)
		{
			// This trick allows us to not list each field manually
			$allFields = array_keys($this->knownFields);
			$allFields = array_filter($allFields, function ($x) {
				return !in_array($x, ['lft', 'depth']);
			});

			$query
				->clear('select')
				->select(array_merge(array_map([$query, 'qn'], array_map(function ($x) {
					return "node.$x";
				}, $allFields)), [
					$query->qn('node.lft'),
					sprintf('COUNT(%s) - 1', $query->qn('parent.engage_comment_id')) . ' AS ' . $query->qn('depth'),
				]))
				->clear('group')
				->group(array_merge([
					$query->qn('node.lft'),
				], array_map([$query, 'qn'], array_map(function ($x) {
					return "node.$x";
				}, $allFields))))
				->clear('order')
				->order(
					$query->qn('node.lft') . ' ASC'
				);

		}

		return $query;
	}

	/**
	 * get() will return the comment tree of the specified asset, in infinite depth.
	 *
	 * @param   int  $asset_id  The asset ID to scope the tree for
	 *
	 * @return  void
	 */
	public function scopeAssetCommentTree(int $asset_id): void
	{
		$this->scopeNonRootNodes();
		$this->where('asset_id', '=', $asset_id);
	}

	/**
	 * Return a Joomla user object for the user that filed the comment.
	 *
	 * If the comment was not filed by a logged in user a guest record with the correct name and email is filed instead.
	 *
	 * @return  User
	 */
	public function getUser(): User
	{
		if ($this->created_by)
		{
			return $this->container->platform->getUser($this->created_by);
		}

		$user        = $this->container->platform->getUser(0);
		$user->name  = $this->getFieldValue('name');
		$user->email = $this->email;

		return $user;
	}

	public function getAvatarURL(int $size = 32): string
	{
		$hash = md5(strtolower(trim($this->getUser()->email)));

		return 'https://www.gravatar.com/avatar/' . $hash . '?s=' . $size;
	}

	public function getProfileURL(): string
	{
		$hash = md5(strtolower(trim($this->getUser()->email)));

		return 'https://www.gravatar.com/' . $hash;
	}

	/**
	 * Binds the 'depth' parameter in a meaningful way for the TreeModel
	 *
	 * @param   array  $data  The data array being bound to the object
	 *
	 * @return  void
	 */
	protected function onBeforeBind(array &$data)
	{
		if (!isset($data['depth']))
		{
			return;
		}

		$this->treeDepth = $data['depth'];
	}

	private function getUserIdByEmail(string $email): ?int
	{
		$db = $this->getDbo();
		$q  = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__users'))
			->where($db->qn('email') . ' = ' . $db->q($email));

		try
		{
			return $db->setQuery($q)->loadResult();
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	/**
	 * get() will return all descendants of the root node (even subtrees of subtrees!) but not the root.
	 *
	 * @return  void
	 */
	private function scopeNonRootNodes(): void
	{
		$this->treeNestedGet = true;

		$db = $this->getDbo();

		$fldLft = $db->qn($this->getFieldAlias('lft'));
		$fldRgt = $db->qn($this->getFieldAlias('rgt'));

		$this->whereRaw($db->qn('node') . '.' . $fldLft . ' >= ' . $db->qn('parent') . '.' . $fldLft);
		$this->whereRaw($db->qn('node') . '.' . $fldLft . ' <= ' . $db->qn('parent') . '.' . $fldRgt);
	}

	/**
	 * Create a hash for a new record.
	 *
	 * This is necessary since we do not have a 'slug' column in the table.
	 *
	 * @return  string
	 */
	private function createHash(): string
	{
		$name  = $this->name;
		$email = $this->email;

		if (empty($name) || empty($email))
		{
			if ($this->created_by)
			{
				$jUser = $this->container->platform->getUser($this->created_by);
				$name  = $jUser->name;
				$email = $jUser->email;
			}
		}

		if (empty($name) || empty($email))
		{
			$name  = 'No Name';
			$email = 'nobody@localhost';
		}

		return sha1($this->asset_id . microtime(false) . $name . $email);
	}
}