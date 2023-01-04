/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Convert tables to InnoDB
ALTER TABLE `#__engage_comments`
    ENGINE InnoDB;

ALTER TABLE `#__engage_unsubscribe`
    ENGINE InnoDB;

-- Convert tables to UTF8MB4
ALTER TABLE `#__engage_comments`
    DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci;
