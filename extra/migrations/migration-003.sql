CREATE TABLE `browsers`( `id` INT(11) NOT NULL AUTO_INCREMENT, `name` VARCHAR(255), `version` VARCHAR(255), PRIMARY KEY (`id`) );
ALTER TABLE `browsers` ADD UNIQUE INDEX (`name`, `version`);

CREATE TABLE `os`( `id` INT(11) NOT NULL AUTO_INCREMENT, `architecture` VARCHAR(255), `family` VARCHAR(255), `version` VARCHAR(255), PRIMARY KEY (`id`), UNIQUE INDEX (`architecture`, `family`, `version`) );

ALTER TABLE `totals` DROP COLUMN `browser_version`, CHANGE `browser_name` `browser_entry_id` INT(11) NULL;
ALTER TABLE `totals` CHANGE `metric_type` `metric_type` ENUM('opsPerSec','custom') DEFAULT 'opsPerSec' NULL;
ALTER TABLE `totals` ADD COLUMN `os_entry_id` INT(11) NULL AFTER `browser_entry_id`;
