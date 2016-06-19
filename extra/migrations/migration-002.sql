ALTER TABLE `jsbdb`.`reports_opsPerSec_per_browserVersion` ADD COLUMN `metric_type` VARCHAR(32) NULL AFTER `browser_version`;
RENAME TABLE `jsbdb`.`reports_opsPerSec_per_browserVersion` TO `jsbdb`.`totals`;
update totals set metric_type = 'opsPerSec';