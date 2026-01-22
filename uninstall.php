<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once ABSPATH . 'wp-admin/includes/file.php';

global $wp_filesystem;

if (!isset($wp_filesystem)) {
    WP_Filesystem();
}

$loohood_options = [
    'loohood_github_token',
    'loohood_github_repo',
    'loohood_github_owner',
    'loohood_github_branch',
    'loohood_cloudflare_token',
    'loohood_cloudflare_account_id',
    'loohood_cloudflare_project',
    'loohood_cloudflare_project_id',
    'loohood_cloudflare_custom_domain',
    'loohood_cloudflare_bucket',
    'loohood_enable_cloudflare',
    'loohood_setup_completed',
    'loohood_auto_deploy'
];

foreach ($loohood_options as $loohood_option) {
    delete_option($loohood_option);
}

$loohood_export_dir = wp_upload_dir()['basedir'] . '/static-export';

if (is_dir($loohood_export_dir)) {
    if ($wp_filesystem) {
        $loohood_files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($loohood_export_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($loohood_files as $loohood_file) {
            $loohood_path = $loohood_file->getRealPath();
            if ($loohood_file->isDir()) {
                $wp_filesystem->rmdir($loohood_path);
            } else {
                $wp_filesystem->delete($loohood_path);
            }
        }

        $wp_filesystem->rmdir($loohood_export_dir);
    }
}

