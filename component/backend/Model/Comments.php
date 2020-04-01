<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Model;

use FOF30\Container\Container;
use FOF30\Model\TreeModel;
use JDatabaseQuery;
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

		if (!empty($this->name) && !empty($this->email))
		{
			$this->created_by = 0;
		}

		if (empty($this->name) || empty($this->email))
		{
			$this->name  = null;
			$this->email = null;
		}

		if (empty($this->created_by) && empty($this->name) && empty($this->email))
		{
			throw new RuntimeException("You need to provide your name and email address to file a comment.");
		}

		if (empty($this->hash))
		{
			$this->hash = $this->createHash();
		}
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