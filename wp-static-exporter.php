<?php
/**
 * Plugin Name: LooHood
 * Plugin URI: https://loohood.web.id/
 * Description: Export WordPress pages as static HTML and deploy to GitHub & Cloudflare with automated setup wizard
 * Version: 1.0.0
 * Author: Lukman Bagus
 * Author URI: https://loohood.web.id/
 * License: GPL v2
 * Text Domain: loohood
 */

defined('ABSPATH') || exit;

/**
 * Render plugin header template.
 *
 * @param string $subtitle Subtitle text to display.
 */
function loohood_render_plugin_header( $subtitle ) {
    $subtitle_text = is_string( $subtitle ) ? trim( $subtitle ) : '';
    $dashboard_url = admin_url( 'admin.php?page=wp-static-exporter' );
    $settings_url  = admin_url( 'admin.php?page=wp-static-exporter-settings' );

    include plugin_dir_path( __FILE__ ) . 'templates/inc/header.php';
}

class WP_Static_Exporter {
    private static $instance = null;
    private $plugin_dir;
    private $plugin_url;
    private $export_dir;
    private $git_path;

    private function getGitPath() {
        $candidates = array_filter([
            $this->git_path,
            '/usr/bin/git',
            '/opt/homebrew/bin/git',
            '/usr/local/bin/git'
        ]);

        foreach ($candidates as $path) {
            if ($path === 'git') {
                return $path;
            }
            if (@is_file($path) && @is_executable($path)) {
                return $path;
            }
        }

        return 'git';
    }

    private function buildGitHubRepoUrlWithToken($owner, $repo, $token) {
        $user = rawurlencode((string) $owner);
        $pass = rawurlencode((string) $token);
        $repo_owner = rawurlencode((string) $owner);
        $repo_name = rawurlencode((string) $repo);

        return "https://{$user}:{$pass}@github.com/{$repo_owner}/{$repo_name}.git";
    }

    private function redactSensitive($text) {
        $token = get_option('loohood_github_token');
        if (empty($token) || !is_string($text)) {
            return $text;
        }

        $encoded = rawurlencode($token);
        return str_replace([$token, $encoded], '***', $text);
    }

    private function __construct() {
        $this->plugin_dir = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->export_dir = get_option('loohood_export_dir', wp_upload_dir()['basedir'] . '/wp-exporter-result');
        $this->git_path = get_option('loohood_git_path', '/usr/bin/git');

        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_ajax_loohood_run_setup', [$this, 'runSetupWizard']);
        add_action('wp_ajax_loohood_stream_deploy', [$this, 'streamDeploy']);
        add_action('wp_ajax_loohood_get_deploy_logs', [$this, 'getDeployLogs']);
        add_action('wp_ajax_loohood_add_custom_domain', [$this, 'addCustomDomain']);
        add_action('wp_ajax_loohood_update_token', [$this, 'updateToken']);
        add_action('save_post', [$this, 'triggerOnPostUpdate'], 10, 3);
        add_action('transition_post_status', [$this, 'triggerOnPublish'], 10, 3);
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addAdminMenu() {
        add_menu_page(
            'LooHood',
            'Static Exporter',
            'manage_options',
            'wp-static-exporter',
            [$this, 'renderAdminPage'],
            'dashicons-cloud',
            30
        );

        add_submenu_page(
            'wp-static-exporter',
            'Settings',
            'Settings',
            'manage_options',
            'wp-static-exporter-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function registerSettings() {
        register_setting('loohood_settings', 'loohood_github_token');
        register_setting('loohood_settings', 'loohood_github_repo');
        register_setting('loohood_settings', 'loohood_github_owner');
        register_setting('loohood_settings', 'loohood_github_branch', ['default' => 'main']);
        register_setting('loohood_settings', 'loohood_repo_cloned', ['default' => 0]);
        register_setting('loohood_settings', 'loohood_git_configured', ['default' => 0]);
        register_setting('loohood_settings', 'loohood_auto_deploy_enabled', ['default' => 0]);
        register_setting('loohood_settings', 'loohood_cloudflare_token');
        register_setting('loohood_settings', 'loohood_cloudflare_account_id');
        register_setting('loohood_settings', 'loohood_cloudflare_project');
        register_setting('loohood_settings', 'loohood_cloudflare_project_id');
        register_setting('loohood_settings', 'loohood_cloudflare_custom_domain');
        register_setting('loohood_settings', 'loohood_setup_completed', ['default' => 0]);
        register_setting('loohood_settings', 'loohood_auto_deploy', ['default' => 1]);
        register_setting('loohood_settings', 'loohood_deploy_logs', ['default' => []]);
        register_setting('loohood_settings', 'loohood_git_path', ['default' => '/usr/bin/git']);
        register_setting('loohood_settings', 'loohood_not_found_target_type', ['default' => '404']);
        register_setting('loohood_settings', 'loohood_not_found_target_page_id', ['default' => 0]);
    }

    public function enqueueScripts($hook) {
        if (strpos($hook, 'wp-static-exporter') !== false) {
            wp_enqueue_script('loohood-tailwind', 'https://cdn.tailwindcss.com?plugins=forms,typography,container-queries', [], null, false);
            wp_enqueue_style('loohood-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap', [], null);
            wp_enqueue_style('loohood-material-symbols', 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap', [], null);

            wp_enqueue_script('loohood-admin', $this->plugin_url . 'assets/admin.js', ['jquery'], '1.0.0', true);

            $github_owner = get_option('loohood_github_owner');
            $github_repo = get_option('loohood_github_repo');
            $github_url = ($github_owner && $github_repo) ? ('https://github.com/' . $github_owner . '/' . $github_repo) : '';

            $preview_url = $this->getCloudflarePagesPreviewUrlFromOptions();
            $custom_domain = get_option('loohood_cloudflare_custom_domain');
            $cloudflare_url = !empty($custom_domain) ? ('https://' . $custom_domain) : $preview_url;

            wp_localize_script('loohood-admin', 'loohoodAjax', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('loohood_nonce'),
                'githubToken' => get_option('loohood_github_token') ? 1 : 0,
                'githubRepo' => get_option('loohood_github_repo') ? 1 : 0,
                'repoCloned' => get_option('loohood_repo_cloned') ? 1 : 0,
                'gitConfigured' => get_option('loohood_git_configured') ? 1 : 0,
                'autoDeploy' => get_option('loohood_auto_deploy_enabled') ? 1 : 0,
                'cloudflareToken' => get_option('loohood_cloudflare_token') ? 1 : 0,
                'cloudflareProject' => get_option('loohood_cloudflare_project') ? 1 : 0,
                'githubConnected' => (get_option('loohood_github_token') && get_option('loohood_github_owner')) ? 1 : 0,
                'githubRepoReady' => ($github_owner && $github_repo && get_option('loohood_repo_cloned') && get_option('loohood_git_configured')) ? 1 : 0,
                'cloudflareConnected' => (get_option('loohood_cloudflare_token') && get_option('loohood_cloudflare_account_id')) ? 1 : 0,
                'cloudflareProjectReady' => (get_option('loohood_cloudflare_project') && get_option('loohood_cloudflare_project_id')) ? 1 : 0,
                'githubUrl' => $github_url,
                'cloudflarePreviewUrl' => $preview_url,
                'cloudflareUrl' => $cloudflare_url,
                'customDomain' => $custom_domain ? (string) $custom_domain : ''
            ]);
        }
    }

    public function renderAdminPage() {
        if (!get_option('loohood_setup_completed')) {
            require_once $this->plugin_dir . 'templates/wizard-page.php';
            return;
        }

        if (isset($_POST['loohood_export'])) {
            check_admin_referer('loohood_export_nonce');
            $this->exportAndDeploy();
        }

        if (isset($_POST['loohood_disconnect'])) {
            check_admin_referer('loohood_disconnect_nonce');
            $this->disconnectServices();
        }

        if (isset($_POST['loohood_save_settings'])) {
            check_admin_referer('loohood_settings_nonce');
            if (isset($_POST['loohood_git_path'])) {
                update_option('loohood_git_path', sanitize_text_field($_POST['loohood_git_path']));
                echo wp_kses( '<div class="notice notice-success is-dismissible"><p>Settings saved!</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
            }
        }

        require_once $this->plugin_dir . 'templates/admin-page.php';
    }

    public function renderSettingsPage() {
        if (isset($_POST['loohood_save_auto_deploy_settings'])) {
            check_admin_referer('loohood_auto_deploy_settings_nonce');

            $enabled = isset($_POST['loohood_auto_deploy_enabled']) ? 1 : 0;
            update_option('loohood_auto_deploy_enabled', $enabled);
            update_option('loohood_auto_deploy', $enabled);

            echo wp_kses( '<div class="notice notice-success is-dismissible"><p>Auto deploy setting saved!</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
        }

        if (isset($_POST['loohood_save_not_found_settings'])) {
            check_admin_referer('loohood_not_found_settings_nonce');

            $type = isset($_POST['loohood_not_found_target_type']) ? sanitize_text_field($_POST['loohood_not_found_target_type']) : '404';
            if (!in_array($type, ['404', 'home', 'page'], true)) {
                $type = '404';
            }

            $page_id = isset($_POST['loohood_not_found_target_page_id']) ? intval($_POST['loohood_not_found_target_page_id']) : 0;
            if ($type !== 'page') {
                $page_id = 0;
            } else {
                $post = $page_id > 0 ? get_post($page_id) : null;
                if (!($post instanceof WP_Post) || $post->post_type !== 'page' || $post->post_status !== 'publish') {
                    $page_id = 0;
                }
            }

            update_option('loohood_not_found_target_type', $type);
            update_option('loohood_not_found_target_page_id', $page_id);

            echo wp_kses( '<div class="notice notice-success is-dismissible"><p>Not found redirect setting saved!</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
        }

        if (isset($_POST['loohood_save_custom_assets_settings'])) {
            check_admin_referer('loohood_custom_assets_settings_nonce');

            $custom_assets = isset($_POST['loohood_custom_asset_paths']) ? sanitize_textarea_field($_POST['loohood_custom_asset_paths']) : '';
            update_option('loohood_custom_asset_paths', $custom_assets);

            echo wp_kses( '<div class="notice notice-success is-dismissible"><p>Custom asset paths saved!</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
        }

        require_once $this->plugin_dir . 'templates/settings-page.php';
    }

    public function exportAndDeploy() {
        try {
            $this->exportToStatic();
            $github_result = $this->pushToGitHub();

            echo wp_kses( '<div class="notice notice-success is-dismissible"><p>Export &amp; Deploy successful! Cloudflare Pages will automatically deploy from GitHub.</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
        } catch (Exception $e) {
            echo wp_kses( '<div class="notice notice-error is-dismissible"><p>Error: ' . esc_html( $e->getMessage() ) . '</p></div>', array( 'div' => array( 'class' => array() ), 'p' => array() ) );
        }
    }

    public function runSetupWizard() {
        error_log('WPSE: runSetupWizard called');

        if (!current_user_can('manage_options')) {
            error_log('WPSE: Permission denied - user does not have manage_options capability');
            wp_send_json_error(['message' => 'Permission denied']);
        }

        check_ajax_referer('loohood_nonce', 'nonce', true);

        $step = isset($_POST['step']) ? intval($_POST['step']) : 1;

        try {
            switch ($step) {
                case 1:
                    $result = $this->connectGitHub();
                    break;
                case 2:
                    $result = $this->createGitHubRepo();
                    break;
                case 3:
                    $result = $this->connectCloudflare();
                    break;
                case 4:
                    $result = $this->createCloudflareProject();
                    break;
                default:
                    throw new Exception('Invalid step');
            }

            wp_send_json_success($result);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function streamDeploy() {
        error_log('WPSE: streamDeploy called');

        if (!current_user_can('manage_options')) {
            error_log('WPSE: Permission denied - user does not have manage_options capability');
            wp_send_json_error(['message' => 'Permission denied']);
        }

        check_ajax_referer('loohood_nonce', 'nonce', true);

        error_log('WPSE: Starting deployment process');

        try {
            error_log('WPSE: Clearing deploy logs');
            $this->clearDeployLogs();
            $this->addDeployLog('info', 'Starting deployment...');

            $this->export_dir = get_option('loohood_export_dir', wp_upload_dir()['basedir'] . '/wp-exporter-result');
            $this->git_path = get_option('loohood_git_path', '/usr/bin/git');

            $token = get_option('loohood_github_token');
            $owner = get_option('loohood_github_owner');
            $repo = get_option('loohood_github_repo');

            if (empty($token) || empty($owner) || empty($repo)) {
                throw new Exception('GitHub settings not configured');
            }

            $uploads_dir = wp_upload_dir()['basedir'];
            $expected_clone_dir = $uploads_dir . '/' . $repo;

            if (is_dir($expected_clone_dir . '/.git')) {
                update_option('loohood_repo_cloned', 1);
                update_option('loohood_export_dir', $expected_clone_dir);
                $this->export_dir = $expected_clone_dir;
            } else {
                $this->addDeployLog('info', 'Repository not cloned, cloning to /wp-content/uploads/' . $repo . ' ...');
                $this->cloneRepository();
                $this->export_dir = get_option('loohood_export_dir', $expected_clone_dir);
                $this->addDeployLog('success', 'Repository cloned successfully');
            }

            error_log('WPSE: Calling exportToStatic()');
            $this->exportToStatic();
            $this->addDeployLog('success', 'Static files exported successfully');

            error_log('WPSE: Calling pushToGitHub()');
            $github_result = $this->pushToGitHub();
            $this->addDeployLog('success', 'Pushed to GitHub successfully');

            try {
                $this->triggerCloudflareDeployment();
            } catch (Exception $e) {
                $this->addDeployLog('warning', 'Cloudflare deployment not triggered: ' . $e->getMessage());
            }

            $this->addDeployLog('success', 'Deployment completed successfully!');
            $this->addDeployLog('info', 'Cloudflare Pages will deploy from GitHub');

            error_log('WPSE: Deployment completed successfully');
            wp_send_json_success(['message' => 'Deployment completed']);
        } catch (Exception $e) {
            error_log('WPSE: Exception caught: ' . $e->getMessage());
            error_log('WPSE: Exception trace: ' . $e->getTraceAsString());
            $this->addDeployLog('error', 'Error: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function getDeployLogs() {
        error_log('WPSE: getDeployLogs called');

        if (!current_user_can('manage_options')) {
            error_log('WPSE: Permission denied - user does not have manage_options capability');
            wp_send_json_error(['message' => 'Permission denied']);
        }

        check_ajax_referer('loohood_nonce', 'nonce', true);

        $logs = get_option('loohood_deploy_logs', []);
        wp_send_json_success(['logs' => $logs]);
    }

    private function addDeployLog($type, $message) {
        $logs = get_option('loohood_deploy_logs', []);
        $logs[] = [
            'type' => $type,
            'message' => $message,
            'timestamp' => time()
        ];
        update_option('loohood_deploy_logs', $logs);
    }

    private function clearDeployLogs() {
        delete_option('loohood_deploy_logs');
    }

    private function acquireAutoDeployLock() {
        $lock = get_transient('loohood_auto_deploy_lock');
        if ($lock) {
            return false;
        }
        set_transient('loohood_auto_deploy_lock', 1, 15 * MINUTE_IN_SECONDS);
        return true;
    }

    private function releaseAutoDeployLock() {
        delete_transient('loohood_auto_deploy_lock');
    }

    public function triggerOnPostUpdate($post_id, $post, $update) {
        if (!get_option('loohood_auto_deploy_enabled')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
            return;
        }

        if (!($post instanceof WP_Post)) {
            $post = get_post($post_id);
        }

        if (!($post instanceof WP_Post)) {
            return;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post->post_type)) {
            return;
        }

        if ($post->post_status !== 'publish') {
            return;
        }

        $this->exportSinglePost($post_id);
    }

    public function triggerOnPublish($new_status, $old_status, $post) {
        if (!get_option('loohood_auto_deploy_enabled')) {
            return;
        }

        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        if (!($post instanceof WP_Post)) {
            return;
        }

        if (function_exists('is_post_type_viewable') && !is_post_type_viewable($post->post_type)) {
            return;
        }

        $this->exportSinglePost($post->ID);
    }

    private function exportSinglePost($post_id) {
        if (!$this->acquireAutoDeployLock()) {
            return;
        }

        try {
            $this->export_dir = get_option('loohood_export_dir', wp_upload_dir()['basedir'] . '/wp-exporter-result');
            $this->git_path = get_option('loohood_git_path', '/usr/bin/git');

            $repo = get_option('loohood_github_repo');
            if ($repo) {
                $uploads_dir = wp_upload_dir()['basedir'];
                $expected_clone_dir = $uploads_dir . '/' . $repo;

                if (is_dir($expected_clone_dir . '/.git')) {
                    update_option('loohood_repo_cloned', 1);
                    update_option('loohood_export_dir', $expected_clone_dir);
                    $this->export_dir = $expected_clone_dir;
                } else {
                    $this->addDeployLog('info', 'Repository not cloned, cloning to /wp-content/uploads/' . $repo . ' ...');
                    $this->cloneRepository();
                    $this->export_dir = get_option('loohood_export_dir', $expected_clone_dir);
                }
            }

            if (!is_dir($this->export_dir)) {
                wp_mkdir_p($this->export_dir);
            }

            $url = get_permalink($post_id);
            if (!is_string($url) || $url === '') {
                throw new Exception('Failed to resolve permalink for post ID: ' . intval($post_id));
            }

            $this->addDeployLog('info', 'Auto deploy triggered for URL: ' . $url);

            $fetched = $this->fetchUrl($url);
            if (!$fetched) {
                throw new Exception('Failed to export updated URL: ' . $url);
            }

            $this->pushToGitHub();

            try {
                $this->triggerCloudflareDeployment();
            } catch (Exception $e) {
                $this->addDeployLog('warning', 'Cloudflare deployment not triggered: ' . $e->getMessage());
            }

            error_log('LooHood: Auto-deploy completed for post ' . $post_id);
        } catch (Exception $e) {
            $this->addDeployLog('error', 'Auto deploy failed: ' . $e->getMessage());
            error_log('LooHood: Auto-deploy failed for post ' . $post_id . ': ' . $e->getMessage());
        } finally {
            $this->releaseAutoDeployLock();
        }
    }

    private function connectGitHub() {
        $logs = [];
        $token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';

        if (empty($token)) {
            throw new Exception('GitHub token is required');
        }

        $logs[] = ['type' => 'info', 'message' => 'Connecting to GitHub API endpoint...'];
        $logs[] = ['type' => 'info', 'message' => 'GET https://api.github.com/user'];

        $response = wp_remote_get('https://api.github.com/user', [
            'headers' => [
                'Authorization' => "token {$token}",
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ]);

        if (is_wp_error($response)) {
            $logs[] = ['type' => 'error', 'message' => 'Failed to connect to GitHub: ' . $response->get_error_message()];
            throw new Exception('Failed to connect to GitHub');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $logs[] = ['type' => 'info', 'message' => 'Response received: ' . ($body['login'] ?? 'unknown user')];

        if (isset($body['login'])) {
            update_option('loohood_github_token', $token);
            update_option('loohood_github_owner', $body['login']);

            $logs[] = ['type' => 'success', 'message' => 'GitHub token validated'];
            $logs[] = ['type' => 'success', 'message' => 'User: ' . $body['login']];

            return [
                'message' => 'GitHub connected successfully',
                'username' => $body['login'],
                'avatar' => $body['avatar_url'],
                'logs' => $logs
            ];
        }

        $logs[] = ['type' => 'error', 'message' => 'Invalid GitHub token'];
        throw new Exception('Invalid GitHub token');
    }

    private function createGitHubRepo() {
        $logs = [];
        $token = get_option('loohood_github_token');
        $owner = get_option('loohood_github_owner');
        $repo_name = isset($_POST['repo_name']) ? sanitize_text_field($_POST['repo_name']) : 'wp-static-' . time();

        $logs[] = ['type' => 'info', 'message' => 'Creating repository: ' . $repo_name];
        $logs[] = ['type' => 'info', 'message' => 'POST https://api.github.com/user/repos'];

        $response = wp_remote_post("https://api.github.com/user/repos", [
            'headers' => [
                'Authorization' => "token {$token}",
                'Accept' => 'application/vnd.github.v3+json',
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $repo_name,
                'description' => 'WordPress Static Export - Generated by LooHood',
                'private' => true,
                'auto_init' => true
            ])
        ]);

        if (is_wp_error($response)) {
            $logs[] = ['type' => 'error', 'message' => 'Failed to create repository: ' . $response->get_error_message()];
            throw new Exception('Failed to create GitHub repository');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['full_name'])) {
            update_option('loohood_github_repo', $body['name']);
            update_option('loohood_github_branch', $body['default_branch'] ?? 'main');

            $logs[] = ['type' => 'success', 'message' => 'Repository created: ' . $body['full_name']];
            $logs[] = ['type' => 'success', 'message' => 'Default branch: ' . ($body['default_branch'] ?? 'main')];

            $logs[] = ['type' => 'info', 'message' => 'Cloning newly created repository...'];
            $clone_result = $this->cloneRepository();
            if (!empty($clone_result['logs']) && is_array($clone_result['logs'])) {
                $logs = array_merge($logs, $clone_result['logs']);
            }

            $logs[] = ['type' => 'info', 'message' => 'Creating index.html and pushing initial commit...'];
            $git_result = $this->configureExportAndGit();
            if (!empty($git_result['logs']) && is_array($git_result['logs'])) {
                $logs = array_merge($logs, $git_result['logs']);
            }

            return [
                'message' => 'Repository created, cloned, and initialized successfully',
                'repo_name' => $body['full_name'],
                'clone_url' => $body['clone_url'],
                'html_url' => $body['html_url'],
                'github_url' => $body['html_url'],
                'project_name' => $body['name'] . '-pages',
                'clone_dir' => $clone_result['clone_dir'] ?? null,
                'repo_cloned' => !empty($clone_result['clone_dir']),
                'git_configured' => 1,
                'logs' => $logs
            ];
        }

        if (isset($body['message'])) {
            $logs[] = ['type' => 'error', 'message' => 'GitHub API Error: ' . $body['message']];
            throw new Exception( esc_html( $body['message'] ) );
        }

        throw new Exception('Failed to create repository');
    }

    private function cloneRepository() {
        $logs = [];
        $token = get_option('loohood_github_token');
        $owner = get_option('loohood_github_owner');
        $repo = get_option('loohood_github_repo');
        $branch = get_option('loohood_github_branch', 'main');
        $uploads_dir = wp_upload_dir()['basedir'];
        $git_path = $this->getGitPath();

        if (empty($token) || empty($owner) || empty($repo)) {
            throw new Exception('GitHub settings not configured');
        }

        if (empty($uploads_dir) || !is_dir($uploads_dir)) {
            throw new Exception('Uploads directory not found');
        }

        if (!is_writable($uploads_dir)) {
            throw new Exception('Uploads directory is not writable');
        }

        $repo_name = $repo;
        $clone_dir = $uploads_dir . '/' . $repo_name;

        $logs[] = ['type' => 'info', 'message' => 'Git binary: ' . $git_path];
        $version = $this->execCommand("'{$git_path}' --version 2>&1", true);
        if (!empty($version['output'])) {
            $logs[] = ['type' => 'info', 'message' => 'Git version: ' . $this->redactSensitive($version['output'])];
        }

        $logs[] = ['type' => 'info', 'message' => 'Cloning repository to: ' . $clone_dir];
        $logs[] = ['type' => 'info', 'message' => 'Repository: ' . $owner . '/' . $repo];

        if (is_dir($clone_dir)) {
            $logs[] = ['type' => 'warning', 'message' => 'Directory already exists, removing...'];
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($clone_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    @rmdir($item->getPathname());
                } else {
                    @unlink($item->getPathname());
                }
            }
            @rmdir($clone_dir);
        }

        $clone_url = $this->buildGitHubRepoUrlWithToken($owner, $repo, $token);

        $logs[] = ['type' => 'info', 'message' => 'Executing: git clone'];

        $output = $this->execCommand("cd '{$uploads_dir}' && '{$git_path}' clone '{$clone_url}' '{$repo_name}' 2>&1", true);

        if ($output['return_code'] !== 0) {
            $safe_output = $this->redactSensitive($output['output']);
            $logs[] = ['type' => 'error', 'message' => 'Git clone failed: ' . $safe_output];
            throw new Exception('Failed to clone repository: ' . esc_html( $safe_output ));
        }

        if (!is_dir($clone_dir)) {
            $logs[] = ['type' => 'error', 'message' => 'Clone directory does not exist'];
            throw new Exception('Clone directory was not created');
        }

        update_option('loohood_repo_cloned', 1);
        update_option('loohood_export_dir', $clone_dir);

        $logs[] = ['type' => 'success', 'message' => 'Repository cloned successfully'];
        $logs[] = ['type' => 'success', 'message' => 'Export directory: ' . $clone_dir];

        return [
            'message' => 'Repository cloned successfully',
            'clone_dir' => $clone_dir,
            'logs' => $logs
        ];
    }

    private function configureExportAndGit() {
        $logs = [];
        $token = get_option('loohood_github_token');
        $owner = get_option('loohood_github_owner');
        $repo = get_option('loohood_github_repo');
        $branch = get_option('loohood_github_branch', 'main');
        $export_dir = get_option('loohood_export_dir');
        $git_path = $this->getGitPath();

        if (empty($export_dir) || !is_dir($export_dir)) {
            throw new Exception('Export directory not found. Please clone repository first.');
        }

        $logs[] = ['type' => 'info', 'message' => 'Configuring Git in: ' . $export_dir];

        $repo_url = $this->buildGitHubRepoUrlWithToken($owner, $repo, $token);

        $logs[] = ['type' => 'info', 'message' => 'Configuring Git user...'];
        $this->execCommand("cd '{$export_dir}' && '{$git_path}' config user.name 'WordPress Static Exporter'", true);
        $this->execCommand("cd '{$export_dir}' && '{$git_path}' config user.email 'wordpress@localhost'", true);

        $logs[] = ['type' => 'info', 'message' => 'Checking Git remote...'];
        $output = $this->execCommand("cd '{$export_dir}' && '{$git_path}' remote get-url origin 2>&1", true);
        
        if ($output['return_code'] !== 0) {
            $logs[] = ['type' => 'info', 'message' => 'Setting Git remote...'];
            $this->execCommand("cd '{$export_dir}' && '{$git_path}' remote add origin '{$repo_url}' 2>&1", true);
        } elseif (strpos($output['output'], 'github.com') === false) {
            $logs[] = ['type' => 'info', 'message' => 'Setting Git remote...'];
            $this->execCommand("cd '{$export_dir}' && '{$git_path}' remote set-url origin '{$repo_url}' 2>&1", true);
        }

        $logs[] = ['type' => 'info', 'message' => 'Creating index.html...'];
        file_put_contents(
            $export_dir . '/index.html',
            "<!doctype html>\n<html lang=\"en\">\n<head>\n  <meta charset=\"utf-8\" />\n  <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\" />\n  <title>Coming Soon</title>\n</head>\n<body>\n  <h1>Coming Soon</h1>\n</body>\n</html>\n"
        );

        $logs[] = ['type' => 'info', 'message' => 'Adding files to Git...'];
        $this->execCommand("cd '{$export_dir}' && '{$git_path}' add . 2>&1", true);

        $logs[] = ['type' => 'info', 'message' => 'Creating initial commit...'];
        $output = $this->execCommand("cd '{$export_dir}' && '{$git_path}' commit -m 'Initial commit from WordPress Static Exporter' 2>&1", true);

        if ($output['return_code'] !== 0 && strpos($output['output'], 'nothing to commit') === false) {
            $logs[] = ['type' => 'error', 'message' => 'Commit failed: ' . $output['output']];
            throw new Exception('Failed to create commit: ' . esc_html( $output['output'] ));
        }

        $logs[] = ['type' => 'info', 'message' => 'Pushing to GitHub...'];
        $output = $this->execCommand("cd '{$export_dir}' && '{$git_path}' push -u origin {$branch} 2>&1", true);

        if ($output['return_code'] !== 0) {
            $logs[] = ['type' => 'error', 'message' => 'Push failed: ' . $output['output']];
            throw new Exception('Failed to push to GitHub: ' . esc_html( $output['output'] ));
        }

        update_option('loohood_git_configured', 1);

        $logs[] = ['type' => 'success', 'message' => 'Git configured successfully'];
        $logs[] = ['type' => 'success', 'message' => 'Export directory ready: ' . $export_dir];

        return [
            'message' => 'Export and Git configured successfully',
            'export_dir' => $export_dir,
            'logs' => $logs
        ];
    }

    private function setupAutoDeploy() {
        $logs = [];

        $logs[] = ['type' => 'info', 'message' => 'Setting up auto-deploy triggers...'];
        $logs[] = ['type' => 'info', 'message' => 'Enabling hooks: save_post, transition_post_status'];
        $logs[] = ['type' => 'info', 'message' => 'Auto-deploy will trigger on post/page publish'];

        update_option('loohood_auto_deploy_enabled', 1);
        update_option('loohood_auto_deploy', 1);

        $logs[] = ['type' => 'success', 'message' => 'Auto-deploy triggers configured'];
        $logs[] = ['type' => 'success', 'message' => 'Posts and pages will auto-parse and push on publish'];

        return [
            'message' => 'Auto-deploy setup completed',
            'logs' => $logs
        ];
    }

    private function connectCloudflare() {
        $logs = [];
        $token = isset($_POST['cloudflare_token']) ? sanitize_text_field($_POST['cloudflare_token']) : '';

        if (empty($token)) {
            throw new Exception('Cloudflare token is required');
        }

        $logs[] = ['type' => 'info', 'message' => 'Verifying Cloudflare API token...'];

        $response = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ]
        ]);

        if (is_wp_error($response)) {
            $logs[] = ['type' => 'error', 'message' => 'Failed to connect to Cloudflare: ' . $response->get_error_message()];
            throw new Exception('Failed to connect to Cloudflare');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['success']) && $body['success']) {
            update_option('loohood_cloudflare_token', $token);

            $accounts_response = wp_remote_get('https://api.cloudflare.com/client/v4/accounts', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $accounts_body = json_decode(wp_remote_retrieve_body($accounts_response), true);

            if (isset($accounts_body['success']) && $accounts_body['success'] && !empty($accounts_body['result'])) {
                update_option('loohood_cloudflare_account_id', $accounts_body['result'][0]['id']);
                $account_name = $accounts_body['result'][0]['name'];

                $logs[] = ['type' => 'success', 'message' => 'Cloudflare token validated'];
                $logs[] = ['type' => 'success', 'message' => 'Account: ' . $account_name];

                return [
                    'message' => 'Cloudflare connected successfully',
                    'account_name' => $account_name,
                    'logs' => $logs
                ];
            }

            throw new Exception('No Cloudflare accounts found');
        }

        $logs[] = ['type' => 'error', 'message' => 'Invalid Cloudflare token'];
        throw new Exception('Invalid Cloudflare token');
    }

    private function createCloudflareProject() {
        $logs = [];
        $token = get_option('loohood_cloudflare_token');
        $account_id = get_option('loohood_cloudflare_account_id');
        $owner = get_option('loohood_github_owner');
        $repo = get_option('loohood_github_repo');
        $project_name = isset($_POST['project_name']) ? sanitize_text_field($_POST['project_name']) : $repo . '-pages';
        $custom_domain_raw = isset($_POST['custom_domain']) ? sanitize_text_field($_POST['custom_domain']) : '';
        $custom_domain = $this->normalizeCloudflareCustomDomain($custom_domain_raw);

        $logs[] = ['type' => 'info', 'message' => 'Creating Cloudflare Pages project: ' . $project_name];
        $logs[] = ['type' => 'info', 'message' => 'GitHub repo: ' . $owner . '/' . $repo];

        $payload = [
            'name' => $project_name,
            'production_branch' => 'main',
            'source' => [
                'type' => 'github',
                'config' => [
                    'owner' => $owner,
                    'repo_name' => $repo,
                    'production_branch' => 'main'
                ]
            ]
        ];

        $attemptCreate = function($attempt_account_id) use ($token, $payload) {
            $response = wp_remote_post("https://api.cloudflare.com/client/v4/accounts/{$attempt_account_id}/pages/projects", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode($payload)
            ]);

            if (is_wp_error($response)) {
                return [
                    'status_code' => 0,
                    'body' => null,
                    'wp_error' => $response
                ];
            }

            return [
                'status_code' => wp_remote_retrieve_response_code($response),
                'body' => json_decode(wp_remote_retrieve_body($response), true),
                'wp_error' => null
            ];
        };

        $accounts_tried = [];
        $result = $attemptCreate($account_id);
        $accounts_tried[] = $account_id;

        if (!empty($result['wp_error'])) {
            $logs[] = ['type' => 'error', 'message' => 'Failed to create project: ' . $result['wp_error']->get_error_message()];
            throw new Exception('Failed to create Cloudflare Pages project: ' . esc_html( $result['wp_error']->get_error_message() ));
        }

        $status_code = (int) $result['status_code'];
        $body = is_array($result['body']) ? $result['body'] : [];

        $error_code = isset($body['errors'][0]['code']) ? (string) $body['errors'][0]['code'] : '';
        $error_msg = isset($body['errors'][0]['message']) ? (string) $body['errors'][0]['message'] : '';

        if (($error_code === '8000011' || stripos($error_msg, 'Cloudflare Pages Git installation') !== false) && !empty($token)) {
            $dashboard_url = 'https://dash.cloudflare.com/';
            if (!empty($account_id) && is_string($account_id)) {
                $dashboard_url = 'https://dash.cloudflare.com/' . rawurlencode($account_id) . '/workers-and-pages';
            }

            $logs[] = ['type' => 'error', 'message' => 'Cloudflare Pages Git installation is not ready for API project creation.'];
            $logs[] = ['type' => 'info', 'message' => 'Fix (recommended): Open Cloudflare dashboard → Pages → Connect to Git, then Install & Authorize the GitHub app.'];
            $logs[] = ['type' => 'info', 'message' => 'Cloudflare dashboard: ' . $dashboard_url];
            $logs[] = ['type' => 'info', 'message' => 'GitHub App installations (personal): https://github.com/settings/installations'];

            $accounts_response = wp_remote_get('https://api.cloudflare.com/client/v4/accounts', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (!is_wp_error($accounts_response)) {
                $accounts_body = json_decode(wp_remote_retrieve_body($accounts_response), true);
                if (isset($accounts_body['success']) && $accounts_body['success'] && !empty($accounts_body['result']) && is_array($accounts_body['result'])) {
                    foreach ($accounts_body['result'] as $acct) {
                        if (empty($acct['id']) || !is_string($acct['id'])) {
                            continue;
                        }
                        $candidate_id = $acct['id'];
                        if (in_array($candidate_id, $accounts_tried, true)) {
                            continue;
                        }

                        $logs[] = ['type' => 'info', 'message' => 'Retrying project creation with Cloudflare account: ' . (isset($acct['name']) ? (string) $acct['name'] : $candidate_id)];
                        $candidate_result = $attemptCreate($candidate_id);
                        $accounts_tried[] = $candidate_id;

                        if (!empty($candidate_result['wp_error'])) {
                            continue;
                        }

                        $candidate_body = is_array($candidate_result['body']) ? $candidate_result['body'] : [];
                        if (isset($candidate_body['success']) && $candidate_body['success'] && isset($candidate_body['result']['id'])) {
                            $account_id = $candidate_id;
                            update_option('loohood_cloudflare_account_id', $account_id);
                            $status_code = (int) $candidate_result['status_code'];
                            $body = $candidate_body;
                            break;
                        }
                    }
                }
            }
        }

        if (isset($body['success']) && $body['success'] && isset($body['result']['id'])) {
            if (!empty($account_id) && is_string($account_id)) {
                update_option('loohood_cloudflare_account_id', $account_id);
            }
            update_option('loohood_cloudflare_project', $body['result']['name']);
            update_option('loohood_cloudflare_project_id', $body['result']['id']);

            $subdomain = isset($body['result']['subdomain']) ? (string) $body['result']['subdomain'] : '';
            $pages_host = $this->normalizeCloudflarePagesTargetHost($subdomain);
            if ($pages_host !== '') {
                update_option('loohood_cloudflare_pages_host', $pages_host);
            }

            $project_url = $pages_host !== '' ? ('https://' . $pages_host) : '';
            $cloudflare_url = $project_url;

            $logs[] = ['type' => 'success', 'message' => 'Project created successfully'];
            if ($project_url !== '') {
                $logs[] = ['type' => 'success', 'message' => 'URL: ' . $project_url];
            }

            if (!empty($custom_domain)) {
                $logs[] = ['type' => 'info', 'message' => 'Adding custom domain: ' . $custom_domain];
                try {
                    $domain_result = $this->addCloudflarePagesCustomDomain($custom_domain, $body['result']['name']);
                    update_option('loohood_cloudflare_custom_domain', $custom_domain);
                    $cloudflare_url = 'https://' . $custom_domain;

                    $logs[] = ['type' => 'success', 'message' => 'Custom domain added: ' . $cloudflare_url];

                    if (isset($domain_result['result']['status']) && is_string($domain_result['result']['status'])) {
                        $logs[] = ['type' => 'info', 'message' => 'Domain status: ' . $domain_result['result']['status']];
                    }

                    try {
                        $dns_result = $this->ensureCloudflarePagesDnsCnameRecord($custom_domain, $pages_host !== '' ? $pages_host : $subdomain);
                        if (!empty($dns_result['logs']) && is_array($dns_result['logs'])) {
                            foreach ($dns_result['logs'] as $dns_log) {
                                $logs[] = $dns_log;
                            }
                        }
                    } catch (Exception $e) {
                        $logs[] = ['type' => 'warning', 'message' => 'DNS record not updated: ' . $e->getMessage()];
                    }
                } catch (Exception $e) {
                    $logs[] = ['type' => 'warning', 'message' => 'Custom domain not added: ' . $e->getMessage()];
                }
            }

            $created_project_name = (string) $body['result']['name'];
            $logs[] = ['type' => 'info', 'message' => 'Triggering initial Cloudflare Pages deployment...'];
            $deploy_response = wp_remote_post("https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/{$created_project_name}/deployments", [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'body' => json_encode([
                    'branch' => 'main'
                ])
            ]);

            if (is_wp_error($deploy_response)) {
                $logs[] = ['type' => 'warning', 'message' => 'Initial deployment not triggered: ' . $deploy_response->get_error_message()];
            } else {
                $deploy_body = json_decode(wp_remote_retrieve_body($deploy_response), true);
                if (is_array($deploy_body) && !empty($deploy_body['success'])) {
                    $logs[] = ['type' => 'success', 'message' => 'Initial deployment triggered successfully'];
                } else {
                    $deploy_error = 'Unknown error';
                    if (is_array($deploy_body) && isset($deploy_body['errors'][0]['message'])) {
                        $deploy_error = (string) $deploy_body['errors'][0]['message'];
                    }
                    $logs[] = ['type' => 'warning', 'message' => 'Initial deployment not triggered: ' . $deploy_error];
                }
            }

            update_option('loohood_setup_completed', 1);

            return [
                'message' => 'Cloudflare Pages project created successfully',
                'project_name' => $created_project_name,
                'project_url' => $project_url,
                'cloudflare_url' => $cloudflare_url,
                'github_url' => "https://github.com/{$owner}/{$repo}",
                'subdomain' => $subdomain,
                'custom_domain' => $custom_domain ? $custom_domain : null,
                'logs' => $logs
            ];
        }

        if (isset($body['errors'][0]['message'])) {
            $error_msg = $body['errors'][0]['message'];
            $error_code = isset($body['errors'][0]['code']) ? (string) $body['errors'][0]['code'] : '';

            $log_line = 'Cloudflare API Error';
            if ($error_code !== '') {
                $log_line .= ' (' . $error_code . ')';
            }
            if (!empty($status_code)) {
                $log_line .= ' HTTP ' . $status_code;
            }
            $log_line .= ': ' . $error_msg;
            $logs[] = ['type' => 'error', 'message' => $log_line];

            throw new Exception( esc_html( $error_msg ) );
        }

        throw new Exception('Failed to create Cloudflare Pages project');
    }

    private function normalizeCloudflareCustomDomain($domain) {
        if (!is_string($domain)) {
            return '';
        }

        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        $domain = preg_replace('~^https?://~i', '', $domain);
        $domain = preg_replace('~/.*$~', '', $domain);
        $domain = rtrim($domain, '.');
        $domain = strtolower($domain);

        if ($domain === '') {
            return '';
        }

        if (strpos($domain, '*') !== false) {
            throw new Exception('Wildcard domains are not supported');
        }

        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])$/i', $domain)) {
            throw new Exception('Invalid domain format');
        }

        return $domain;
    }

    private function addCloudflarePagesCustomDomain($domain, $project_name) {
        $token = get_option('loohood_cloudflare_token');
        $account_id = get_option('loohood_cloudflare_account_id');

        if (empty($token) || empty($account_id) || empty($project_name)) {
            throw new Exception('Cloudflare settings not configured');
        }

        $response = wp_remote_post("https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/{$project_name}/domains", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'name' => $domain
            ])
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to add custom domain: ' . esc_html( $response->get_error_message() ));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['success']) && $body['success']) {
            return $body;
        }

        if (isset($body['errors'][0]['message'])) {
            throw new Exception( esc_html( $body['errors'][0]['message'] ) );
        }

        throw new Exception('Failed to add custom domain');
    }

    private function normalizeCloudflarePagesTargetHost($value) {
        $value = strtolower(trim((string) $value));
        if ($value === '') {
            return '';
        }

        $value = preg_replace('~^https?://~i', '', $value);
        $value = preg_replace('~/.*$~', '', $value);
        $value = rtrim($value, '.');

        while (substr($value, -18) === '.pages.dev.pages.dev') {
            $value = substr($value, 0, -9);
        }

        if (substr($value, -9) !== '.pages.dev' && strpos($value, '.') === false) {
            $value .= '.pages.dev';
        }

        return $value;
    }

    private function getCloudflarePagesPreviewUrlFromOptions() {
        $pages_host = get_option('loohood_cloudflare_pages_host');
        if (!empty($pages_host)) {
            $host = $this->normalizeCloudflarePagesTargetHost($pages_host);
            return $host !== '' ? ('https://' . $host) : '';
        }

        $project = get_option('loohood_cloudflare_project');
        if (!empty($project)) {
            $host = $this->normalizeCloudflarePagesTargetHost($project);
            return $host !== '' ? ('https://' . $host) : '';
        }

        return '';
    }

    private function cloudflareApiRequest($method, $url, $body = null) {
        $token = get_option('loohood_cloudflare_token');
        if (empty($token)) {
            throw new Exception('Cloudflare token is not configured');
        }

        $args = [
            'method' => strtoupper((string) $method),
            'timeout' => 30,
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ]
        ];

        if ($body !== null) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            throw new Exception('Cloudflare API request failed: ' . esc_html( $response->get_error_message() ));
        }

        $decoded = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($decoded)) {
            throw new Exception('Cloudflare API returned invalid response');
        }

        if (isset($decoded['success']) && $decoded['success']) {
            return $decoded;
        }

        if (isset($decoded['errors'][0]['message'])) {
            throw new Exception( esc_html( $decoded['errors'][0]['message'] ) );
        }

        throw new Exception('Cloudflare API request failed');
    }

    private function getCloudflarePagesProjectSubdomain($project_name) {
        $account_id = get_option('loohood_cloudflare_account_id');
        if (empty($account_id) || empty($project_name)) {
            throw new Exception('Cloudflare settings not configured');
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/{$project_name}";
        $result = $this->cloudflareApiRequest('GET', $url);

        if (isset($result['result']['subdomain']) && is_string($result['result']['subdomain']) && $result['result']['subdomain'] !== '') {
            return $result['result']['subdomain'];
        }

        throw new Exception('Unable to read Cloudflare Pages project subdomain');
    }

    private function findCloudflareZoneIdForHostname($hostname) {
        $account_id = get_option('loohood_cloudflare_account_id');
        if (empty($account_id)) {
            throw new Exception('Cloudflare account ID is not configured');
        }

        $hostname = strtolower(trim((string) $hostname));
        if ($hostname === '') {
            throw new Exception('Hostname is required');
        }

        $page = 1;
        $per_page = 50;
        $best_match = null;

        while (true) {
            $url = add_query_arg([
                'per_page' => $per_page,
                'page' => $page,
                'account.id' => $account_id
            ], 'https://api.cloudflare.com/client/v4/zones');

            $zones = $this->cloudflareApiRequest('GET', $url);
            $results = isset($zones['result']) && is_array($zones['result']) ? $zones['result'] : [];

            if (empty($results)) {
                break;
            }

            foreach ($results as $zone) {
                if (empty($zone['id']) || empty($zone['name'])) {
                    continue;
                }

                $zone_name = strtolower((string) $zone['name']);
                $is_match = ($hostname === $zone_name) || (substr($hostname, -1 - strlen($zone_name)) === '.' . $zone_name);
                if (!$is_match) {
                    continue;
                }

                if ($best_match === null || strlen($zone_name) > strlen($best_match['name'])) {
                    $best_match = [
                        'id' => (string) $zone['id'],
                        'name' => $zone_name
                    ];
                }
            }

            if (count($results) < $per_page) {
                break;
            }

            $page++;
            if ($page > 50) {
                break;
            }
        }

        if ($best_match !== null) {
            return $best_match;
        }

        throw new Exception('No matching Cloudflare zone found for domain');
    }

    private function upsertCloudflareCnameRecord($zone_id, $hostname, $target, $proxied = true) {
        $zone_id = (string) $zone_id;
        $hostname = strtolower(trim((string) $hostname));
        $target = strtolower(trim((string) $target));

        if ($zone_id === '' || $hostname === '' || $target === '') {
            throw new Exception('DNS record parameters are missing');
        }

        $list_url = add_query_arg([
            'type' => 'CNAME',
            'name' => $hostname
        ], "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records");

        $existing = $this->cloudflareApiRequest('GET', $list_url);
        $records = isset($existing['result']) && is_array($existing['result']) ? $existing['result'] : [];

        $payload = [
            'type' => 'CNAME',
            'name' => $hostname,
            'content' => $target,
            'ttl' => 1,
            'proxied' => (bool) $proxied
        ];

        if (!empty($records) && !empty($records[0]['id'])) {
            $record_id = (string) $records[0]['id'];
            $update_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records/{$record_id}";
            $this->cloudflareApiRequest('PUT', $update_url, $payload);
            return 'updated';
        }

        $create_url = "https://api.cloudflare.com/client/v4/zones/{$zone_id}/dns_records";
        $this->cloudflareApiRequest('POST', $create_url, $payload);
        return 'created';
    }

    private function ensureCloudflarePagesDnsCnameRecord($custom_domain, $pages_target) {
        $custom_domain = $this->normalizeCloudflareCustomDomain($custom_domain);
        $pages_target = $this->normalizeCloudflarePagesTargetHost($pages_target);

        if ($custom_domain === '' || $pages_target === '') {
            throw new Exception('DNS record parameters are missing');
        }

        $zone = $this->findCloudflareZoneIdForHostname($custom_domain);
        $action = $this->upsertCloudflareCnameRecord($zone['id'], $custom_domain, $pages_target, true);

        return [
            'message' => 'DNS record applied',
            'logs' => [
                ['type' => 'info', 'message' => 'Cloudflare DNS zone: ' . $zone['name']],
                ['type' => 'success', 'message' => 'CNAME ' . $action . ': ' . $custom_domain . ' → ' . $pages_target . ' (proxied)']
            ]
        ];
    }

    public function addCustomDomain() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        check_ajax_referer('loohood_nonce', 'nonce', true);

        $logs = [];

        try {
            $domain_raw = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
            $domain = $this->normalizeCloudflareCustomDomain($domain_raw);

            if (empty($domain)) {
                throw new Exception('Domain is required');
            }

            $project_name = get_option('loohood_cloudflare_project');
            if (empty($project_name)) {
                throw new Exception('Cloudflare Pages project is not configured');
            }

            $logs[] = ['type' => 'info', 'message' => 'Adding custom domain to project: ' . $project_name];
            $logs[] = ['type' => 'info', 'message' => 'Domain: ' . $domain];

            $result = $this->addCloudflarePagesCustomDomain($domain, $project_name);
            update_option('loohood_cloudflare_custom_domain', $domain);

            $logs[] = ['type' => 'success', 'message' => 'Custom domain added: https://' . $domain];

            if (isset($result['result']['status']) && is_string($result['result']['status'])) {
                $logs[] = ['type' => 'info', 'message' => 'Domain status: ' . $result['result']['status']];
            }

            try {
                $subdomain = $this->getCloudflarePagesProjectSubdomain($project_name);
                $pages_host = $this->normalizeCloudflarePagesTargetHost($subdomain);
                if ($pages_host !== '') {
                    update_option('loohood_cloudflare_pages_host', $pages_host);
                }

                $dns_result = $this->ensureCloudflarePagesDnsCnameRecord($domain, $pages_host !== '' ? $pages_host : $subdomain);
                if (!empty($dns_result['logs']) && is_array($dns_result['logs'])) {
                    foreach ($dns_result['logs'] as $dns_log) {
                        $logs[] = $dns_log;
                    }
                }
            } catch (Exception $e) {
                $logs[] = ['type' => 'warning', 'message' => 'DNS record not updated: ' . $e->getMessage()];
            }

            wp_send_json_success([
                'message' => 'Custom domain added successfully',
                'custom_domain' => $domain,
                'cloudflare_url' => 'https://' . $domain,
                'project_url' => $this->getCloudflarePagesPreviewUrlFromOptions(),
                'logs' => $logs
            ]);
        } catch (Exception $e) {
            $logs[] = ['type' => 'error', 'message' => $e->getMessage()];
            wp_send_json_error([
                'message' => $e->getMessage(),
                'logs' => $logs
            ]);
        }
    }

    public function updateToken() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        check_ajax_referer('loohood_nonce', 'nonce', true);

        $token_type = isset($_POST['token_type']) ? sanitize_text_field($_POST['token_type']) : '';
        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';

        try {
            if ($token_type !== 'github' && $token_type !== 'cloudflare') {
                throw new Exception('Invalid token type');
            }
            if ($token === '') {
                throw new Exception('Token is required');
            }

            if ($token_type === 'github') {
                $response = wp_remote_get('https://api.github.com/user', [
                    'headers' => [
                        'Authorization' => "token {$token}",
                        'Accept' => 'application/vnd.github.v3+json'
                    ],
                    'timeout' => 30
                ]);

                if (is_wp_error($response)) {
                    throw new Exception('Failed to connect to GitHub: ' . $response->get_error_message());
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!is_array($body) || empty($body['login'])) {
                    throw new Exception('Invalid GitHub token');
                }

                update_option('loohood_github_token', $token);
                update_option('loohood_github_owner', $body['login']);

                wp_send_json_success([
                    'message' => 'GitHub token updated',
                    'username' => $body['login']
                ]);
            }

            $verify = wp_remote_get('https://api.cloudflare.com/client/v4/user/tokens/verify', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($verify)) {
                throw new Exception('Failed to connect to Cloudflare: ' . $verify->get_error_message());
            }

            $verify_body = json_decode(wp_remote_retrieve_body($verify), true);
            if (!is_array($verify_body) || empty($verify_body['success'])) {
                throw new Exception('Invalid Cloudflare token');
            }

            update_option('loohood_cloudflare_token', $token);

            $accounts_response = wp_remote_get('https://api.cloudflare.com/client/v4/accounts', [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                    'Content-Type' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (!is_wp_error($accounts_response)) {
                $accounts_body = json_decode(wp_remote_retrieve_body($accounts_response), true);
                if (is_array($accounts_body) && !empty($accounts_body['success']) && !empty($accounts_body['result'][0]['id'])) {
                    update_option('loohood_cloudflare_account_id', $accounts_body['result'][0]['id']);
                }
            }

            wp_send_json_success([
                'message' => 'Cloudflare token updated'
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function initialDeploy() {
        $logs = [];
        try {
            $logs[] = ['type' => 'info', 'message' => 'Starting initial deployment...'];

            $this->addDeployLog('info', 'Starting initial deployment...');
            $this->exportToStatic();
            $this->addDeployLog('success', 'Static files exported successfully');

            $logs[] = ['type' => 'success', 'message' => 'Static files exported'];
            $logs[] = ['type' => 'info', 'message' => 'Pushing to GitHub...'];

            $github_result = $this->pushToGitHub();
            $this->addDeployLog('success', 'Pushed to GitHub successfully');

            $logs[] = ['type' => 'success', 'message' => 'Files pushed to GitHub'];
            $logs[] = ['type' => 'info', 'message' => 'Triggering Cloudflare Pages deployment...'];

            try {
                $this->triggerCloudflareDeployment();
                $logs[] = ['type' => 'success', 'message' => 'Cloudflare Pages deployment triggered'];
            } catch (Exception $e) {
                $logs[] = ['type' => 'warning', 'message' => 'Cloudflare deployment not triggered: ' . $e->getMessage()];
            }

            $logs[] = ['type' => 'success', 'message' => 'Initial deployment completed!'];
            $logs[] = ['type' => 'info', 'message' => 'Cloudflare Pages will deploy from GitHub'];

            update_option('loohood_setup_completed', 1);

            return [
                'message' => 'Initial deployment completed successfully',
                'github_url' => "https://github.com/" . get_option('loohood_github_owner') . "/" . get_option('loohood_github_repo'),
                'cloudflare_url' => $this->getCloudflarePagesPreviewUrlFromOptions(),
                'logs' => $logs
            ];
        } catch (Exception $e) {
            $this->addDeployLog('error', 'Deployment failed: ' . $e->getMessage());
            $logs[] = ['type' => 'error', 'message' => 'Deployment failed: ' . $e->getMessage()];
            throw new Exception('Deployment failed: ' . esc_html( $e->getMessage() ));
        }
    }

    private function pushToGitHub() {
        error_log('WPSE: pushToGitHub started using Git CLI');
        error_log('WPSE: Git path: ' . $this->getGitPath());
        
        $token = get_option('loohood_github_token');
        $owner = get_option('loohood_github_owner');
        $repo = get_option('loohood_github_repo');
        $branch = get_option('loohood_github_branch', 'main');

        if (empty($token) || empty($owner) || empty($repo)) {
            throw new Exception('GitHub settings not configured');
        }

        error_log('WPSE: Pushing to GitHub: ' . $owner . '/' . $repo);
        $this->addDeployLog('info', 'Pushing to GitHub: ' . $owner . '/' . $repo);

        $repo_url = $this->buildGitHubRepoUrlWithToken($owner, $repo, $token);
        $export_dir = $this->export_dir;
        $git = $this->getGitPath();

        try {
            error_log('WPSE: Preparing Git operations in export directory');

            if (!is_dir($export_dir)) {
                throw new Exception('Export directory does not exist');
            }

            $this->addDeployLog('info', 'Checking for oversized files (GitHub max 100MB/file)...');
            $oversized = $this->findOversizedFiles($export_dir, 95 * 1024 * 1024, 10);
            if (!empty($oversized)) {
                $items = array_map(function ($item) use ($export_dir) {
                    $relative = ltrim(str_replace($export_dir, '', $item['path']), '/\\');
                    return $relative . ' (' . $this->formatBytes($item['size']) . ')';
                }, $oversized);

                throw new Exception(
                    "Found file(s) too large for GitHub. Please remove/optimize or use Git LFS:\n- " .
                    implode("\n- ", $items)
                );
            }

            $git_dir = $export_dir . '/.git';
            $is_git_repo = is_dir($git_dir);

            if (!$is_git_repo) {
                $this->addDeployLog('info', 'Initializing Git repository...');
                error_log('WPSE: Initializing Git repository');

                $output = $this->execCommand("cd '{$export_dir}' && '{$git}' init 2>&1", true);
                if ($output['return_code'] !== 0) {
                    $safe_output = $this->redactSensitive($output['output']);
                    error_log('WPSE: Git init failed: ' . $safe_output);
                    throw new Exception('Failed to initialize Git repository: ' . $safe_output);
                }
            }

            $this->execCommand("cd '{$export_dir}' && '{$git}' config http.postBuffer 524288000 2>&1", true);
            $this->execCommand("cd '{$export_dir}' && '{$git}' config http.lowSpeedLimit 0 2>&1", true);
            $this->execCommand("cd '{$export_dir}' && '{$git}' config http.lowSpeedTime 999999 2>&1", true);
            $this->execCommand("cd '{$export_dir}' && '{$git}' config http.version HTTP/1.1 2>&1", true);

            $this->addDeployLog('info', 'Configuring Git remote...');
            error_log('WPSE: Configuring Git remote');

            $output = $this->execCommand("cd '{$export_dir}' && '{$git}' remote get-url origin 2>&1", true);
            if ($output['return_code'] !== 0 || strpos($output['output'], 'github.com') === false) {
                $output = $this->execCommand("cd '{$export_dir}' && '{$git}' remote add origin '{$repo_url}' 2>&1", true);
                if ($output['return_code'] !== 0) {
                    error_log('WPSE: Git remote add failed: ' . $this->redactSensitive($output['output']));
                }
            } else {
                $output = $this->execCommand("cd '{$export_dir}' && '{$git}' remote set-url origin '{$repo_url}' 2>&1", true);
                if ($output['return_code'] !== 0) {
                    error_log('WPSE: Git remote set-url failed: ' . $this->redactSensitive($output['output']));
                }
            }

            $this->addDeployLog('info', 'Configuring Git user...');
            error_log('WPSE: Configuring Git user');

            $this->execCommand("cd '{$export_dir}' && '{$git}' config user.name 'WordPress Static Exporter'", true);
            $this->execCommand("cd '{$export_dir}' && '{$git}' config user.email 'wordpress@localhost'", true);

            $this->addDeployLog('info', 'Adding files to Git...');
            error_log('WPSE: Adding files to Git');

            $output = $this->execCommand("cd '{$export_dir}' && '{$git}' add -A 2>&1", true);
            if ($output['return_code'] !== 0) {
                error_log('WPSE: Git add failed: ' . $this->redactSensitive($output['output']));
            }

            $status_output = $this->execCommand("cd '{$export_dir}' && '{$git}' status --porcelain 2>&1", true);
            if (empty(trim($status_output['output']))) {
                $this->addDeployLog('info', 'No changes to commit');
                error_log('WPSE: No changes to commit');
                return ['sha' => null];
            }

            $commit_message = 'Update static export from WordPress - ' . gmdate('Y-m-d H:i:s');
            $this->addDeployLog('info', 'Creating commit...');
            error_log('WPSE: Creating commit with message: ' . $commit_message);

            $output = $this->execCommand("cd '{$export_dir}' && '{$git}' commit -m " . escapeshellarg($commit_message) . " 2>&1", true);
            if ($output['return_code'] !== 0) {
                $safe_output = $this->redactSensitive($output['output']);
                error_log('WPSE: Commit failed: ' . $safe_output);
                throw new Exception('Failed to create commit: ' . $safe_output);
            }

            $this->addDeployLog('info', 'Pushing to GitHub...');
            error_log('WPSE: Pushing to GitHub');

            if (!$is_git_repo) {
                $output = $this->execCommand(
                    "cd '{$export_dir}' && '{$git}' -c http.version=HTTP/1.1 -c http.postBuffer=524288000 -c http.lowSpeedLimit=0 -c http.lowSpeedTime=999999 push -u origin {$branch} 2>&1",
                    true
                );
            } else {
                $output = $this->execCommand(
                    "cd '{$export_dir}' && '{$git}' -c http.version=HTTP/1.1 -c http.postBuffer=524288000 -c http.lowSpeedLimit=0 -c http.lowSpeedTime=999999 push origin {$branch} 2>&1",
                    true
                );
            }

            if ($output['return_code'] !== 0) {
                $safe_output = $this->redactSensitive($output['output']);
                error_log('WPSE: Push failed: ' . $safe_output);
                throw new Exception('Failed to push to GitHub: ' . $safe_output);
            }

            $this->addDeployLog('success', 'Pushed to GitHub successfully');
            error_log('WPSE: pushToGitHub completed successfully');

            return ['sha' => null];
        } catch (Exception $e) {
            throw $e;
        }
    }

    private function findOversizedFiles($dir, $max_bytes, $limit) {
        if (!is_dir($dir)) {
            return [];
        }

        $results = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if (strpos($path, $dir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR) === 0) {
                continue;
            }

            if ($item->isFile()) {
                $size = @filesize($path);
                if (is_int($size) && $size > $max_bytes) {
                    $results[] = ['path' => $path, 'size' => $size];
                    if (count($results) >= $limit) {
                        break;
                    }
                }
            }
        }

        usort($results, function ($a, $b) {
            return $b['size'] <=> $a['size'];
        });

        return $results;
    }

    private function formatBytes($bytes) {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit_index = 0;

        while ($bytes >= 1024 && $unit_index < count($units) - 1) {
            $bytes /= 1024;
            $unit_index++;
        }

        return number_format($bytes, $unit_index === 0 ? 0 : 2) . $units[$unit_index];
    }

    private function execCommand($command, $capture_output = false) {
        $descriptorspec = $capture_output ? [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ] : [];

        $process = proc_open($command, $descriptorspec, $pipes);

        if (!is_resource($process)) {
            return [
                'return_code' => -1,
                'output' => 'Failed to execute command'
            ];
        }

        if ($capture_output) {
            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
        }

        $return_code = proc_close($process);

        if ($capture_output) {
            return [
                'return_code' => $return_code,
                'output' => trim($stdout . $stderr)
            ];
        }

        return ['return_code' => $return_code];
    }

    private function scanExportDir($dir, $relative_path, &$files) {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            $rel_path = $relative_path ? $relative_path . '/' . $item : $item;

            if (is_dir($path)) {
                $this->scanExportDir($path, $rel_path, $files);
            } else {
                $files[] = [
                    'path' => $path,
                    'export_path' => $rel_path,
                    'type' => 'blob'
                ];
            }
        }
    }

    private function triggerCloudflareDeployment() {
        $token = get_option('loohood_cloudflare_token');
        $account_id = get_option('loohood_cloudflare_account_id');
        $project_name = get_option('loohood_cloudflare_project');

        if (empty($token) || empty($account_id) || empty($project_name)) {
            throw new Exception('Cloudflare settings not configured');
        }

        $this->addDeployLog('info', 'Triggering Cloudflare Pages deployment...');

        $response = wp_remote_post("https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/{$project_name}/deployments", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode([
                'branch' => 'main'
            ])
        ]);

        if (is_wp_error($response)) {
            throw new Exception('Failed to trigger Cloudflare deployment: ' . esc_html( $response->get_error_message() ));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['success']) && $body['success']) {
            $this->addDeployLog('success', 'Cloudflare Pages deployment triggered successfully');
            $preview_url = $this->getCloudflarePagesPreviewUrlFromOptions();
            if ($preview_url !== '') {
                $this->addDeployLog('info', 'URL: ' . $preview_url);
            }
            return $body;
        }

        $error_msg = 'Failed to trigger Cloudflare deployment';
        if (isset($body['errors'][0]['message'])) {
            $error_msg .= ': ' . $body['errors'][0]['message'];
        }

        throw new Exception( esc_html( $error_msg ) );
    }

    private function disconnectServices() {
        delete_option('loohood_github_token');
        delete_option('loohood_github_repo');
        delete_option('loohood_github_owner');
        delete_option('loohood_repo_cloned');
        delete_option('loohood_git_configured');
        delete_option('loohood_auto_deploy_enabled');
        delete_option('loohood_cloudflare_token');
        delete_option('loohood_cloudflare_account_id');
        delete_option('loohood_cloudflare_project');
        delete_option('loohood_cloudflare_project_id');
        delete_option('loohood_cloudflare_custom_domain');
        delete_option('loohood_setup_completed');
        delete_option('loohood_not_found_target_type');
        delete_option('loohood_not_found_target_page_id');
        delete_option('loohood_cloudflare_pages_host');

        // Use JavaScript redirect since headers are already sent
        $redirect_url = admin_url('admin.php?page=wp-static-exporter');
        echo '<script>window.location.href = "' . esc_url($redirect_url) . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '"></noscript>';
        exit;
    }

    public function exportToStatic() {
        if (empty($this->export_dir)) {
            throw new Exception('Export directory is not configured');
        }

        $this->addDeployLog('info', 'Initializing static export directory: wp-exporter-result');

        if (!file_exists($this->export_dir)) {
            wp_mkdir_p($this->export_dir);
        }

        $this->cleanExportDir();
        $this->addDeployLog('info', 'Cleaned export directory');

        $urls_to_fetch = $this->discoverUrls();
        $this->addDeployLog('info', 'Found ' . count($urls_to_fetch) . ' URLs to export');

        $exported_count = 0;
        foreach ($urls_to_fetch as $url) {
            $result = $this->fetchUrl($url);

            if ($result) {
                $exported_count++;
                $this->addDeployLog('info', "Fetched: {$url}");

                if ($exported_count % 10 === 0) {
                    $this->addDeployLog('info', "Fetched {$exported_count}/" . count($urls_to_fetch) . " URLs...");
                }
            }
        }

        $this->exportNotFoundArtifacts();
        $this->processExternalPluginFiles();
        $this->addDeployLog('success', "Generated {$exported_count} static files");
    }

    private function exportNotFoundArtifacts() {
        $type = get_option('loohood_not_found_target_type', '404');
        $type = is_string($type) ? $type : '404';
        if (!in_array($type, ['404', 'home', 'page'], true)) {
            $type = '404';
        }

        $page_id = intval(get_option('loohood_not_found_target_page_id', 0));

        $this->export404Html();
        $this->exportRobotsTxt();
        $this->exportSitemapXml();
        $this->exportRedirectsFile($type, $page_id);
    }

    private function export404Html() {
        $url = home_url('/?p=-1');
        $this->addDeployLog('info', 'Generating 404.html using theme template...');

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            $this->addDeployLog('warning', 'Failed to generate 404.html: ' . $response->get_error_message());
            return false;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $html = wp_remote_retrieve_body($response);

        if (!is_string($html) || trim($html) === '') {
            $this->addDeployLog('warning', 'Failed to generate 404.html: empty response body');
            return false;
        }

        if (!is_string($content_type) || stripos($content_type, 'text/html') === false) {
            $this->addDeployLog('warning', 'Failed to generate 404.html: response is not HTML');
            return false;
        }

        $html = $this->processStaticAssets($html);
        $html = $this->replaceWordPressUrls($html);
        $path = rtrim($this->export_dir, '/') . '/404.html';

        if (file_put_contents($path, $html) === false) {
            $this->addDeployLog('warning', 'Failed to write 404.html to export directory');
            return false;
        }

        $this->addDeployLog('success', 'Created: 404.html');
        return true;
    }

    private function exportRobotsTxt() {
        $url = home_url('/robots.txt');
        $this->addDeployLog('info', 'Fetching robots.txt...');

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            $this->addDeployLog('warning', 'Failed to fetch robots.txt: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->addDeployLog('warning', 'robots.txt not available (HTTP ' . $status_code . ')');
            return false;
        }

        $content = wp_remote_retrieve_body($response);

        if (!is_string($content) || trim($content) === '') {
            $this->addDeployLog('warning', 'robots.txt is empty or not available');
            return false;
        }

        $content = $this->replaceWordPressUrls($content);

        $path = rtrim($this->export_dir, '/') . '/robots.txt';

        if (file_put_contents($path, $content) === false) {
            $this->addDeployLog('warning', 'Failed to write robots.txt to export directory');
            return false;
        }

        $this->addDeployLog('success', 'Created: robots.txt');
        return true;
    }

    private function exportSitemapXml() {
        $this->addDeployLog('info', 'Exporting sitemap XML files...');
        $exported_count = 0;

        // Try WordPress core sitemap first (WP 5.5+)
        $wp_sitemap_url = home_url('/wp-sitemap.xml');
        if ($this->fetchAndSaveSitemap($wp_sitemap_url, 'wp-sitemap.xml')) {
            $exported_count++;
            // Fetch child sitemaps from WordPress core sitemap
            $this->fetchChildSitemaps($wp_sitemap_url);
        }

        // Try Yoast SEO sitemap
        $yoast_sitemap_url = home_url('/sitemap_index.xml');
        if ($this->fetchAndSaveSitemap($yoast_sitemap_url, 'sitemap_index.xml')) {
            $exported_count++;
            // Fetch child sitemaps from Yoast
            $this->fetchChildSitemaps($yoast_sitemap_url);
        }

        // Try simple sitemap.xml
        $simple_sitemap_url = home_url('/sitemap.xml');
        if ($this->fetchAndSaveSitemap($simple_sitemap_url, 'sitemap.xml')) {
            $exported_count++;
        }

        if ($exported_count > 0) {
            $this->addDeployLog('success', 'Exported sitemap files');
        } else {
            $this->addDeployLog('warning', 'No sitemap files found');
        }

        return $exported_count > 0;
    }

    private function fetchAndSaveSitemap($url, $filename) {
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        if (!is_string($content_type) || (stripos($content_type, 'xml') === false && stripos($content_type, 'text/') === false)) {
            return false;
        }

        $content = wp_remote_retrieve_body($response);
        if (!is_string($content) || trim($content) === '') {
            return false;
        }

        // Ensure subdirectory exists if filename contains path
        $path = rtrim($this->export_dir, '/') . '/' . $filename;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        // Replace WordPress URLs with target domain in sitemap
        $content = $this->replaceWordPressUrls( $content );

        if (file_put_contents($path, $content) === false) {
            return false;
        }

        $this->addDeployLog('info', 'Created: ' . $filename);
        return true;
    }

    private function fetchChildSitemaps($index_url) {
        $response = wp_remote_get($index_url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $content = wp_remote_retrieve_body($response);
        if (!is_string($content) || trim($content) === '') {
            return;
        }

        // Parse XML to find child sitemap URLs
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false) {
            return;
        }

        $home_url = home_url('/');
        $namespaces = $xml->getNamespaces(true);

        // Handle sitemapindex format (contains <sitemap> elements)
        $sitemaps = $xml->sitemap ?? [];
        foreach ($sitemaps as $sitemap) {
            $loc = (string) ($sitemap->loc ?? '');
            if ($loc && strpos($loc, $home_url) === 0) {
                $relative_path = str_replace($home_url, '', $loc);
                $relative_path = ltrim($relative_path, '/');
                if ($relative_path && strpos($relative_path, 'sitemap') !== false) {
                    $this->fetchAndSaveSitemap($loc, $relative_path);
                }
            }
        }

        // Also check for WordPress core format with wp namespace
        if (isset($namespaces['']) || isset($namespaces['wp'])) {
            foreach ($xml->children() as $child) {
                if ($child->getName() === 'sitemap') {
                    $loc = (string) ($child->loc ?? '');
                    if ($loc && strpos($loc, $home_url) === 0) {
                        $relative_path = str_replace($home_url, '', $loc);
                        $relative_path = ltrim($relative_path, '/');
                        if ($relative_path) {
                            $this->fetchAndSaveSitemap($loc, $relative_path);
                        }
                    }
                }
            }
        }
    }

    private function exportRedirectsFile($type, $page_id) {
        $target = '404.html';
        $status = 404;

        if ($type === 'home') {
            $target = 'index.html';
            $status = 200;
        } elseif ($type === 'page' && $page_id > 0) {
            $post = get_post($page_id);
            if ($post instanceof WP_Post && $post->post_type === 'page' && $post->post_status === 'publish') {
                $permalink = get_permalink($page_id);
                $filename = $this->getFilenameFromUrl($permalink);
                $filename = is_string($filename) ? ltrim($filename, '/') : '';
                if ($filename !== '') {
                    $target = $filename;
                    $status = 200;
                }
            }
        }

        $rule = "/* /{$target} {$status}\n";
        $path = rtrim($this->export_dir, '/') . '/_redirects';

        if (file_put_contents($path, $rule) === false) {
            $this->addDeployLog('warning', 'Failed to write _redirects file');
            return false;
        }

        $this->addDeployLog('success', 'Created: _redirects');
        return true;
    }

    private function processExternalPluginFiles() {
        $processed_count = 0;
        $copied_assets = [];

        // File extensions to scan for asset references
        $scannable_extensions = ['xml', 'html', 'htm', 'css', 'js', 'xsl', 'xslt'];
        
        // Asset extensions to copy
        $asset_extensions = ['css', 'js', 'xsl', 'xslt', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'ico'];

        $files_to_check = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->export_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        // Collect all scannable files from export directory
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                
                if (in_array($ext, $scannable_extensions)) {
                    $relative_path = str_replace($this->export_dir . '/', '', $file->getPathname());
                    $files_to_check[] = [
                        'path' => $file->getPathname(),
                        'relative' => $relative_path,
                        'extension' => $ext
                    ];
                }
            }
        }

        foreach ($files_to_check as $file_info) {
            $content = file_get_contents($file_info['path']);
            if ($content === false) {
                continue;
            }

            // Extract and copy XML stylesheets
            if (in_array($file_info['extension'], ['xml'])) {
                $stylesheet_urls = $this->extractXmlStylesheetUrls($content);
                foreach ($stylesheet_urls as $stylesheet_url) {
                    if ($this->fetchXmlStylesheet($stylesheet_url)) {
                        $copied_assets[] = $stylesheet_url;
                    }
                }
            }

            // Extract and copy all plugin/theme asset references
            $asset_urls = $this->extractAssetUrls($content);
            foreach ($asset_urls as $asset_url) {
                if (!in_array($asset_url, $copied_assets)) {
                    if ($this->copyLocalAsset($asset_url)) {
                        $copied_assets[] = $asset_url;
                    }
                }
            }

            // Replace WordPress URLs in the content
            $content = $this->replaceWordPressUrls($content);

            file_put_contents($file_info['path'], $content);
            $processed_count++;
            $this->addDeployLog('info', 'Processed: ' . $file_info['relative']);
        }

        // Copy custom asset paths defined by user
        $custom_asset_paths = $this->getCustomAssetPaths();
        foreach ($custom_asset_paths as $asset_path) {
            if (!in_array($asset_path, $copied_assets)) {
                if ($this->copyCustomAssetPath($asset_path)) {
                    $copied_assets[] = $asset_path;
                }
            }
        }

        if ($processed_count > 0 || count($copied_assets) > 0) {
            $this->addDeployLog('success', 'Processed ' . $processed_count . ' files, copied ' . count($copied_assets) . ' assets');
        }
    }

    /**
     * Get custom asset paths from settings
     */
    private function getCustomAssetPaths() {
        $custom_assets = get_option('loohood_custom_asset_paths', '');
        if (empty($custom_assets)) {
            return [];
        }

        $paths = [];
        $lines = explode("\n", $custom_assets);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            // Remove leading slash if present
            $line = ltrim($line, '/');
            
            // Skip comments
            if (strpos($line, '#') === 0) {
                continue;
            }
            
            $paths[] = $line;
        }

        return array_unique($paths);
    }

    /**
     * Copy a custom asset path defined by user
     */
    private function copyCustomAssetPath($relative_path) {
        $save_path = rtrim($this->export_dir, '/') . '/' . $relative_path;

        // Skip if already exists
        if (file_exists($save_path)) {
            $this->addDeployLog('info', 'Custom asset already exists, skipping: ' . $relative_path);
            return true;
        }

        $save_dir = dirname($save_path);
        if (!is_dir($save_dir)) {
            if (!wp_mkdir_p($save_dir)) {
                $this->addDeployLog('warning', 'Failed to create directory for custom asset: ' . $save_dir);
                return false;
            }
        }

        $local_file = null;

        // Check local plugin files
        if (preg_match('/^wp-content\/plugins\/(.+)$/', $relative_path, $matches)) {
            $local_file = WP_PLUGIN_DIR . '/' . $matches[1];
        }
        // Check local theme files
        elseif (preg_match('/^wp-content\/themes\/(.+)$/', $relative_path, $matches)) {
            $local_file = WP_CONTENT_DIR . '/themes/' . $matches[1];
        }
        // Check wp-includes files
        elseif (preg_match('/^wp-includes\/(.+)$/', $relative_path, $matches)) {
            $local_file = ABSPATH . 'wp-includes/' . $matches[1];
        }
        // Check wp-content uploads
        elseif (preg_match('/^wp-content\/uploads\/(.+)$/', $relative_path, $matches)) {
            $local_file = WP_CONTENT_DIR . '/uploads/' . $matches[1];
        }
        // Check wp-content directly
        elseif (preg_match('/^wp-content\/(.+)$/', $relative_path, $matches)) {
            $local_file = WP_CONTENT_DIR . '/' . $matches[1];
        }

        if ($local_file && file_exists($local_file)) {
            if (copy($local_file, $save_path)) {
                $this->addDeployLog('success', 'Copied custom asset: ' . $relative_path);
                return true;
            } else {
                $this->addDeployLog('warning', 'Failed to copy custom asset: ' . $relative_path);
                return false;
            }
        }

        $this->addDeployLog('warning', 'Custom asset not found: ' . $relative_path . ' (expected at: ' . ($local_file ?: 'unknown') . ')');
        return false;
    }

    /**
     * Extract all asset URLs (plugins, themes, wp-includes) from content
     */
    private function extractAssetUrls($content) {
        $urls = [];
        $home_url = home_url('/');
        $site_url = site_url('/');
        
        // Patterns to match asset URLs from plugins, themes, and wp-includes
        $patterns = [
            // Full URLs with http/https
            '/(?:https?:)?\/\/[^\s"\'\)>]+\/wp-content\/plugins\/[^\s"\'\)>]+/i',
            '/(?:https?:)?\/\/[^\s"\'\)>]+\/wp-content\/themes\/[^\s"\'\)>]+/i',
            '/(?:https?:)?\/\/[^\s"\'\)>]+\/wp-includes\/[^\s"\'\)>]+/i',
            
            // Relative URLs starting with /wp-content or /wp-includes
            '/["\'\(]\s*(\/wp-content\/plugins\/[^\s"\'\)>]+)/i',
            '/["\'\(]\s*(\/wp-content\/themes\/[^\s"\'\)>]+)/i',
            '/["\'\(]\s*(\/wp-includes\/[^\s"\'\)>]+)/i',
            
            // href and src attributes
            '/href\s*=\s*["\']([^"\']*\/wp-content\/plugins\/[^"\']+)["\']/i',
            '/href\s*=\s*["\']([^"\']*\/wp-content\/themes\/[^"\']+)["\']/i',
            '/href\s*=\s*["\']([^"\']*\/wp-includes\/[^"\']+)["\']/i',
            '/src\s*=\s*["\']([^"\']*\/wp-content\/plugins\/[^"\']+)["\']/i',
            '/src\s*=\s*["\']([^"\']*\/wp-content\/themes\/[^"\']+)["\']/i',
            '/src\s*=\s*["\']([^"\']*\/wp-includes\/[^"\']+)["\']/i',
            
            // url() in CSS
            '/url\s*\(\s*["\']?([^"\')\s]*\/wp-content\/plugins\/[^"\')\s]+)["\']?\s*\)/i',
            '/url\s*\(\s*["\']?([^"\')\s]*\/wp-content\/themes\/[^"\')\s]+)["\']?\s*\)/i',
            '/url\s*\(\s*["\']?([^"\')\s]*\/wp-includes\/[^"\')\s]+)["\']?\s*\)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches as $match_group) {
                    foreach ($match_group as $url) {
                        $url = trim($url);
                        if (empty($url)) continue;
                        
                        // Clean up the URL
                        $url = preg_replace('/^["\'\(\s]+/', '', $url);
                        $url = preg_replace('/["\'\)\s]+$/', '', $url);
                        
                        // Skip if it's just a pattern match artifact
                        if (strpos($url, 'wp-content') === false && strpos($url, 'wp-includes') === false) {
                            continue;
                        }
                        
                        // Normalize protocol-relative URLs
                        if (preg_match('/^\/\//', $url)) {
                            $url = 'https:' . $url;
                        }
                        
                        // Convert relative URLs to full URLs
                        if (preg_match('/^\/wp-/', $url)) {
                            $url = rtrim($home_url, '/') . $url;
                        }
                        
                        if (!empty($url)) {
                            $urls[] = $url;
                        }
                    }
                }
            }
        }

        return array_unique($urls);
    }

    /**
     * Copy a local asset file to export directory
     */
    private function copyLocalAsset($url) {
        $parsed_url = parse_url($url);
        if ($parsed_url === false || !isset($parsed_url['path'])) {
            return false;
        }

        $path = ltrim($parsed_url['path'], '/');
        
        // Skip if not a WordPress asset path
        if (strpos($path, 'wp-content/') !== 0 && strpos($path, 'wp-includes/') !== 0) {
            return false;
        }

        $save_path = rtrim($this->export_dir, '/') . '/' . $path;

        // Skip if already exists
        if (file_exists($save_path)) {
            return true;
        }

        $save_dir = dirname($save_path);
        if (!is_dir($save_dir)) {
            if (!wp_mkdir_p($save_dir)) {
                return false;
            }
        }

        $local_file = null;

        // Check local plugin files
        if (preg_match('/^wp-content\/plugins\/(.+)$/', $path, $matches)) {
            $local_file = WP_PLUGIN_DIR . '/' . $matches[1];
        }
        // Check local theme files
        elseif (preg_match('/^wp-content\/themes\/(.+)$/', $path, $matches)) {
            $local_file = WP_CONTENT_DIR . '/themes/' . $matches[1];
        }
        // Check wp-includes files
        elseif (preg_match('/^wp-includes\/(.+)$/', $path, $matches)) {
            $local_file = ABSPATH . 'wp-includes/' . $matches[1];
        }
        // Check wp-content uploads
        elseif (preg_match('/^wp-content\/uploads\/(.+)$/', $path, $matches)) {
            $local_file = WP_CONTENT_DIR . '/uploads/' . $matches[1];
        }

        if ($local_file && file_exists($local_file)) {
            if (copy($local_file, $save_path)) {
                $this->addDeployLog('success', 'Copied asset: ' . $path);
                return true;
            }
        }

        // Fallback: try to fetch via HTTP from local WordPress
        return $this->fetchAssetViaHttp($url, $save_path, $path);
    }

    /**
     * Fetch asset via HTTP as fallback
     */
    private function fetchAssetViaHttp($url, $save_path, $relative_path) {
        $local_url = $this->convertToLocalUrl($url);
        
        $response = wp_remote_get($local_url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return false;
        }

        $content = wp_remote_retrieve_body($response);
        if (!is_string($content) || trim($content) === '') {
            return false;
        }

        $bytes_written = file_put_contents($save_path, $content);
        if ($bytes_written !== false) {
            $this->addDeployLog('success', 'Fetched asset: ' . $relative_path . ' (' . $bytes_written . ' bytes)');
            return true;
        }

        return false;
    }

    private function extractXmlStylesheetUrls($xml_content) {
        $urls = [];

        preg_match_all('/<\?xml-stylesheet\s*[^>]*href\s*=\s*["\']([^"\']+)["\'][^>]*\?>/i', $xml_content, $matches);

        if (isset($matches[1]) && is_array($matches[1])) {
            foreach ($matches[1] as $url) {
                if (is_string($url) && trim($url) !== '') {
                    $url = trim($url);

                    if (preg_match('/^\/\//', $url)) {
                        $url = 'https:' . $url;
                    }

                    if (preg_match('/^https?:\/\//i', $url)) {
                        $urls[] = $url;
                    } elseif (preg_match('/^\//', $url)) {
                        $urls[] = home_url($url);
                    }
                }
            }
        }

        if (!empty($urls)) {
            $this->addDeployLog('info', 'Found ' . count($urls) . ' XML stylesheet URL(s)');
            foreach ($urls as $url) {
                $this->addDeployLog('info', '  - ' . $url);
            }
        }

        return array_unique($urls);
    }

    private function fetchXmlStylesheet($url) {
        $parsed_url = parse_url($url);
        if ($parsed_url === false || !isset($parsed_url['path'])) {
            $this->addDeployLog('warning', 'Failed to parse URL: ' . $url);
            return false;
        }

        $path = ltrim($parsed_url['path'], '/');
        $save_path = rtrim($this->export_dir, '/') . '/' . $path;

        $save_dir = dirname($save_path);
        if (!is_dir($save_dir)) {
            if (!wp_mkdir_p($save_dir)) {
                $this->addDeployLog('warning', 'Failed to create directory: ' . $save_dir);
                return false;
            }
        }

        if (file_exists($save_path)) {
            $this->addDeployLog('info', 'File already exists, skipping: ' . $path);
            return true;
        }

        // Check if file exists in local WordPress plugins directory
        if (preg_match('/^wp-content\/plugins\/(.+)$/', $path, $matches)) {
            $plugin_relative_path = $matches[1];
            $local_plugin_file = WP_PLUGIN_DIR . '/' . $plugin_relative_path;
            
            if (file_exists($local_plugin_file)) {
                $content = file_get_contents($local_plugin_file);
                if ($content !== false) {
                    $bytes_written = file_put_contents($save_path, $content);
                    if ($bytes_written !== false) {
                        $this->addDeployLog('success', 'Copied local plugin file: ' . $path . ' (' . $bytes_written . ' bytes)');
                        return true;
                    }
                }
            }
        }

        // Check if file exists in local WordPress themes directory
        if (preg_match('/^wp-content\/themes\/(.+)$/', $path, $matches)) {
            $theme_relative_path = $matches[1];
            $local_theme_file = WP_CONTENT_DIR . '/themes/' . $theme_relative_path;
            
            if (file_exists($local_theme_file)) {
                $content = file_get_contents($local_theme_file);
                if ($content !== false) {
                    $bytes_written = file_put_contents($save_path, $content);
                    if ($bytes_written !== false) {
                        $this->addDeployLog('success', 'Copied local theme file: ' . $path . ' (' . $bytes_written . ' bytes)');
                        return true;
                    }
                }
            }
        }

        // Check if file exists in wp-includes directory
        if (preg_match('/^wp-includes\/(.+)$/', $path, $matches)) {
            $includes_relative_path = $matches[1];
            $local_includes_file = ABSPATH . 'wp-includes/' . $includes_relative_path;
            
            if (file_exists($local_includes_file)) {
                $content = file_get_contents($local_includes_file);
                if ($content !== false) {
                    $bytes_written = file_put_contents($save_path, $content);
                    if ($bytes_written !== false) {
                        $this->addDeployLog('success', 'Copied local wp-includes file: ' . $path . ' (' . $bytes_written . ' bytes)');
                        return true;
                    }
                }
            }
        }

        // Try to convert external URL to local URL if possible
        $local_url = $this->convertToLocalUrl($url);
        
        $response = wp_remote_get($local_url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'redirection' => 5
        ]);

        if (is_wp_error($response)) {
            $this->addDeployLog('warning', 'Failed to fetch stylesheet: ' . $response->get_error_message());
            return false;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            $this->addDeployLog('warning', 'HTTP error ' . $status_code . ' for: ' . $local_url);
            return false;
        }

        $content = wp_remote_retrieve_body($response);
        if (!is_string($content) || trim($content) === '') {
            $this->addDeployLog('warning', 'Empty content for: ' . $local_url);
            return false;
        }

        $bytes_written = file_put_contents($save_path, $content);
        if ($bytes_written === false) {
            $this->addDeployLog('warning', 'Failed to write file: ' . $save_path);
            return false;
        }

        $this->addDeployLog('success', 'Fetched XML stylesheet: ' . $path . ' (' . $bytes_written . ' bytes)');
        return true;
    }

    /**
     * Convert an external URL to local WordPress URL using the same path
     */
    private function convertToLocalUrl($url) {
        $parsed_url = parse_url($url);
        if ($parsed_url === false || !isset($parsed_url['path'])) {
            return $url;
        }

        // Get the path part and construct local URL
        $path = $parsed_url['path'];
        return home_url($path);
    }

    private function discoverUrls() {
        $urls = [];

        $home_url = home_url('/');
        $urls[] = $home_url;

        $post_types = get_post_types(['public' => true], 'names');
        if (!is_array($post_types)) {
            $post_types = ['page', 'post'];
        }

        $excluded_types = ['attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset', 'oembed_cache', 'user_request'];
        foreach ($excluded_types as $excluded) {
            if (isset($post_types[$excluded])) {
                unset($post_types[$excluded]);
            }
        }

        $post_types = array_values($post_types);

        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false
        ];

        $post_ids = get_posts($args);

        foreach ($post_ids as $post_id) {
            $permalink = get_permalink($post_id);
            if (is_string($permalink) && $permalink !== '') {
                $urls[] = $permalink;
            }
        }

        return array_unique($urls);
    }

    private function fetchUrl($url) {
        $this->addDeployLog('info', 'Fetching URL: ' . $url);

        $response = wp_remote_get($url, [
            'timeout' => 30,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ]);

        if (is_wp_error($response)) {
            $error_msg = 'Failed to fetch URL: ' . $response->get_error_message();
            $this->addDeployLog('error', $error_msg);
            return false;
        }

        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $content = wp_remote_retrieve_body($response);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 200) {
            $this->addDeployLog('warning', "URL returned status {$status_code}: {$url}");
            return false;
        }

        $html = $content;
        $this->addDeployLog('info', 'Content fetched successfully for: ' . $url);

        if (strpos($content_type, 'text/html') !== false) {
            $html = $this->processStaticAssets($html);
            $html = $this->replaceWordPressUrls($html);
        }

        $filename = $this->getFilenameFromUrl($url);
        $filepath = $this->export_dir . '/' . $filename;
        $dirpath = dirname($filepath);

        if (!file_exists($dirpath)) {
            wp_mkdir_p($dirpath);
        }

        if (file_put_contents($filepath, $html) === false) {
            $this->addDeployLog('error', "Failed to write file: {$filepath}");
            return false;
        }

        $this->addDeployLog('info', "Created: {$filename}");

        return true;
    }

    private function discoverAndCopyThemeAssets() {
        $this->addDeployLog('info', 'Discovering theme assets...');

        $theme_dir = get_template_directory();
        $theme_name = basename($theme_dir);

        if (!is_dir($theme_dir)) {
            return;
        }

        $asset_extensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($theme_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $path = $item->getPathname();
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (!in_array($ext, $asset_extensions)) {
                continue;
            }

            $relative_path = substr($path, strlen($theme_dir) + 1);
            $export_path = 'wp-content/themes/' . $theme_name . '/' . $relative_path;
            $dest_path = $this->export_dir . '/' . $export_path;

            $dir = dirname($dest_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            if (copy($path, $dest_path)) {
                $this->addDeployLog('success', "Copied theme asset: {$export_path}");
            }
        }
    }

    private function discoverAndCopyPluginAssets() {
        $this->addDeployLog('info', 'Discovering plugin assets...');

        $plugins_dir = WP_PLUGIN_DIR;

        if (!is_dir($plugins_dir)) {
            return;
        }

        $asset_extensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'otf', 'eot', 'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($plugins_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                continue;
            }

            $path = $item->getPathname();
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

            if (!in_array($ext, $asset_extensions)) {
                continue;
            }

            $relative_path = substr($path, strlen($plugins_dir) + 1);
            $export_path = 'wp-content/plugins/' . $relative_path;
            $dest_path = $this->export_dir . '/' . $export_path;

            $dir = dirname($dest_path);
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
            }

            if (copy($path, $dest_path)) {
                $this->addDeployLog('success', "Copied plugin asset: {$export_path}");
            }
        }
    }

    private function getFilenameFromUrl($url) {
        $home_url = home_url('/');
        $url_path = parse_url($url, PHP_URL_PATH);

        if ($url_path === '/' || $url_path === $home_url || rtrim($url_path, '/') === rtrim($home_url, '/')) {
            return 'index.html';
        }

        $path = str_replace($home_url, '', $url);
        $path = ltrim($path, '/');
        $path = rtrim($path, '/');

        $path_info = pathinfo($path);

        if (!isset($path_info['extension']) || empty($path_info['extension'])) {
            return $path . '/index.html';
        }

        return $path;
    }

    private function domGetAttribute(DOMElement $element, $name) {
        return $element->getAttribute($name);
    }

    private function domSetAttribute(DOMElement $element, $name, $value) {
        $element->setAttribute($name, $value);
    }



    private function processStaticAssets($html) {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);

        $site_url = site_url('/');
        $home_url = home_url('/');
        $theme_uri = get_template_directory_uri();

        $assets_to_fetch = [];

        $elements = [
            'link' => $xpath->query('//link[@href]'),
            'script' => $xpath->query('//script[@src]'),
            'img' => $xpath->query('//img[@src]'),
            'source' => $xpath->query('//source[@src]'),
            'video' => $xpath->query('//video[@poster]'),
            'audio' => $xpath->query('//audio[@src]'),
            'iframe' => $xpath->query('//iframe[@src]'),
            'embed' => $xpath->query('//embed[@src]'),
            'object' => $xpath->query('//object[@data]'),
            'input' => $xpath->query('//input[@src]'),
            'track' => $xpath->query('//track[@src]')
        ];

        foreach ($elements['link'] as $link) {
            if (!($link instanceof DOMElement)) {
                continue;
            }
            $href = $this->domGetAttribute($link, 'href');
            if ($this->shouldFetchAsset($href)) {
                $new_path = $this->getExportAssetPathFromUrl($href);
                if ($new_path) {
                    $this->domSetAttribute($link, 'href', '/' . $new_path);
                    $assets_to_fetch[] = $href;
                }
            }
        }

        foreach ($elements['script'] as $script) {
            if (!($script instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($script, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($script, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        foreach ($elements['img'] as $img) {
            if (!($img instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($img, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($img, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }

            $srcset = $this->domGetAttribute($img, 'srcset');
            if ($srcset && $this->shouldFetchAsset($srcset)) {
                $new_srcset = $this->processSrcset($srcset);
                if ($new_srcset) {
                    $this->domSetAttribute($img, 'srcset', $new_srcset);
                    $assets_to_fetch = array_merge($assets_to_fetch, $this->extractUrlsFromSrcset($srcset));
                }
            }
        }

        foreach ($elements['source'] as $source) {
            if (!($source instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($source, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($source, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }

            $srcset = $this->domGetAttribute($source, 'srcset');
            if ($srcset && $this->shouldFetchAsset($srcset)) {
                $new_srcset = $this->processSrcset($srcset);
                if ($new_srcset) {
                    $this->domSetAttribute($source, 'srcset', $new_srcset);
                    $assets_to_fetch = array_merge($assets_to_fetch, $this->extractUrlsFromSrcset($srcset));
                }
            }
        }

        foreach ($elements['video'] as $video) {
            if (!($video instanceof DOMElement)) {
                continue;
            }
            $poster = $this->domGetAttribute($video, 'poster');
            if ($this->shouldFetchAsset($poster)) {
                $new_path = $this->getExportAssetPathFromUrl($poster);
                if ($new_path) {
                    $this->domSetAttribute($video, 'poster', '/' . $new_path);
                    $assets_to_fetch[] = $poster;
                }
            }
        }

        foreach ($elements['audio'] as $audio) {
            if (!($audio instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($audio, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($audio, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        foreach ($elements['iframe'] as $iframe) {
            if (!($iframe instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($iframe, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($iframe, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        foreach ($elements['embed'] as $embed) {
            if (!($embed instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($embed, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($embed, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        foreach ($elements['object'] as $object) {
            if (!($object instanceof DOMElement)) {
                continue;
            }
            $data = $this->domGetAttribute($object, 'data');
            if ($this->shouldFetchAsset($data)) {
                $new_path = $this->getExportAssetPathFromUrl($data);
                if ($new_path) {
                    $this->domSetAttribute($object, 'data', '/' . $new_path);
                    $assets_to_fetch[] = $data;
                }
            }
        }

        foreach ($elements['input'] as $input) {
            if (!($input instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($input, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($input, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        foreach ($elements['track'] as $track) {
            if (!($track instanceof DOMElement)) {
                continue;
            }
            $src = $this->domGetAttribute($track, 'src');
            if ($this->shouldFetchAsset($src)) {
                $new_path = $this->getExportAssetPathFromUrl($src);
                if ($new_path) {
                    $this->domSetAttribute($track, 'src', '/' . $new_path);
                    $assets_to_fetch[] = $src;
                }
            }
        }

        $style_tags = $xpath->query('//style');
        foreach ($style_tags as $style) {
            $style_content = $style->nodeValue;
            $new_style = $this->processCssUrls($style_content);
            if ($new_style !== $style_content) {
                $style->nodeValue = $new_style;
            }
            $assets_to_fetch = array_merge($assets_to_fetch, $this->extractCssUrls($style_content));
        }

        $elements_with_style = $xpath->query('//*[@style]');
        foreach ($elements_with_style as $element) {
            if (!($element instanceof DOMElement)) {
                continue;
            }
            $style = $this->domGetAttribute($element, 'style');
            if ($style) {
                $new_style = $this->processCssUrls($style);
                if ($new_style !== $style) {
                    $this->domSetAttribute($element, 'style', $new_style);
                }
                $assets_to_fetch = array_merge($assets_to_fetch, $this->extractCssUrls($style));
            }
        }

        $meta_tags = $xpath->query('//meta[@property] | //meta[@name]');
        foreach ($meta_tags as $meta) {
            if (!($meta instanceof DOMElement)) {
                continue;
            }
            $property = $this->domGetAttribute($meta, 'property');
            $name = $this->domGetAttribute($meta, 'name');
            $content = $this->domGetAttribute($meta, 'content');

            if (in_array($property, ['og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image']) ||
                in_array($name, ['msapplication-TileImage', 'msapplication-square70x70logo', 'msapplication-square150x150logo', 'msapplication-wide310x150logo', 'msapplication-square310x310logo', 'msapplication-notification'])) {
                if ($this->shouldFetchAsset($content)) {
                    $new_path = $this->getExportAssetPathFromUrl($content);
                    if ($new_path) {
                        $this->domSetAttribute($meta, 'content', '/' . $new_path);
                        $assets_to_fetch[] = $content;
                    }
                }
            }
        }

        $html = $dom->saveHTML();

        $html = preg_replace_callback('/@import\s+(?:url\()?[\'"]?([^\'")]+)[\'"]?\)?;?/i', function($matches) {
            $url = trim($matches[1]);
            if ($this->shouldFetchAsset($url)) {
                $new_path = $this->getExportAssetPathFromUrl($url);
                if ($new_path) {
                    return '@import url("/' . $new_path . '");';
                }
            }
            return $matches[0];
        }, $html);

        $html = $this->cleanupWordPressElements($html);

        $html = str_replace($site_url, '/', $html);
        $html = str_replace($home_url, '/', $html);

        $assets_to_fetch = array_unique($assets_to_fetch);
        $assets_to_fetch = array_filter($assets_to_fetch, function($url) {
            return !empty($url) && $this->shouldFetchAsset($url);
        });

        $this->addDeployLog('info', 'Found ' . count($assets_to_fetch) . ' assets to fetch');

        $fetched_count = 0;
        foreach ($assets_to_fetch as $asset_url) {
            if ($this->fetchAsset($asset_url)) {
                $fetched_count++;
            }
        }

        $this->addDeployLog('success', "Successfully fetched {$fetched_count} assets");

        return $html;
    }

    private function shouldFetchAsset($url) {
        if (empty($url)) {
            return false;
        }

        if (preg_match('#^data:#', $url)) {
            return false;
        }

        if (preg_match('#^https?://#', $url)) {
            if (strpos($url, home_url()) === false) {
                return false;
            }
            return true;
        }

        if (strpos($url, '/') === 0) {
            return true;
        }

        return false;
    }

    private function isValidAssetFile($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        $allowed_extensions = [
            'css', 'js', 'json', 'xml', 'svg',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico',
            'woff', 'woff2', 'ttf', 'otf', 'eot',
            'mp4', 'webm', 'ogg', 'mp3', 'wav', 'aac',
            'pdf', 'zip', 'tar', 'gz', 'txt', 'map'
        ];

        if (!in_array($extension, $allowed_extensions)) {
            $this->addDeployLog('info', "Skipping asset with extension: {$extension}");
            return false;
        }

        return true;
    }

    private function fetchAsset($url) {
        if (!$this->shouldFetchAsset($url)) {
            return false;
        }

        $url = $this->stripUrlQueryAndFragment($url);

        if (strpos($url, 'http') !== 0) {
            $url = home_url('/') . ltrim($url, '/');
        }

        $source_path = $this->getLocalPathFromUrl($url);

        if (!$source_path) {
            $this->addDeployLog('warning', "Could not resolve source path for: {$url}");
            return false;
        }

        if (!file_exists($source_path)) {
            $this->addDeployLog('warning', "Source file not found: {$source_path}");
            return false;
        }

        if (!$this->isValidAssetFile($source_path)) {
            return false;
        }

        $export_path = $this->getExportAssetPathFromUrl($url);

        if (!$export_path) {
            $this->addDeployLog('warning', "Could not resolve export path for: {$url}");
            return false;
        }

        $dest_path = $this->export_dir . '/' . $export_path;

        $dir = dirname($dest_path);
        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
        }

        if (file_exists($dest_path)) {
            $this->addDeployLog('info', "Asset already exists: {$export_path}");
            return true;
        }

        $extension = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));

        if ($extension === 'css') {
            $css = @file_get_contents($source_path);
            if ($css === false) {
                $this->addDeployLog('error', "Failed to read CSS asset: {$source_path}");
                return false;
            }

            $result = $this->processCssForExport($css, $url);
            $processed_css = isset($result['css']) ? (string) $result['css'] : $css;
            $referenced_assets = isset($result['assets']) && is_array($result['assets']) ? $result['assets'] : [];

            if (@file_put_contents($dest_path, $processed_css) === false) {
                $this->addDeployLog('error', "Failed to write CSS asset: {$export_path}");
                return false;
            }

            $this->addDeployLog('success', "Asset written: {$export_path}");

            $referenced_assets = array_unique(array_filter($referenced_assets, function($u) {
                return is_string($u) && $u !== '';
            }));

            foreach ($referenced_assets as $asset_url) {
                $this->fetchAsset($asset_url);
            }

            return true;
        }

        $this->addDeployLog('info', "Copying asset: {$url} -> {$export_path}");

        if (!copy($source_path, $dest_path)) {
            $error = error_get_last();
            $error_msg = $error ? $error['message'] : 'Unknown error';
            $this->addDeployLog('error', "Failed to copy asset: {$error_msg}");
            return false;
        }

        $this->addDeployLog('success', "Asset copied: {$export_path}");

        return true;
    }

    private function stripUrlQueryAndFragment($url) {
        $url = (string) $url;
        if ($url === '') {
            return $url;
        }

        return preg_replace('/[?#].*$/', '', $url);
    }

    private function resolveAssetUrlRelativeToUrl($base_url, $maybe_relative_url) {
        $base_url = (string) $base_url;
        $maybe_relative_url = trim((string) $maybe_relative_url);

        if ($maybe_relative_url === '' || $maybe_relative_url[0] === '#') {
            return '';
        }

        if (preg_match('#^data:#i', $maybe_relative_url)) {
            return '';
        }

        if (strpos($maybe_relative_url, '//') === 0) {
            $scheme = parse_url($base_url, PHP_URL_SCHEME);
            if (!$scheme) {
                $scheme = 'https';
            }
            return $scheme . ':' . $maybe_relative_url;
        }

        if (preg_match('#^https?://#i', $maybe_relative_url)) {
            return $maybe_relative_url;
        }

        if (strpos($maybe_relative_url, '/') === 0) {
            return home_url('/') . ltrim($maybe_relative_url, '/');
        }

        $base_url = $this->stripUrlQueryAndFragment($base_url);
        $base_parts = parse_url($base_url);
        if (empty($base_parts['scheme']) || empty($base_parts['host']) || empty($base_parts['path'])) {
            return '';
        }

        $base_dir = rtrim(str_replace('\\', '/', dirname($base_parts['path'])), '/');
        $combined_path = $base_dir . '/' . ltrim($maybe_relative_url, '/');
        $normalized_path = $this->normalizeUrlPath($combined_path);

        $resolved = $base_parts['scheme'] . '://' . $base_parts['host'] . $normalized_path;
        if (!empty($base_parts['port'])) {
            $resolved = $base_parts['scheme'] . '://' . $base_parts['host'] . ':' . $base_parts['port'] . $normalized_path;
        }

        return $resolved;
    }

    private function normalizeUrlPath($path) {
        $path = str_replace('\\', '/', (string) $path);
        $segments = explode('/', $path);
        $out = [];

        foreach ($segments as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($out);
                continue;
            }
            $out[] = $seg;
        }

        return '/' . implode('/', $out);
    }

    private function processCssForExport($css, $css_url) {
        $css = (string) $css;
        $css_url = (string) $css_url;
        $assets = [];

        $css = preg_replace_callback('/@import\s+(?:url\(\s*)?(?:[\'"])?([^\'"\)]+)(?:[\'"])?\s*\)?\s*;?/i', function($matches) use ($css_url, &$assets) {
            $raw = isset($matches[1]) ? trim((string) $matches[1]) : '';
            $resolved = $this->resolveAssetUrlRelativeToUrl($css_url, $raw);
            $resolved = $this->stripUrlQueryAndFragment($resolved);

            if ($resolved !== '' && $this->shouldFetchAsset($resolved)) {
                $assets[] = $resolved;
                $new_path = $this->getExportAssetPathFromUrl($resolved);
                if ($new_path) {
                    return '@import url("/' . $new_path . '");';
                }
            }

            return $matches[0];
        }, $css);

        $css = preg_replace_callback('/url\(\s*(?:[\'"])?([^\'"\)]+)(?:[\'"])?\s*\)/i', function($matches) use ($css_url, &$assets) {
            $raw = isset($matches[1]) ? trim((string) $matches[1]) : '';
            $resolved = $this->resolveAssetUrlRelativeToUrl($css_url, $raw);
            $resolved = $this->stripUrlQueryAndFragment($resolved);

            if ($resolved !== '' && $this->shouldFetchAsset($resolved)) {
                $assets[] = $resolved;
                $new_path = $this->getExportAssetPathFromUrl($resolved);
                if ($new_path) {
                    return 'url("/' . $new_path . '")';
                }
            }

            return $matches[0];
        }, $css);

        return [
            'css' => $css,
            'assets' => $assets
        ];
    }

    private function getLocalPathFromUrl($url) {
        $url = $this->stripUrlQueryAndFragment($url);
        $site_url = site_url('/');
        $theme_uri = get_template_directory_uri();
        $uploads_url = wp_upload_dir()['baseurl'];
        $content_dir = WP_CONTENT_DIR;

        if (strpos($url, $theme_uri) === 0) {
            $path = str_replace($theme_uri, get_template_directory(), $url);
            $path = str_replace('//', '/', $path);
            $this->addDeployLog('info', "Theme asset path resolved: {$path}");
            return $path;
        }

        if (strpos($url, $site_url) === 0) {
            $relative_url = str_replace($site_url, '', $url);
            $path = ABSPATH . $relative_url;
            $path = str_replace('//', '/', $path);
            $this->addDeployLog('info', "Site asset path resolved: {$path}");
            return $path;
        }

        if (strpos($url, $uploads_url) === 0) {
            $relative_path = str_replace($uploads_url, '', $url);
            $path = wp_upload_dir()['basedir'] . $relative_path;
            $path = str_replace('//', '/', $path);
            $this->addDeployLog('info', "Upload asset path resolved: {$path}");
            return $path;
        }

        $relative_path = ltrim($url, '/');

        if (strpos($relative_path, 'wp-content/') === 0) {
            $relative_path = substr($relative_path, strlen('wp-content/'));
        }

        $path = WP_CONTENT_DIR . $relative_path;
        $path = str_replace('//', '/', $path);

        $this->addDeployLog('info', "Content asset path resolved: {$path}");
        return $path;
    }

    private function getExportAssetPathFromUrl($url) {
        $url = $this->stripUrlQueryAndFragment($url);
        $site_url = site_url('/');
        $theme_uri = get_template_directory_uri();
        $uploads_url = wp_upload_dir()['baseurl'];
        $relative_path = ltrim($url, '/');

        if (strpos($url, $theme_uri) === 0) {
            $path = 'wp-content/themes/' . basename(get_template_directory()) . str_replace($theme_uri, '', $url);
            $this->addDeployLog('info', "Export path (theme URI): {$path}");
            return $path;
        }

        if (strpos($url, $site_url) === 0) {
            $relative_url = str_replace($site_url, '', $url);
            $path = ltrim($relative_url, '/');
            $this->addDeployLog('info', "Export path (site URL): {$path}");
            return $path;
        }

        if (strpos($url, $uploads_url) === 0) {
            $clean_path = str_replace($uploads_url, '', $url);
            $path = 'wp-content/uploads/' . ltrim($clean_path, '/');
            $this->addDeployLog('info', "Export path (uploads): {$path}");
            return $path;
        }

        if (strpos($relative_path, 'wp-content/') === 0) {
            $clean_path = substr($relative_path, strlen('wp-content/'));
            $path = 'wp-content/' . $clean_path;
            $this->addDeployLog('info', "Export path (wp-content prefix removed): {$path}");
            return $path;
        }

        $path = 'wp-content/' . $relative_path;
        $this->addDeployLog('info', "Export path (default): {$path}");
        return $path;
    }

    private function processSrcset($srcset) {
        $entries = explode(',', $srcset);
        $processed = [];

        foreach ($entries as $entry) {
            $parts = preg_split('/\s+/', trim($entry), 2);
            $url = $parts[0];
            $descriptor = isset($parts[1]) ? $parts[1] : '';

            if ($this->shouldFetchAsset($url)) {
                $new_path = $this->getExportAssetPathFromUrl($url);
                if ($new_path) {
                    $processed[] = '/' . $new_path . ($descriptor ? ' ' . $descriptor : '');
                } else {
                    $processed[] = $entry;
                }
            } else {
                $processed[] = $entry;
            }
        }

        return implode(', ', $processed);
    }

    private function extractUrlsFromSrcset($srcset) {
        $entries = explode(',', $srcset);
        $urls = [];

        foreach ($entries as $entry) {
            $parts = preg_split('/\s+/', trim($entry), 2);
            $url = $parts[0];

            if ($this->shouldFetchAsset($url)) {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function processCssUrls($css) {
        return preg_replace_callback('/url\(["\']?([^)"\']+)["\']?\)/i', function($matches) {
            $url = $matches[1];
            if ($this->shouldFetchAsset($url)) {
                $new_path = $this->getExportAssetPathFromUrl($url);
                if ($new_path) {
                    return 'url("/' . $new_path . '")';
                }
            }
            return $matches[0];
        }, $css);
    }

    private function extractCssUrls($css) {
        $urls = [];
        preg_match_all('/url\(["\']?([^)"\']+)["\']?\)/i', $css, $matches);

        if (isset($matches[1])) {
            foreach ($matches[1] as $url) {
                if ($this->shouldFetchAsset($url)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    private function cleanExportDir() {
        if (is_dir($this->export_dir)) {
            try {
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($this->export_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($files as $file) {
                    $path = $file->getRealPath();
                    if ($path && strpos($path, $this->export_dir . DIRECTORY_SEPARATOR . '.git') === 0) {
                        continue;
                    }
                    if ($file->isDir()) {
                        @rmdir($path);
                    } else {
                        @unlink($path);
                    }
                }
            } catch (Exception $e) {
                throw new Exception('Failed to clean export directory: ' . esc_html( $e->getMessage() ));
            }
        }
    }

    private function cleanupWordPressElements($html) {
        $html = preg_replace('/<script[^>]*>\s*var\s+ajaxurl[^<]*<\/script>/i', '', $html);
        $html = preg_replace('/<script[^>]*>\s*var\s+_wpUtilSettings[^<]*<\/script>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']shortlink["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']edituri["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']wlwmanifest["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']oembed["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']alternate["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<link[^>]*rel=["\']restapi["\'][^>]*>/i', '', $html);
        $html = preg_replace('/<meta[^>]*generator[^>]*>/i', '', $html);
        $html = preg_replace('/<style[^>]*>\s*\.admin-bar[^<]*<\/style>/i', '', $html);
        $html = preg_replace('/<style[^>]*>\s*html\s*\.admin-bar[^<]*<\/style>/i', '', $html);

        return $html;
    }

    /**
     * Replace WordPress URLs with the target domain.
     *
     * Priority: custom domain > Cloudflare Pages domain.
     *
     * @param string $html HTML content to process.
     * @return string Processed HTML with replaced URLs.
     */
    private function replaceWordPressUrls( $html ) {
        $target_domain = $this->getTargetDomain();
        if ( empty( $target_domain ) ) {
            return $html;
        }

        $site_url = site_url();
        $home_url = home_url();

        // Build target URL with https
        $target_url = 'https://' . $target_domain;
        $target_url_protocol_relative = '//' . $target_domain;

        // Get URL variations to replace
        $urls_to_replace = array_unique( array_filter( [
            $site_url,
            $home_url,
            untrailingslashit( $site_url ),
            untrailingslashit( $home_url ),
            trailingslashit( $site_url ),
            trailingslashit( $home_url ),
        ] ) );

        foreach ( $urls_to_replace as $old_url ) {
            // Replace http and https versions
            $old_url_https = preg_replace( '/^http:/', 'https:', $old_url );
            $old_url_http  = preg_replace( '/^https:/', 'http:', $old_url );
            $old_url_protocol_relative = preg_replace( '/^https?:/', '', $old_url );

            // Replace with trailing slash handling
            if ( substr( $old_url, -1 ) === '/' ) {
                $target_with_trailing = trailingslashit( $target_url );
                $target_protocol_with_trailing = trailingslashit( $target_url_protocol_relative );
                $html = str_replace( $old_url_https, $target_with_trailing, $html );
                $html = str_replace( $old_url_http, $target_with_trailing, $html );
                $html = str_replace( $old_url_protocol_relative, $target_protocol_with_trailing, $html );
            } else {
                $target_no_trailing = untrailingslashit( $target_url );
                $target_protocol_no_trailing = untrailingslashit( $target_url_protocol_relative );
                $html = str_replace( $old_url_https, $target_no_trailing, $html );
                $html = str_replace( $old_url_http, $target_no_trailing, $html );
                $html = str_replace( $old_url_protocol_relative, $target_protocol_no_trailing, $html );
            }
        }

        return $html;
    }

    /**
     * Get target domain for URL replacement.
     *
     * @return string Target domain (without protocol) or empty string.
     */
    private function getTargetDomain() {
        // Priority 1: Custom domain
        $custom_domain = get_option( 'loohood_cloudflare_custom_domain', '' );
        if ( is_string( $custom_domain ) && trim( $custom_domain ) !== '' ) {
            return $this->normalizeCloudflareCustomDomain( $custom_domain );
        }

        // Priority 2: Cloudflare Pages host
        $pages_host = get_option( 'loohood_cloudflare_pages_host', '' );
        if ( is_string( $pages_host ) && trim( $pages_host ) !== '' ) {
            return $this->normalizeCloudflarePagesHost( $pages_host );
        }

        // Priority 3: Cloudflare project name + .pages.dev
        $project_name = get_option( 'loohood_cloudflare_project', '' );
        if ( is_string( $project_name ) && trim( $project_name ) !== '' ) {
            return strtolower( trim( $project_name ) ) . '.pages.dev';
        }

        return '';
    }

    /**
     * Normalize Cloudflare Pages host value.
     *
     * @param string $value Pages host value.
     * @return string Normalized host.
     */
    private function normalizeCloudflarePagesHost( $value ) {
        $value = strtolower( trim( (string) $value ) );
        $value = preg_replace( '/^https?:\/\//i', '', $value );
        $value = preg_replace( '/\/.*$/', '', $value );
        $value = rtrim( $value, '.' );

        // Fix double .pages.dev suffix
        while ( substr( $value, -18 ) === '.pages.dev.pages.dev' ) {
            $value = substr( $value, 0, -9 );
        }

        return $value;
    }
}

WP_Static_Exporter::getInstance();
