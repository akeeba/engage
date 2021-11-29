<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Administrator\Table;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Model\CommentsModel;
use Akeeba\Component\Engage\Administrator\Table\Mixin\CreateModifyAware;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Factory\MVCFactoryServiceInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Event\DispatcherInterface;
use RuntimeException;

defined('_JEXEC') or die;

/**
 * Table class for Akeeba Engage Comment records
 *
 * @property int         $id          Comment ID
 * @property int         $parent_id   Parent comment ID
 * @property int         $asset_id    Asset ID being commented on
 * @property string      $body        Comment body
 * @property string|null $name        Commenter's name. Only used when created_by is 0.
 * @property string|null $email       Commenter's email address. Only used when created_by is 0.
 * @property string|null $ip          IP address of the visitor leaving the comment.
 * @property string      $user_agent  User Agent string of the browser of the visitor leaving the comment.
 * @property int         $enabled     Is the comment published?
 * @property string|null $created     When was the comment created?
 * @property int|null    $created_by  User ID of the user who created the comment, 0 for guest comments.
 * @property string|null $modified    When was the comment modified?
 * @property int|null    $modified_by User ID of the user who modified the comment.
 *
 * @since 3.0.0
 */
class CommentTable extends AbstractTable
{
	use CreateModifyAware
	{
		CreateModifyAware::onBeforeStore as onBeforeStoreCreateModifyAware;
	}

	/**
	 * Object constructor.
	 *
	 * @param   DatabaseDriver            $db          DatabaseDriver object.
	 * @param   DispatcherInterface|null  $dispatcher  Event dispatcher for this table
	 *
	 * @since   3.0.0
	 */
	public function __construct(DatabaseDriver $db, DispatcherInterface $dispatcher = null)
	{
		parent::__construct('#__engage_comments', 'id', $db, $dispatcher);

		$this->setColumnAlias('published', 'enabled');
	}

	/**
	 * Runs after deleting a comment. Used to automatically delete all child comments as well.
	 *
	 * @param   bool  $result  Was the comment deleted?
	 * @param   int   $pk      Primary Key (ID) of the comment deleted
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function onAfterDelete(bool &$result, int $pk): void
	{
		if (!$result)
		{
			return;
		}

		// Get the child comments and delete them as well
		$component = Factory::getApplication()->bootComponent('com_engage');

		if (!$component instanceof MVCFactoryServiceInterface)
		{
			return;
		}

		$factory       = $component->getMVCFactory();
		/** @var CommentsModel $commentsModel */
		$commentsModel = $factory->createModel('Comments', 'Administrator', [
			'ignore_request' => true,
		]);

		$commentsModel->setState('filter.parent_id', $pk);
		$commentsModel->setState('list.limit', 30);

		while (true)
		{
			$commentsSlice = $commentsModel->getItems();

			if (empty($commentsSlice))
			{
				break;
			}

			$ids = array_map(function ($x) {
				return $x->id;
			}, $commentsSlice);

			$table = clone $this;
			$table->reset();

			foreach ($ids as $id)
			{
				$table->delete($id);
			}
		}
	}

	/**
	 * Runs before checking the table object's data for validity. Performs custom checks.
	 *
	 * @since   3.0.0
	 */
	protected function onBeforeCheck(): void
	{
		// Make sure we have EITHER a user OR both an email and full name
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
			throw new RuntimeException(Text::_('COM_ENGAGE_COMMENTS_ERR_NO_NAME_OR_EMAIL'));
		}

		// If we have a guest user, make sure we don't have another user with the same email address
		if (($this->created_by <= 0) && !empty(UserFetcher::getUserIdByEmail($this->email)))
		{
			throw new RuntimeException(Text::sprintf('COM_ENGAGE_COMMENTS_ERR_EMAIL_IN_USE', $this->email));
		}
	}
}