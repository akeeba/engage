/**
 * @package   AkeebaEngage
 * @copyright Copyright (c)2020-2023 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

-- Currently used tables
DROP TABLE IF EXISTS `#__engage_comments`;
DROP TABLE IF EXISTS `#__engage_unsubscribe`;

-- Legacy table, just in case
DROP TABLE IF EXISTS `#__engage_emailtemplates`;

-- Mail templates
DELETE FROM `#__mail_templates` WHERE `extension` = 'com_engage';