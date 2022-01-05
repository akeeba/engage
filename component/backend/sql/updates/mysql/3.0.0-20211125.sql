/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Drop legacy tables
DROP TABLE IF EXISTS `#__engage_emailtemplates`;

-- Change column names and convert to InnoDB
ALTER TABLE `#__engage_comments` CHANGE `engage_comment_id` `id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `#__engage_comments` CHANGE `created_on` `created` datetime DEFAULT NULL;
ALTER TABLE `#__engage_comments` CHANGE `modified_on` `modified` datetime DEFAULT NULL;
ALTER TABLE `#__engage_comments` ENGINE InnoDB;

-- Convert tables to InnoDB
ALTER TABLE `#__engage_unsubscribe` ENGINE InnoDB;