<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Engage\Admin\Model;

use DateInterval;
use Exception;
use FOF30\Container\Container;
use FOF30\Date\Date;
use FOF30\Model\DataModel\Collection as DataCollection;
use FOF30\Model\TreeModel;
use FOF30\Timer\Timer;
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
 * @property string      $user_agent        The User Agent string used to file the comment
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

			$dir = strtoupper($this->getState('filter_order_Dir', null, 'cmd'));

			if (!in_array($dir, ['ASC', 'DESC']))
			{
				$dir = 'ASC';
				$this->setState('filter_order_Dir', $dir);
			}

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
					$query->qn('node.lft') . ' ' . $dir
				);

		}

		return $query;
	}

	/** @inheritDoc */
	public function count()
	{
		if (!$this->treeNestedGet)
		{
			return parent::count();
		}

		// Get a "count all" query
		$db = $this->getDbo();

		$innerQuery = $this->buildQuery(true);
		$innerQuery->clear('select')->clear('order')->select($db->qn('node.lft'));

		// Run the "before build query" hook and behaviours
		$this->triggerEvent('onBuildCountQuery', [&$innerQuery]);

		$outerQuery = $db->getQuery(true)
			->select('COUNT(*)')
			->from('(' . $innerQuery . ') AS ' . $db->qn('a'));


		$total = $db->setQuery($outerQuery)->loadResult();

		return $total;

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

	/**
	 * Returns a URL for the user's avatar image, empty if no avatar is available.
	 *
	 * @param   int  $size  Size of the avatar in pixels (avatars are meant to be square)
	 *
	 * @return  string  The avatar URL, empty if no avatar is available.
	 */
	public function getAvatarURL(int $size = 32): string
	{
		$platform = $this->container->platform;

		$platform->importPlugin('engage');

		$results = $platform->runPlugins('onEngageUserAvatarURL', [$this->getUser(), $size]);
		$results = array_filter($results, function ($x) {
			return is_string($x) && !empty($x);
		});

		if (empty($results))
		{
			return '';
		}

		return array_shift($results);
	}

	/**
	 * Returns the URL for the user's profile page, empty if no profile is available
	 *
	 * @return  string  The user's profile page, empty if no profile is available
	 */
	public function getProfileURL(): string
	{
		$platform = $this->container->platform;

		$platform->importPlugin('engage');

		$results = $platform->runPlugins('onEngageUserProfileURL', [$this->getUser()]);
		$results = array_filter($results, function ($x) {
			return is_string($x) && !empty($x);
		});

		if (empty($results))
		{
			return '';
		}

		return array_shift($results);
	}

	/**
	 * Pre-process the record data before saving them to the database.
	 *
	 * Used to remove virtual fields which do not exist in the table.
	 *
	 * @return  array  The pre-processed data
	 */
	public function recordDataToDatabaseData()
	{
		$ret = parent::recordDataToDatabaseData();

		if (array_key_exists('depth', $ret))
		{
			unset($ret['depth']);
		}

		return $ret;
	}

	/**
	 * Automatically deletes obsolete spam messages older than this many days, using an upper execution time limit.
	 *
	 * If there are numerous spam messages this method will delete at least one chunk (100 messages). It will keep on
	 * going until the maxExecutionTime limit is reached or exceeded; or until there are no more spam messages left to
	 * delete.
	 *
	 * Use $maxExecutionTime=0 to only delete up to 100 messages.
	 *
	 * @param   int  $maxDays           Spam older than this many days will be automatically deleted
	 * @param   int  $maxExecutionTime  Maximum time to spend cleaning obsolete spam
	 *
	 * @return  int  Total number of spam messages deleted.
	 */
	public function cleanSpam(int $maxDays = 15, int $maxExecutionTime = 1): int
	{
		$timer   = new Timer($maxExecutionTime, 100);
		$deleted = 0;

		do
		{
			$deletedNow = $this->cleanSpamChunk($maxDays);
			$deleted    += $deletedNow;

			if ($deletedNow === 0)
			{
				break;
			}
		} while ($timer->getTimeLeft() > 0.01);

		return $deleted;
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
	 * Automatically deletes up to 100 spam messages which are older than this many days.
	 *
	 * @param   int  $maxDays
	 *
	 * @return  int  Number of spam comments deleted
	 */
	private function cleanSpamChunk(int $maxDays = 15): int
	{
		$maxDays = max(1, $maxDays);

		try
		{
			$interval     = new DateInterval(sprintf('P%uD', $maxDays));
			$earliestDate = (new Date())->sub($interval);
		}
		catch (Exception $e)
		{
			return 0;
		}

		/** @var DataCollection $obsoleteSpam */
		$obsoleteSpam = $this
			->getClone()
			->enabled(-3)
			->where('created_on', 'lt', $earliestDate->toSql())
			->get(false, 0, 100);

		$numComments = $obsoleteSpam->count();

		$obsoleteSpam->delete();

		return $numComments;
	}

	/**
	 * Returns the user ID given their email address.
	 *
	 * @param   string  $email  The email to check
	 *
	 * @return  int|null  The corresponding user ID, null if no user matches this email address
	 */
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
}