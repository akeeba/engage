<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\Component\Engage\Administrator\Model\CommentsModel;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Featured Article comments summary
 *
 * This layout is used when displaying the comments summary in the featured article view.
 *
 * Override by creating the folder templates/YOUR_TEMPLATE/html/layouts/akeeba/engage/content and copying this file in
 * there. That file will be used instead of the file plugins/content/engage/layouts/akeeba/engage/content/featured.php
 *
 * @var  array          $displayData The incoming display data. It's extracted into scope in the following variables.
 * @var  CMSApplication $app         The current application
 * @var  CommentsModel  $model       The Akeeba Engage Comments model object
 * @var  stdClass       $row         The Joomla article object.
 * @var  array          $meta        Display metadata, returned by Meta::getAssetAccessMeta().
 *
 * @see  \Akeeba\Component\Engage\Site\Helper\Meta::getAssetAccessMeta()
 */
extract($displayData);

// Get the number of comments for this article
$model->setState('filter.asset_id', $row->asset_id);
$numComments = $model->getTotal();

// Language key to use
$headerKey = 'COM_ENGAGE_COMMENTS_HEADER_N_COMMENTS';
$key       = sprintf('COM_ENGAGE_COMMENTS_%s_HEADER_N_COMMENTS', strtoupper($meta['type']));
$lang      = $app->getLanguage();
$headerKey = $lang->hasKey($key) ? $key : $headerKey;

$uri = Uri::getInstance($meta['public_url']);
$uri->setFragment('akengage-comments-section');
?>
<aside class="akenage-comments-counter--featured">
	<a href="<?= $uri->toString() ?>">
		<data itemprop="commentCount" value="<?= $numComments ?>">
			<?= Text::plural($headerKey, $numComments, $row->title) ?>
		</data>
	</a>
</aside>
