ALTER TABLE `profiles` ADD `deact_date` bigint(20) unsigned NOT NULL DEFAULT '0' AFTER `deleted`, ADD `deact_reason` text NULL AFTER `deact_date`;
