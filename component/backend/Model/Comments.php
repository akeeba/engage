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
use FOF30\Model\DataModel;
use FOF30\Model\DataModel\Collection as DataCollection;
use FOF30\Timer\Timer;
use JDatabaseQuery;
use Joomla\CMS\Language\Text;
use Joomla\CMS\User\User;
use RuntimeException;

/**
 * Comments model
 * @package Akeeba\Engage\Admin\Model
 *
 * @property int           $engage_comment_id Primary key
 * @property int           $parent_id         Parent comment ID
 * @property int           $asset_id          Asset ID the comment belongs to
 * @property string        $body              Comment body
 * @property string|null   $name              Commenter's name
 * @property string|null   $email             Commenter's email address
 * @property string        $ip                IP address used to file the comment
 * @property string        $user_agent        The User Agent string used to file the comment
 * @property int           $enabled           Is this comment published?
 *
 * Filters:
 *
 * @method $this parent_id(int $parent_id) Filter by parent ID
 * @method $this asset_id(int $asset_id) Filter by asset
 * @method $this commenter(string $partial) Partial email or name to search a commenter for
 * @method $this ip(string $ip) Search by IP address
 * @method $this enabled(int $enabled) Search by published / unpublished comment
 * @method $this created_by(?int $created_by) Search by creted by user ID
 *
 * Relations:
 *
 * @property-read Comments $parent            Parent comment, if applicable
 *
 * Calculated columns:
 *
 * @property int           $depth             Comment level
 */
class Comments extends DataModel
{
	/**
	 * The number of tree-aware comments fetched by commentIDTreeSliceWithDepth
	 *
	 * @var   int
	 * @see   self::commentIDTreeSliceWithDepth
	 */
	private $treeAwareCount = null;

	/** @inheritDoc */
	public function __construct(Container $container = null, array $config = [])
	{
		$config['behaviours'] = (isset($config['behaviours']) && is_array($config['behaviours'])) ? $config['behaviours'] : [];

		if (!in_array('filters', $config['behaviours']))
		{
			$config['behaviours'][] = 'filters';
		}

		parent::__construct($container, $config);

		$this->hasOne('parent', 'Comments', 'parent_id', 'engage_comment_id');

		$this->addKnownField('depth', 0);
	}

	public function getTreeAwareCount(): int
	{
		if (is_null($this->treeAwareCount))
		{
			$this->commentIDTreeSliceWithDepth(0);
		}

		return $this->treeAwareCount ?? 0;
	}

	/**
	 * Tree-aware version of get(), returning a slice of the tree.
	 *
	 * @param   int  $start  Starting offset
	 * @param   int  $limit  Max number of items to retrieve
	 *
	 * @return  DataCollection
	 * @see     self::get
	 */
	public function commentTreeSlice(int $start, int $limit): DataCollection
	{
		// Get a slice of comment IDs and their depth in tree listing order
		$idsAndDepth = $this->commentIDTreeSliceWithDepth($start, $limit);

		// No IDs? No items!
		if (empty($idsAndDepth))
		{
			return new DataCollection();
		}

		// Get the comments with the IDs specified. They are NOT in order.
		$items = $this->tmpInstance()
			->where($this->getIdFieldName(), 'in', array_map('trim', array_keys($idsAndDepth)))
			->with(['parent'])
			->orderBy(null)
			->get(true);

		// Create a new collection
		$ret = new DataCollection();

		/**
		 * Distribute the items to the collection in the order they SHOULD appear.
		 *
		 * Magic trick: since the collection internally has an array consisting entirely of objects, creating a second
		 * collection referencing the same objects has minimal overhead. The reason is that objects are stored in arrays
		 * as references. Adding the same object to two arrays only adds its reference to the array, without copying the
		 * actual object. This helps keep memory pressure low while we are rearranging our items in an arbitrary order.
		 * Neat, huh?
		 */
		foreach ($idsAndDepth as $id => $depth)
		{
			$id = (int) $id;

			if (!$items->has($id))
			{
				continue;
			}

			// When adding the item to the collection we also need to set its level information.
			$ret->add($items->get($id)->bind([
				'depth' => $depth
			]));
		}

		return $ret;
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

		$results = $platform->runPlugins('onAkeebaEngageUserAvatarURL', [$this->getUser(), $size]);
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

		$results = $platform->runPlugins('onAkeebaEngageUserProfileURL', [$this->getUser()]);
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
	 * Automatically deletes obsolete spam comments older than this many days, using an upper execution time limit.
	 *
	 * If the $maxDays == 0 nothing is deleted; we return without querying the database.
	 *
	 * If there are numerous spam comments this method will delete at least one chunk (100 comments). It will keep on
	 * going until the maxExecutionTime limit is reached or exceeded; or until there are no more spam comments left to
	 * delete.
	 *
	 * Use $maxExecutionTime=0 to only delete up to 100 comments.
	 *
	 * @param   int  $maxDays           Spam older than this many days will be automatically deleted
	 * @param   int  $maxExecutionTime  Maximum time to spend cleaning obsolete spam
	 *
	 * @return  int  Total number of spam comments deleted.
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
	 * Get a slice of comment IDs with depth (level) information.
	 *
	 * The comment ID slice is aware of the tree nature of the comments.
	 *
	 * Use $start=0 and $limit=null to retrieve the entire tree
	 *
	 * @param   int       $start  Starting offset of the slice
	 * @param   int|null  $limit  Maximum number of elements to retrieve
	 *
	 * @return array An array of id => depth
	 */
	public function commentIDTreeSliceWithDepth(int $start, ?int $limit = null): array
	{
		// Get all the IDs filtered by the model
		$db     = $this->getDbo();
		$query  = $this->buildQuery(true)
			->clear('select')
			->select([
				$db->qn('engage_comment_id'),
				$db->qn('parent_id'),
			]);
		$allIDs = $db->setQuery($query)->loadAssocList('engage_comment_id') ?? [];

		$this->treeAwareCount = 0;

		// No IDs? Empty list!
		if (empty($allIDs))
		{
			return [];
		}

		// Convert into an ID => parent array
		$allIDs = array_map(function ($x) {
			return $x['parent_id'];
		}, $allIDs);

		$this->treeAwareCount = count($allIDs);

		// Filter out orphan nodes (children of deleted or unpublished comments)
		$allIDs = array_filter($allIDs, function ($parent_id) use ($allIDs) {
			return is_null($parent_id) || array_key_exists($parent_id, $allIDs);
		});

		/**
		 * Create a tree version of the comments and flatten it out
		 *
		 * Starting at parent id NULL forces makeIDTree to start from the first level nodes that have no parents.
		 */
		$flattened = $this->flattenIDTree($this->makeIDTree($allIDs, null));

		unset($allIDs);

		return array_slice($flattened, $start, $limit, true);
	}

	/**
	 * Utility function that converts an array of id => parent_id into a tree representation of IDs.
	 *
	 * @param   array     $allIDs    The source array of id => parent_id entries
	 * @param   int|null  $parentId  The parent ID to retrieve
	 *
	 * @return array
	 * @see    self::commentIDTreeSliceWithDepth
	 */
	protected function makeIDTree(array &$allIDs, ?int $parentId): array
	{
		$childIDs = array_keys($allIDs, $parentId);

		if (empty($childIDs))
		{
			return [];
		}

		$ret = [];

		foreach ($childIDs as $thisParentId)
		{
			$ret[$thisParentId] = $this->makeIDTree($allIDs, $thisParentId);
		}

		return $ret;
	}

	/**
	 * Converts a tree of IDs into a flat array of ID => depth preserving ID order as seen in the tree.
	 *
	 * @param   array  $tree         The tree array
	 * @param   int    $parentLevel  Which level am I currently in
	 *
	 * @return array
	 * @see    self::commentIDTreeSliceWithDepth
	 * @see    self::makeIDTree
	 */
	protected function flattenIDTree(array $tree, $parentLevel = 0): array
	{
		$ret = [];

		foreach ($tree as $k => $v)
		{
			$ret[" " . $k] = $parentLevel + 1;

			if (!empty($v) && is_array($v))
			{
				$ret = array_merge($ret, $this->flattenIDTree($v, $parentLevel + 1));
			}
		}

		return $ret;
	}

	protected function onBeforeBuildQuery(JDatabaseQuery &$query)
	{
		$this->filterByAssetTitle($query);

		$filterEmail = $this->getState('filter_email');
		$filterEmail = trim($filterEmail);

		if (empty($filterEmail))
		{
			return;
		}

		$filterEmail = (strpos($filterEmail, '%') === false) ? "%$filterEmail%" : $filterEmail;
		$db          = $this->dbo;

		$conditions = [
			$db->qn('email') . ' LIKE ' . $db->q($filterEmail),
		];

		// Get user IDs matching partial email
		$q       = $db->getQuery(true)
			->select([$db->qn('id')])
			->from($db->qn('#__users'))
			->where($db->qn('email') . ' LIKE ' . $db->q($filterEmail));
		$userIDs = $db->setQuery($q)->loadColumn();

		if (empty($userIDs))
		{
			$query->where($conditions[0]);

			return;
		}

		// Filter by these IDs **OR** a matching email field
		$userIDs      = array_map([$db, 'q'], $userIDs);
		$conditions[] = $db->qn('created_by') . ' IN(' . implode(',', $userIDs) . ')';

		$conditions = array_map(function ($condition) {
			return '(' . $condition . ')';
		}, $conditions);

		$query->where('(' . implode(' OR ', $conditions) . ')');
	}

	/**
	 * Deletes all children comment on comment deletion
	 *
	 * @param   mixed  $id  Primary key of the comment being deleted.
	 */
	protected function onAfterDelete(&$id)
	{
		/** @var self $model */
		$model = $this->tmpInstance();

		try
		{
			$model->parent_id($id)->get(true)->delete();
		}
		catch (Exception $e)
		{
			return;
		}
	}

	/**
	 * Apply comments filtering by asset title
	 *
	 * @param   JDatabaseQuery  $query  The SELECT query we're modifying
	 *
	 * @return  void
	 */
	private function filterByAssetTitle(JDatabaseQuery &$query): void
	{
		$fltAssetTitle = $this->getState('asset_title');

		if ($fltAssetTitle)
		{
			$this->container->platform->importPlugin('content');
			$this->container->platform->importPlugin('engage');
			$results = $this->container->platform->runPlugins('onAkeebaEngageGetAssetIDsByTitle', [$fltAssetTitle]);
			$ids     = [];

			array_walk($results, function ($someIDs) use (&$ids) {
				if (empty($someIDs))
				{
					return;
				}

				$ids = array_merge($ids, $someIDs);
			});

			$ids = array_map(function ($x) {
				return max(0, (int) $x);
			}, $ids);

			$ids = array_filter($ids, function ($x) {
				return !empty($x);
			});

			$ids = empty($ids) ? [-1] : array_unique($ids);
			$ids = array_map([$query, 'q'], $ids);

			$query->where($query->qn('asset_id') . ' IN (' . implode(',', $ids) . ')');
		}
	}

	/**
	 * Automatically deletes up to 100 spam comments which are older than this many days.
	 *
	 * @param   int  $maxDays
	 *
	 * @return  int  Number of spam comments deleted
	 */
	private function cleanSpamChunk(int $maxDays = 15): int
	{
		$maxDays = max(0, $maxDays);

		if ($maxDays === 0)
		{
			return 0;
		}

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
}