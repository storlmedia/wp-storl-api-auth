<?php

defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();

// no empty lines within a create table statement

$sql = <<<SQL

CREATE TABLE {$wpdb->prefix}storl_user_mappings (
    user_id BIGINT UNSIGNED NOT NULL,
	external_user_id VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT NOW() NOT NULL,
	PRIMARY KEY  user_id (user_id),
	INDEX (external_user_id(36))
) $charset_collate;

SQL;

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

dbDelta($sql);
