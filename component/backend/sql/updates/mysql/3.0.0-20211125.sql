/*
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Drop legacy tables
DROP TABLE IF EXISTS `#__engage_emailtemplates`;

-- Convert sole UNIQUE constraints to PRIMARY KEYs
SET @akeebaWorkaroundQuery=if((
  SELECT true FROM `INFORMATION_SCHEMA`.`STATISTICS` WHERE
          `INDEX_SCHEMA` = DATABASE() AND
          `TABLE_NAME`        = '#__engage_unsubscribe' AND
          `INDEX_NAME` = '#__engage_unsubscribe_unique'
) = true,'ALTER TABLE `#__engage_unsubscribe` DROP KEY `#__engage_unsubscribe_unique`;','SELECT 1');

PREPARE stmt FROM @akeebaWorkaroundQuery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @akeebaWorkaroundQuery=if((
  SELECT true FROM `INFORMATION_SCHEMA`.`STATISTICS` WHERE
          `INDEX_SCHEMA` = DATABASE() AND
          `TABLE_NAME`        = '#__engage_unsubscribe' AND
          `INDEX_NAME` = 'PRIMARY'
  LIMIT 0,1
) = true,'SELECT 1','ALTER TABLE `#__engage_unsubscribe` ADD PRIMARY KEY (`asset_id`,`email`);');

PREPARE stmt FROM @akeebaWorkaroundQuery;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Change column names and convert to InnoDB
ALTER TABLE `#__engage_comments` CHANGE `engage_comment_id` `id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `#__engage_comments` CHANGE `created_on` `created` datetime DEFAULT NULL;
ALTER TABLE `#__engage_comments` CHANGE `modified_on` `modified` datetime DEFAULT NULL;
ALTER TABLE `#__engage_comments` ENGINE InnoDB;

-- Convert tables to InnoDB
ALTER TABLE `#__engage_unsubscribe` ENGINE InnoDB;