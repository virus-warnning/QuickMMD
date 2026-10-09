<?php
/**
 * Phan configuration for QuickMMD extension.
 * Uses mediawiki/mediawiki-phan-config for MediaWiki core type stubs.
 */

require_once __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

return [
	// Directories that contain project-specific files
	'directory_list' => [
		'src',
	],

	// Files to exclude from analysis
	'exclude_analysis_file_list' => [
	],

	// Target PHP version
	'target_php_version' => '8.1',
];
