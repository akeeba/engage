<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Akeeba Engage -- Sample comments creator
 *
 * WARNING! Do not use this script on live sites. It will DELETE AND REPLACE all your comments.
 *
 * This script is meant to create a lot of sample comments on every commentable article on your site.
 *
 * Remember to run `composer install` on this directory before running this script. Moreover, when running this script
 * make sure that the current working directory is set to the `cli` folder of the target Joomla site.
 */

// Enable Joomla's debug mode
define('JDEBUG', 1);

use Akeeba\Engage\Admin\Model\Comments;
use Akeeba\Engage\Site\Helper\Meta;
use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Faker\Provider\Biased;
use FOF30\Container\Container;
use Joomla\CMS\Access\Access;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\Router\Router;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\User;

// region FOF 3 CLI Boilerplate
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $currentDirectory)
{
	if (file_exists($currentDirectory . '/defines.php'))
	{
		define('JPATH_BASE', realpath($currentDirectory . '/..'));
		require_once $currentDirectory . '/defines.php';

		break;
	}

	if (file_exists($currentDirectory . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($currentDirectory . '/..'));
		require_once $currentDirectory . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be placed in or run from the cli folder of your site.');

require_once JPATH_LIBRARIES . '/fof30/Cli/Application.php';
// endregion

// Composer dependency
require_once __DIR__ . '/vendor/autoload.php';

class EngageDebugCommentsCreate extends FOFApplicationCLI
{
	/**
	 * Probability that a comment is filed by a guest (0.0 to 1.0)
	 *
	 * @var   float
	 */
	private $guestCommentProbability = 0.75;

	/**
	 * Maximum posts per level (linear low algorithm)
	 *
	 * @var   int[]
	 */
	private $maxPostPerLevel = [1200];

	/**
	 * Maximum paragraphs in the comments (linear low algorithm)
	 *
	 * @var   int
	 */
	private $maxParagraphs = 3;

	/**
	 * User IDs which may leave a comment
	 *
	 * @var   int[]
	 */
	private $allowedUsers = [];

	/**
	 * The component's container
	 *
	 * @var   Container
	 */
	private $container;

	/**
	 * The Faker generator, used to create random data
	 *
	 * @var  FakerGenerator
	 */
	private $faker;

	public function getParams($option = null)
	{
		static $params = [];

		$hash = '__default';

		if (!empty($option))
		{
			$hash = $option;
		}

		if (!isset($params[$hash]))
		{
			// Get component parameters
			if (!$option)
			{
				$option = $this->input->getCmd('option', null);
			}

			// Get new instance of component global parameters
			$params[$hash] = clone ComponentHelper::getParams($option);

			// Get menu parameters
			$menu = null;

			$title = $this->get('sitename');

			$description = $this->get('MetaDesc');

			$rights = $this->get('MetaRights');
			$robots = $this->get('robots');

			// Retrieve com_menu global settings
			$temp = clone ComponentHelper::getParams('com_menus');

			// Lets cascade the parameters if we have menu item parameters
			if (is_object($menu))
			{
				// Get show_page_heading from com_menu global settings
				$params[$hash]->def('show_page_heading', $temp->get('show_page_heading'));

				$params[$hash]->merge($menu->params);
				$title = $menu->title;
			}
			else
			{
				// Merge com_menu global settings
				$params[$hash]->merge($temp);

				// If supplied, use page title
				$title = $temp->get('page_title', $title);
			}

			$params[$hash]->def('page_title', $title);
			$params[$hash]->def('page_description', $description);
			$params[$hash]->def('page_rights', $rights);
			$params[$hash]->def('robots', $robots);
		}

		return $params[$hash];
	}

	public function isClient($identifier)
	{
		return $identifier === 'cli';
	}

	/**
	 * Returns the application Router object. Necessary for fetching emails.
	 *
	 * @param   string  $name     The name of the application.
	 * @param   array   $options  An optional associative array of configuration settings.
	 *
	 * @return  Router|null  A JRouter object
	 * @since   3.2.0
	 */
	public function getRouter($name = null, array $options = [])
	{
		try
		{
			return Router::getInstance('site', $options);
		}
		catch (Exception $e)
		{
			return null;
		}
	}

	public function getMenu($name = null, $options = [])
	{
		return AbstractMenu::getInstance($name, $options);
	}

	protected function doExecute()
	{
		$this->out('Initializing');

		$this->faker     = FakerFactory::create();
		$this->container = Container::getInstance('com_engage');
		$this->initCliRouting('https://www.example.com');
		$this->disableDatabaseLogging();
		$this->makeTempTable();
		$this->reinstallSchema();
		$this->populateMaxPostsPerLevel();

		$this->out('Finding candidate users for new comments');

		$this->populateAllowedUsers();

		// If Guest does not have comment privileges set $this->guestCommentProbability to 0
		$guest = new User();

		if (!$guest->authorise('core.create', 'com_engage'))
		{
			$this->guestCommentProbability = 0.00;
		}

		// Get all articles
		$assetIDs = $this->getArticleAssetIDs();
		$this->out(sprintf('Iterating %d articles', count($assetIDs)));

		$this->container->platform->setAllowPluginsInCli(true);
		$this->container->platform->importPlugin('content');
		$this->container->platform->importPlugin('engage');

		foreach ($assetIDs as $assetID)
		{
			$meta = Meta::getAssetAccessMeta($assetID, true);

			$this->out($meta['title']);

			if ($meta['parameters']->get('comments_enabled', '1') == 0)
			{
				$this->out('  I will create no comments for this article.');

				continue;
			}

			$numComments = $this->createTempInfo($assetID, $meta);

			$this->out(sprintf('  I will create %d comments for this article.', $numComments));
		}

		// Create the comments in chronological order
		$this->createCommentsFromTempInfo();

		// Drop the temporary table.
		$this->dropTempTable();
	}

	private function initCliRouting($siteURL)
	{
		// Set up the base site URL in JUri
		$uri                    = Uri::getInstance($siteURL);
		$_SERVER['HTTP_HOST']   = $uri->toString(['host', 'port']);
		$_SERVER['REQUEST_URI'] = $uri->getPath();

		$refClass     = new ReflectionClass(Uri::class);
		$refInstances = $refClass->getProperty('instances');
		$refInstances->setAccessible(true);
		$instances           = $refInstances->getValue();
		$instances['SERVER'] = $uri;
		$refInstances->setValue($instances);

		$base = [
			'prefix' => $uri->toString(['scheme', 'host', 'port']),
			'path'   => rtrim($uri->toString(['path']), '/\\'),
		];

		$refBase = $refClass->getProperty('base');
		$refBase->setAccessible(true);
		$refBase->setValue($base);

		// Set up the SEF mode in the router
		$this->getRouter()->setMode($this->get('sef', 0));
	}

	private function createTempInfo(int $assetID, array $meta): int
	{
		// Initialize with a root node
		$commentsInfo = [
			[
				'uuid'      => 0,
				'id'        => 0,
				'level'     => 0,
				'parent_id' => 0,
				'asset_id'  => 0,
				'date'      => $meta['published_on'],
			],
		];

		// This is how many levels we're going to create at a maximum
		$maxLevel = $this->container->params->get('max_level', 3);

		for ($level = 1; $level <= $maxLevel; $level++)
		{
			// Get the parent comments we are replying to (for level 1 that's the root node)
			$toComment = array_filter($commentsInfo, function ($info) use ($level) {
				return $info['level'] == $level - 1;
			});

			foreach ($toComment as $parent)
			{
				/** @var Date $earliestDate */
				$earliestDate = $parent['date'];
				$latestDate   = (clone $earliestDate)->add(new DateInterval('P2Y'));

				if ($latestDate->getTimestamp() > time())
				{
					$latestDate = new Date();
				}

				// How many comments should I create?
				$maxComments = $this->maxPostPerLevel[$level - 1];
				$biasAlgo    = ($level == 1) ? 'linearHigh' : 'linearLow';
				$numComments = $this->faker->biasedNumberBetween(1, $maxComments, [Biased::class, $biasAlgo]);

				// Create info for $numComments in total
				for ($j = 0; $j < $numComments; $j++)
				{
					$randomTimestamp = $this->faker->biasedNumberBetween($earliestDate->toUnix(), $latestDate->toUnix(), [
						Biased::class, 'linearLow',
					]);
					$earliestDate    = new Date($randomTimestamp);

					if ($earliestDate->toSql() > time())
					{
						break;
					}

					$commentsInfo[] = [
						'uuid'      => $this->faker->uuid,
						'id'        => null,
						'level'     => $level,
						'parent_id' => ($parent['level'] === 0) ? 0 : ('@' . $parent['uuid']),
						'asset_id'  => $assetID,
						'date'      => $earliestDate,
					];
				}
			}
		}

		// Remove root comment (0)
		unset($commentsInfo[0]);

		// Map each Date object to its SQL representation
		$commentsInfo = array_map(function (array $info) {
			$info['date'] = $info['date']->toSql();

			return $info;
		}, $commentsInfo);

		// Commit to database
		$db = $this->container->db;

		foreach ($commentsInfo as $info)
		{
			$infoObject = (object) $info;

			$db->insertObject('#__engage_temp_info', $infoObject, 'uuid');
		}

		return count($commentsInfo);
	}

	private function createCommentsFromTempInfo()
	{
		/** @var Comments $cModel */
		$cModel     = $this->container->factory->model('Comments')->tmpInstance();
		$rootNode   = $cModel->getClone()->findOrFail(1);
		$faker      = FakerFactory::create();
		$limitStart = 0;
		$db         = $this->container->db;
		$query      = $db->getQuery(true)->select('*')->from($db->qn('#__engage_temp_info'))->order($db->qn('date') . ' ASC');

		$this->out('Committing comments in chronological order');

		$maxInsertTime = 0.0;

		while (true)
		{
			if ($maxInsertTime > 0.001)
			{
				$this->out(sprintf('    Max record insert time: %0.3f milliseconds', $maxInsertTime * 1000));
			}

			$this->out(sprintf('  100 comments, starting with %d', $limitStart));
			$commentInfoArray = $db->setQuery($query, $limitStart, 100)->loadAssocList();

			if (empty($commentInfoArray))
			{
				$this->out('All done!');
				break;
			}

			$limitStart += 100;

			foreach ($commentInfoArray as $info)
			{
				if (substr($info['parent_id'], 0, 1) === '@')
				{
					$parentUUID        = substr($info['parent_id'], 1);
					$infoQ             = $db->getQuery(true)
						->select($db->qn('id'))
						->from('#__engage_temp_info')
						->where($db->qn('uuid') . ' = ' . $db->q($parentUUID));
					$info['parent_id'] = $db->setQuery($infoQ)->loadResult();
				}

				$cModel->reset()->bind([
					'asset_id'   => $info['asset_id'],
					'enabled'    => 1,
					'body'       => $this->getCommentText($faker),
					'name'       => $faker->name,
					'email'      => $faker->email,
					'ip'         => $faker->ipv4,
					'user_agent' => $faker->userAgent,
					'created_by' => 0,
					'created_on' => $info['date'],
				]);

				if (!$faker->boolean($this->guestCommentProbability * 100))
				{
					$cModel->created_by = $faker->randomElement($this->allowedUsers);
					$cModel->name       = null;
					$cModel->email      = null;
				}

				$timerStart = microtime(true);

				if ($info['parent_id'] == 0)
				{
					$cModel->insertAsChildOf($rootNode);
				}
				else
				{
					$parentNode = $cModel->getClone()->findOrFail($info['parent_id']);
					$cModel->insertAsChildOf($parentNode);
				}

				$timerEnd      = microtime(true);
				$maxInsertTime = max($maxInsertTime, $timerEnd - $timerStart);

				$info['id'] = $cModel->getId();
				$infoObject = (object) $info;
				$db->updateObject('#__engage_temp_info', $infoObject, 'uuid');
			}
		}
	}

	/**
	 * Disables database query logging.
	 *
	 * When Joomla's debug mode is enabled the database driver will log all executed queries and their performance. This
	 * is great if you're debugging something but not so great when you want to run thousands of queries when populating
	 * sample data. This method terminates database query logging with extreme prejudice.
	 *
	 * @return  void
	 */
	private function disableDatabaseLogging(): void
	{
		$db = Factory::getDbo();
		$db->setDebug(false);
	}

	/**
	 * Re-installs Akeeba Engage database schema.
	 *
	 * This removes comments, unsubscribe information and email templates. It resets everything to the factory default
	 * state.
	 *
	 * @return  void
	 */
	private function reinstallSchema(): void
	{
		$container = $this->container;
		$installer = new \FOF30\Database\Installer($container->db, $container->backEndPath . '/sql/xml');
		$installer->removeSchema();
		$installer->updateSchema();
	}

	/**
	 * Return #__assets IDs for all com_content articles
	 *
	 * @return  int[]
	 */
	private function getArticleAssetIDs(): array
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select([$db->qn('asset_id')])
			->from($db->qn('#__content'))
			->where($db->qn('state') . ' = ' . $db->q(1));

		return $db->setQuery($query)->loadColumn(0) ?? [];
	}

	/**
	 * Returns the created_on timestamp of an article given its asset ID
	 *
	 * @param   int  $assetID  The asset ID of a Joomla! article.
	 *
	 * @return  int  The article's created time as a UNIX timestamp
	 */
	private function getArticleCreatedByAssetId(int $assetID): int
	{
		$db    = Factory::getDbo();
		$query = $db->getQuery(true)
			->select([$db->qn('created')])
			->from($db->qn('#__content'))
			->where($db->qn('asset_id') . ' = ' . $db->q($assetID));

		try
		{
			$created = $db->setQuery($query)->loadResult();
		}
		catch (Exception $e)
		{
			$created = null;
		}

		try
		{
			$jDate = new FOF30\Date\Date($created);
		}
		catch (Exception $e)
		{
			$jDate = new \FOF30\Date\Date();
			$jDate = $jDate->sub(new DatePeriod('P1Y'));
		}

		return $jDate->getTimestamp();
	}

	/**
	 * Define the maximum number of posts per level
	 *
	 * This depends on how many max levels I have. I don't want to overdo it.
	 */
	private function populateMaxPostsPerLevel(): void
	{
		switch ($this->container->params->get('max_level', 3))
		{
			case 1:
				$this->maxPostPerLevel = [1200];
				break;

			case 2:
				$this->maxPostPerLevel = [50, 30];
				break;

			case 3:
			default:
				$this->maxPostPerLevel = [30, 10, 3];
				break;

			case 4:
				$this->maxPostPerLevel = [25, 8, 3, 2];
				break;

			case 5:
				$this->maxPostPerLevel = [20, 7, 3, 2, 2];
				break;

			case 6:
				$this->maxPostPerLevel = [15, 5, 3, 2, 2, 1];
				break;
		}
	}

	private function populateAllowedUsers()
	{
		// Preload the permissions I am going to be using
		Access::preload();

		// Get all groups
		$db     = $this->container->db;
		$q      = $db->getQuery(true)
			->select([$db->qn('id')])
			->from($db->qn('#__usergroups'));
		$groups = $db->setQuery($q)->loadColumn();

		// Get the groups that can create comments or are Super Users
		$groups = array_filter($groups, function ($gid) {
			return Access::checkGroup($gid, 'core.create', 'com_engage') ||
				Access::checkGroup($gid, 'core.admin');
		});

		// Get the user IDs who are allowed to post comments
		$db->getQuery(true)
			->select([
				$db->qn('user_id'),
			])->where($db->qn('group_id') . ' IN(' . implode(',', $groups) . ')');

		$this->allowedUsers = $db->setQuery($q)->loadColumn();
	}

	private function makeTempTable()
	{
		$sql = <<< MySQL
create table `#__engage_temp_info`
(
	`uuid` char(46) not null,
	`id` bigint null,
	`level` tinyint(2) default 1 not null,
	`parent_id` varchar(50) not null,
	`asset_id` bigint not null,
	`date` datetime not null,
	constraint dev31_engage_temp_info_pk
		primary key (uuid)
) ENGINE MyISAM;

MySQL;
		$db  = $this->container->db;
		$db->dropTable('#__engage_temp_info', true);
		$db->setQuery($sql)->execute();
	}

	private function dropTempTable()
	{
		$db = $this->container->db;
		$db->dropTable('#__engage_temp_info', true);
	}

	/**
	 * @param   FakerGenerator  $faker
	 *
	 * @return  string[]
	 */
	private function getCommentText(FakerGenerator $faker): string
	{
		$numParagraphs = $faker->biasedNumberBetween(0, $this->maxParagraphs, [Biased::class, 'linearLow']);

		if ($numParagraphs == 0)
		{
			$numSentences = $this->faker->numberBetween(1, 3);

			return "<p>" . implode(". ", $faker->sentences($numSentences, false)) . ".</p>";
		}

		return implode("\n", array_map(function ($p) {
			return "<p>$p</p>";
		}, $faker->paragraphs($numParagraphs)));
}
}

try
{
	FOFApplicationCLI::getInstance('EngageDebugCommentsCreate')->execute();
}
catch (Throwable $e)
{
	$errorType = get_class($e);

	echo <<< TEXT
================================================================================
E R R O R   #{$e->getCode()} --  {$errorType}
================================================================================

{$e->getMessage()}

{$e->getFile()}:{$e->getLine()}

{$e->getTraceAsString()}

TEXT;

	exit(255);
}