/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

CREATE TABLE `#__engage_comments` (
    `id`          BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
    `parent_id`   BIGINT(20) unsigned NULL,
    `asset_id`    int(10) unsigned NOT NULL,
    `body`        longtext     NOT NULL,
    `name`        varchar(255) DEFAULT NULL,
    `email`       varchar(255) DEFAULT NULL,
    `ip`          varchar(64)  DEFAULT NULL,
    `user_agent`  varchar(255) NOT NULL,
    `enabled`     tinyint(3) NOT NULL DEFAULT '0',
    `created`     datetime     DEFAULT NULL,
    `created_by`  int(11) DEFAULT NULL,
    `modified`    datetime     DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY           `#__engage_comments_asset` (`asset_id`),
    KEY           `#__engage_comments_created_on` (`created` DESC)
) ENGINE InnoDB DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT='Content comments';

CREATE TABLE `#__engage_unsubscribe` (
    `asset_id` bigint(20) NOT NULL,
    `email`    varchar(255) NOT NULL,
    PRIMARY KEY (`asset_id`, `email`(100))
) ENGINE InnoDB DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci COMMENT='Unsubscribed emails';

DROP TABLE IF EXISTS `#__engage_emailtemplates`;