<?php
/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

defined('_JEXEC') or die;

use Akeeba\Component\Engage\Administrator\Helper\UserFetcher;
use Akeeba\Component\Engage\Administrator\Table\CommentTable;
use Akeeba\Component\Engage\Site\Helper\Meta;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Environment\Browser;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Akeeba\Component\Engage\Administrator\View\Comments\HtmlView $this */

HTMLHelper::_('bootstrap.tooltip', '.hasTooltip');
HTMLHelper::_('bootstrap.modal');

$app       = Factory::getApplication();
$user      = $app->getIdentity();
$userId    = $user->get('id');
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$nullDate  = Factory::getContainer()->get('DatabaseDriver')->getNullDate();

// The asset we are filtered by
$filteredAsset = $this->activeFilters['asset_id'] ?? null;

$i = 0;

?>
<?= $this->loadAnyTemplate('common/phpversion_warning', false, [
	'softwareName'  => 'Akeeba Engage',
	'minPHPVersion' => '7.2.0',
]) ?>

<form action="<?= Route::_('index.php?option=com_engage&view=comments'); ?>"
      method="post" name="adminForm" id="adminForm">
	<div class="row">
		<div class="col-md-12">
			<div id="j-main-container" class="j-main-container">
				<?= LayoutHelper::render('joomla.searchtools.default', ['view' => $this]) ?>
				<?php if (empty($this->items)) : ?>
					<div class="alert alert-info">
						<span class="icon-info-circle" aria-hidden="true"></span><span
							class="visually-hidden"><?= Text::_('INFO'); ?></span>
						<?= Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
					</div>
				<?php else : ?>
					<table class="table" id="commentsList">
						<caption class="visually-hidden">
							<?= Text::_('COM_ENGAGE_COMMENTS_TABLE_CAPTION'); ?>,
							<span id="orderedBy"><?= Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
							<span id="filteredBy"><?= Text::_('JGLOBAL_FILTERED_BY'); ?></span>
						</caption>
						<thead>
						<tr>
							<td class="w-1 text-center">
								<?= HTMLHelper::_('grid.checkall'); ?>
							</td>
							<th scope="col" class="w-20 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'COM_ENGAGE_COMMENT_FIELD_CREATED_BY', 'user_name', $listDirn, $listOrder); ?>
							</th>
							<th scope="col">
								<?= HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'c.id', $listDirn, $listOrder); ?>
								<span class="text-muted">&ensp;/&ensp;</span>
								<?= Text::_('COM_ENGAGE_COMMENT_FIELD_BODY') ?>
							</th>
							<th scope="col" class="w-5 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'COM_ENGAGE_COMMENT_FIELD_CREATED_ON', 'c.created', $listDirn, $listOrder); ?>
							</th>
							<th scope="col" class="w-5 d-none d-md-table-cell">
								<?= HTMLHelper::_('searchtools.sort', 'JSTATUS', 'c.enabled', $listDirn, $listOrder); ?>
							</th>
						</tr>
						</thead>
						<tbody data-nested="false">
						<?php foreach ($this->items as $i => $item) :
							// Permissions
							$canEdit = $user->authorise('core.edit', 'com_engage');
							$canEditOwn = $user->authorise('core.edit.own', 'com_engage') && $item->created_by == $userId;
							$canChange = $user->authorise('core.edit.state', 'com_engage');
							// Author
							$creator  = UserFetcher::getUser($item->created_by);
							// Metadata
							$meta        = Meta::getAssetAccessMeta($item->asset_id, false);
							$numComments = Meta::getNumCommentsForAsset($item->asset_id);
							// IP and User Agent
							$ipLookupUrl = HTMLHelper::_('engage.getIPLookupURL', $item->ip);
							$jBrowser    = Browser::getInstance($item->user_agent);
							// Processed HTML comment (purified)
							$processedComment = $this->purifier->purify(HTMLHelper::_('engage.processCommentTextForDisplay', $item->body));
							$excerpt          = HTMLHelper::_('engage.textExcerpt', $processedComment);
							// IP address with word break opportunity tags
							$ip               = empty($item->ip) ? '' : str_replace([
								':', '::', '<wbr>:<wbr>:', '.',
							], ['<wbr>:', '<wbr>::', '<wbr>::', '<wbr>.'], $this->escape($item->ip));
							// Parent comment (if applicable)
							$parent       = null;
							$parentAuthor = null;

							if (!empty($item->parent_id))
							{
								try
								{
									/** @var CommentTable $parent */
									$parent = $this->getModel()->getTable('comment');

									if (!$parent->load($item->parent_id))
									{
										throw new RuntimeException('Parent not found.');
									}

									$public_uri = new Uri($meta['public_url']);
									$public_uri->setVar('akengage_cid', $parent->getId());
									$public_uri->setFragment('akengage-comment-' . $parent->getId());

									$parentUser   = UserFetcher::getUser($parent->created_by);
									$parentAuthor = empty($parentUser) ? '' : $parentUser->name;
									$parentAuthor = $parent->name ?: $parentAuthor;
								}
								catch (Exception $e)
								{
									echo $e->getMessage();
									echo "<pre>" . $e->getTraceAsString() . "</pre>";
									$parent = null;
								}
							}
							?>
							<tr class="row<?= $i % 2; ?> border-dark" style="border-bottom-width: 0.25rem">
								<td class="text-center">
									<?= HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->id); ?>
								</td>
								<td class="w-20 d-none d-md-table-cell">
									<div class="mb-2">
									<?= LayoutHelper::render('akeeba.engage.user', [
										'user_id'  => empty($item->name) ? $creator->id : 0,
										'username' => empty($item->name) ? $creator->username : '(guest)',
										'name'     => $item->user_name,
										'email'    => $item->user_email,
										'showLink' => empty($item->name) && empty($item->email),
									], JPATH_ADMINISTRATOR . '/components/com_engage/layout') ?>
									</div>

									<?php if (!empty($item->user_agent)): ?>
									<div class="mb-1">
										<div class="badge bg-info engage_user_agent--<?= $jBrowser->isMobile() ? 'mobile' : 'desktop' ?> hasTooltip <?= !empty($item->ip) ? 'flex-shrink-1' : '' ?>"
											 title="<?= Text::_('COM_ENGAGE_COMMENTS_LBL_BROWSERTYPE_' . ($jBrowser->isMobile() ? 'mobile' : 'desktop')) ?>"
										>
											<span class="fa fa-<?= $jBrowser->isMobile() ? 'mobile-alt' : 'desktop' ?>" aria-hidden="true"></span>
										</div>
										<span class="text-muted">
											<?= Text::_('COM_ENGAGE_COMMENTS_LBL_BROWSERTYPE_' . ($jBrowser->isMobile() ? 'mobile' : 'desktop')) ?>
										</span>
									</div>
									<?php endif; ?>

									<?php if (!empty($item->ip)): ?>
									<div class="d-flex flex-row flex-wrap gap-1">
										<div class="comEngageIpArea flex-grow-1 d-flex d-row gap-1">
											<div class="flex-shrink-1">
												<a href="<?= Route::_('index.php?option=com_engage&view=comments&filter_search=ip%3A' . urlencode($item->ip) . '&limitstart=0') ?>"
												   class="btn btn-outline-primary btn-sm hasTooltip"
												   title="<?= Text::_('JSEARCH_FILTER_LABEL') . ' ' . $this->escape($item->ip) ?>"
												>
													<span class="visually-hidden">
														<?= Text::_('JSEARCH_FILTER_LABEL') . ' ' . $this->escape($item->ip)?>
													</span>
													<span class="fa fa-search" aria-hidden="true"></span>
												</a>
											</div>
											<div class="engage_ip font-monospace text-success">
												<span class="visually-hidden">
													<?= Text::_('COM_ENGAGE_COMMENTS_FILTER_IP') ?>
												</span>
												<?php if (!empty($ipLookupUrl)): ?>
													<a href="<?= $ipLookupUrl ?>" class="comEngageLinkExternal link-success text-decoration-none hasTooltip"
													   title="<?= Text::_('COM_ENGAGE_COMMENTS_FILTER_IP') ?>"
													   target="_blank">
														<?= $ip ?>
													</a>
												<?php else: ?>
													<span class="hasTooltip" title="<?= Text::_('COM_ENGAGE_COMMENTS_FILTER_IP') ?>">
														<?= $ip ?>
													</span>
												<?php endif; ?>
											</div>
										</div>
									</div>
									<?php endif; ?>
								</td>
								<td>
									<?php // === LINE 1: ID, Edit button, In Reply To === ?>
									<div class="d-flex flex-row flex-wrap gap-2 mb-2">
										<div class="text-secondary">
											#&thinsp;<span class="fw-bold"><?php echo $item->id; ?></span>
										</div>

										<?php if (!is_null($parent) && !empty($parentAuthor)): ?>
											<div class="engage-in-reply-to text-muted mb-1">
												<?= Text::sprintf('COM_ENGAGE_COMMENTS_LBL_INREPLYTO', $public_uri->toString(), $parentAuthor) ?>
											</div>
										<?php endif; ?>

										<div class="flex-grow-1"></div>
										<div class="engage-edit-link">
											<a href="<?= Route::_('index.php?option=com_engage&view=comment&task=edit&id=' . $item->id) ?>"
											   class="btn btn-sm btn-primary text-decoration-none hasTooltip"
											   title="<?= Text::_('JGLOBAL_EDIT_ITEM') ?>"
											>
												<span class="fa fa-edit" aria-hidden="true"></span>
												<?= Text::_('JGLOBAL_EDIT_ITEM') ?>
											</a>
										</div>
									</div>

									<?php // === LINE 2: Comment text === ?>
									<div class="comEngageCommentPreview my-1">
										<?php if ($excerpt === $processedComment): ?>
										<?= $excerpt ?>
										<?php else: ?>
										<p>
											<?= $excerpt ?>
										</p>
										<div class="mb-2">
											<button type="button"
													class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#comEngageCommentModal<?= $item->id ?>">
												<?= Text::_('COM_ENGAGE_COMMENTS_FILTER_BODY') ?>
											</button>
										</div>
										<div class="modal" tabindex="-1" id="comEngageCommentModal<?= $item->id ?>">
											<div class="modal-dialog">
												<div class="modal-content">
													<div class="modal-header">
														<h3 class="modal-title">
															<?= Text::_('COM_ENGAGE_COMMENTS_FILTER_BODY') ?>
														</h3>
														<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
													</div>
													<div class="modal-body p-2">
														<?= $processedComment ?>
													</div>
													<div class="modal-footer">
														<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
															<?= Text::_('JCLOSE') ?>
														</button>
													</div>
												</div>
											</div>
										</div>
										<?php endif; ?>
									</div>

									<?php // === LINE 3: Content name === ?>

									<div class="small mb-1">
										<strong><?= Text::_('COM_ENGAGE_COMMENT_FIELD_ASSET_ID') ?></strong>:
										<?php if ($meta['type'] !== 'unknown'): ?>
											<a href="<?= $meta['url'] ?>">
												<?= $this->escape($meta['title']) ?>
											</a>
										<?php else: ?>
											<span class="badge bg-danger">
												<?= Text::_('COM_ENGAGE_COMMENTS_LBL_INVALIDORDELETED') ?>
											</span>
										<?php endif; ?>
									</div>

									<?php // === LINE 4: View content, number of comments === ?>

									<div class="d-flex flex-row flex-wrap gap-2 small">
										<div>
												<span aria-hidden="true" class="badge bg-info hasTooltip"
													  title="<?= Text::plural('COM_ENGAGE_COMMENTS_LBL_NUMCOMMENTS', $numComments) ?>"
												>
													<?= $numComments ?>
												</span>
												<span class="visually-hidden">
													<?= Text::plural('COM_ENGAGE_COMMENTS_LBL_NUMCOMMENTS', $numComments) ?>
												</span>

										</div>
										<div class="flex-grow-1">
											<?php if ($filteredAsset != $item->asset_id): ?>
											<a href="<?= Route::_('index.php?option=com_engage&view=comments&filter.asset_id=' . $item->asset_id . '&limitstart=0') ?>"
											   class="btn btn-sm btn-outline-secondary text-decoration-none"
											>
												<span aria-hidden="true" class="fa fa-filter"></span>
												<?= Text::_('COM_ENGAGE_COMMENTS_LBL_FILTER_BY_ARTICLE') ?>
											</a>
											<?php else: ?>
											<a href="<?= Route::_('index.php?option=com_engage&view=comments&filter.asset_id=&limitstart=0') ?>"
											   class="btn btn-sm btn-outline-danger text-decoration-none"
											>
												<span aria-hidden="true" class="fa fa-ban"></span>
												<?= Text::_('COM_ENGAGE_COMMENTS_LBL_UNFILTER_BY_ARTICLE') ?>
											</a>
											<?php endif; ?>
										</div>
										<?php if ($meta['type'] !== 'unknown'): ?>
										<div class="engage-content-link">
											<a href="<?= $meta['public_url'] ?>" target="_blank" class="hasTooltip"
											   title="<?= Text::_('COM_ENGAGE_COMMENTS_LBL_VIEWCONTENT_DESC') ?>">
												<?= Text::_('COM_ENGAGE_COMMENTS_LBL_VIEWCONTENT') ?>
											</a>
										</div>
										<?php endif; ?>
									</div>
								</td>
								<td class="w-20 d-none d-md-table-cell">
									<?= (new Date($item->created))->format(Text::_('DATE_FORMAT_LC2'), true, true) ?>
								</td>
								<td class="w-5 d-none d-md-table-cell text-center">
									<?php echo HTMLHelper::_('engage.published', $item->enabled, $i, 'comments.', $canChange, 'cb'); ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<?= $this->pagination->getListFooter(); ?>
				<?php endif; ?>

				<input type="hidden" name="task" value=""> <input type="hidden" name="boxchecked" value="0">
				<?= HTMLHelper::_('form.token'); ?>
			</div>
		</div>
	</div>
</form>