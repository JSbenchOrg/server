ALTER TABLE `totals` ADD UNIQUE INDEX `INTEGRITY_CHECK_FOR_TOTALS` (`entry_id`, `browser_entry_id`, `os_entry_id`, `metric_type`);
