<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$options = [
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

foreach ($options as $option) {
    delete_option($option);
}

$export_dir = wp_upload_dir()['basedir'] . '/static-export';

if (is_dir($export_dir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($export_dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            @rmdir($file->getRealPath());
        } else {
            @unlink($file->getRealPath());
        }
    }

    @rmdir($export_dir);
}
