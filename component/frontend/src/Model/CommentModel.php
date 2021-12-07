<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Component\Engage\Site\Model;

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Model\CommentModel as AdminCommentModel;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Exception;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\User\User;
use RuntimeException;

class CommentModel extends AdminCommentModel
{
	/**
	 * Method for getting a form.
	 *
	 * @param   array    $data      Data for the form.
	 * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
	 *
	 * @return  Form|bool
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 *
	 */
	public function getForm($data = [], $loadData = true)
	{
		$id     = $data['id'] ?? null;
		$isNew  = empty($id);
		$source = $isNew ? 'comment_new' : 'comment';

		$form = $this->loadForm(
			'com_engage.comment',
			$source,
			[
				'control'   => 'jform',
				'load_data' => $loadData,
			]) ?: false;

		if (empty($form))
		{
			return false;
		}

		if ($isNew)
		{
			$this->postProcessNewCommentForm($form, $data);
		}
		else
		{
			$this->postProcessEditCommentForm($form, $data);
		}

		return $form;
	}

	/**
	 * Resubscribe a user to some content's comments
	 *
	 * @param   int          $asset_id  The asset ID of the comment we are commenting on
	 * @param   User|null    $user      The user account which is commenting
	 * @param   string|null  $email     The email address of the user commenting if $user is a guest
	 *
	 * @since   3.0.0
	 */
	public function resubscribeUser(int $asset_id, ?User $user, ?string $email)
	{
		$email = $user->guest ? $email : $user->email;

		if (empty($email))
		{
			return;
		}

		$db    = $this->getDbo();
		$query = $db->getQuery(true)
			->delete($db->quoteName('#__engage_unsubscribe'))
			->where($db->quoteName('asset_id') . ' = :asset_id')
			->where($db->quoteName('email') . ' = :email')
			->bind(':asset_id', $asset_id)
			->bind(':email', $email);
		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			// Ignore any failures, they are not important.
		}
	}

	/**
	 * Method to validate the form data.
	 *
	 * This is used to apply additional validation on top of what the form itself already offers.
	 *
	 * @param   Form    $form   The form to validate against.
	 * @param   array   $data   The data to validate.
	 * @param   string  $group  The name of the field group to validate.
	 *
	 * @return  array|boolean  Array of filtered data if valid, false otherwise.
	 *
	 * @throws  Exception
	 * @see     InputFilter
	 * @since   3.0.0
	 */
	public function validate($form, $data, $group = null)
	{
		$assetId = $data['asset_id'] ?? 0;

		$this->assertAssetAccess($assetId);
		$this->assertCommentsOpen($assetId);
		$this->assertAcceptTos($data['accept_tos'] ?? false);

		return parent::validate($form, $data, $group);
	}

	/**
	 * Post–process the edit an existing comment comment form
	 *
	 * @param   Form   $form  The form we have already loaded
	 * @param   array  $data  The data we loaded in the form
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function postProcessEditCommentForm(Form $form, array $data)
	{
		$user           = UserFetcher::getUser();
		$removeFields   = [];
		$readonlyFields = [];

		if (!$user->authorise('core.manage', 'com_engage'))
		{
			$readonlyFields = ['name', 'email', 'created_by', 'created', 'enabled'];
			$removeFields   = ['ip', 'user_agent', 'modified', 'modified_by'];

			if ($user->authorise('core.edit.state', 'com_engage'))
			{
				array_pop($readonlyFields);
			}
		}

		foreach ($removeFields as $fieldName)
		{
			$form->removeField($fieldName);
		}

		foreach ($readonlyFields as $fieldName)
		{
			$form->setFieldAttribute($fieldName, 'disabled', 'true');
			$form->setFieldAttribute($fieldName, 'required', 'false');
			$form->setFieldAttribute($fieldName, 'filter', 'unset');
		}
	}

	/**
	 * Post–process the new comment form
	 *
	 * @param   Form   $form  The form we have already loaded
	 * @param   array  $data  The data we loaded in the form
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function postProcessNewCommentForm(Form $form, array $data)
	{
		$user          = UserFetcher::getUser();
		$cParams       = ComponentHelper::getParams('com_engage');
		$tosPrompt     = $cParams->get('tos_prompt');
		$acceptTos     = $cParams->get('accept_tos', 0) == 1;
		$tosChecked    = (bool) ($data['accept_tos'] ?? 0);
		$captchaPlugin = $cParams->get('captcha', '0');
		$captchaFor    = $cParams->get('captcha_for', 'guests');

		// Only guests see the Accept ToS field and only if configured
		if (!$user->guest || !$acceptTos || empty($tosPrompt))
		{
			$form->removeField('accept_tos');
		}
		else
		{
			$form->setFieldAttribute('accept_tos', 'label', $tosPrompt);
			$form->setFieldAttribute('accept_tos', 'checked', $tosChecked ? 'true' : 'false');
		}

		// Should I display the CAPTCHA?
		$showCaptcha = false;

		switch ($captchaFor)
		{
			case 'guests':
				$showCaptcha = $user->guest;
				break;

			case 'nonmanager':
				$showCaptcha = !$user->guest && !$user->authorise('core.manage', 'com_engage');
				break;
		}

		$showCaptcha &= ($captchaPlugin !== '0');

		if (!$showCaptcha)
		{
			$form->removeField('captcha');
		}
		else
		{
			$form->setFieldAttribute('captcha', 'plugin', $captchaPlugin);
		}
	}

	/**
	 * Prepare the table before saving it into the database.
	 *
	 * @param   CommentTable  $table
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	protected function prepareTable($table)
	{
		$user    = UserFetcher::getUser();
		$cParams = ComponentHelper::getParams('com_engage');

		// Non-admin users may have their comments auto-unpublished by default
		if (!$user->authorise('core.manage', 'com_engage'))
		{
			$table->enabled = $cParams->get('default_publish', 1);
		}

		// If it's not a guest user we need to unset the name and email
		if (!$user->guest)
		{
			$table->name       = null;
			$table->email      = null;
			$table->created_by = $user->id;
		}

		// Spam check
		PluginHelper::importPlugin('engage');
		$spamResults = Factory::getApplication()->triggerEvent('onAkeebaEngageCheckSpam', [$table]);

		if (in_array(true, $spamResults, true))
		{
			$table->enabled = -3;
		}

		parent::prepareTable($table);
	}

	/**
	 * Assert that the guest user has accepted the Terms of Service.
	 *
	 * This is contingent upon a component option.
	 *
	 * @param   bool  $acceptTos
	 *
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function assertAcceptTos(bool $acceptTos)
	{
		$user       = UserFetcher::getUser();
		$mustAccept = ComponentHelper::getParams('com_engage')->get('tos_accept', 0) == 1;

		if ($user->guest && $mustAccept && !$acceptTos)
		{
			throw new RuntimeException(Text::_('COM_ENGAGE_COMMENTS_ERR_TOSACCEPT'));
		}
	}

	/**
	 * Asserts that the user has view access to a published asset. Throws a RuntimeException otherwise.
	 *
	 * @param   int|null  $assetId
	 *
	 * @return  void
	 * @throws  Exception
	 * @since   3.0.0
	 */
	private function assertAssetAccess(?int $assetId): void
	{
		if (empty($assetId) || ($assetId <= 0))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Get the asset access metadata
		$assetMeta = Meta::getAssetAccessMeta($assetId);

		// Make sure the associated asset is published
		if (!$assetMeta['published'])
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		// Make sure the user is allowed to view this asset and its parent
		$access       = $assetMeta['access'];
		$parentAccess = $assetMeta['parent_access'];
		$user         = UserFetcher::getUser();

		if (!is_null($access) && !in_array($access, $user->getAuthorisedViewLevels()))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}

		if (!is_null($parentAccess) && !in_array($parentAccess, $user->getAuthorisedViewLevels()))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}

	/**
	 * Asserts that the comments for the content being commented on have not been closed.
	 *
	 * @param   int|null  $assetId  The asset ID of the content being commented on.
	 *
	 * @since   3.0.0
	 */
	private function assertCommentsOpen(?int $assetId): void
	{
		if (empty($assetId) || ($assetId <= 0) || Meta::areCommentsClosed($assetId))
		{
			throw new RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
		}
	}
}