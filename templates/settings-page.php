<?php
defined('ABSPATH') || exit;

$loohood_owner = (string) get_option('loohood_github_owner', '');
$loohood_repo = (string) get_option('loohood_github_repo', '');
$loohood_branch = (string) get_option('loohood_github_branch', 'main');
$loohood_setup_completed = get_option('loohood_setup_completed') ? 1 : 0;

$loohood_account_id = (string) get_option('loohood_cloudflare_account_id', '');
$loohood_project = (string) get_option('loohood_cloudflare_project', '');
$loohood_pages_host = (string) get_option('loohood_cloudflare_pages_host', '');
$loohood_custom_domain = (string) get_option('loohood_cloudflare_custom_domain', '');

$loohood_preview_host = '';
if ($loohood_pages_host !== '') {
    $loohood_preview_host = strtolower(trim($loohood_pages_host));
} elseif ($loohood_project !== '') {
    $loohood_preview_host = strtolower(trim($loohood_project));
}
if ($loohood_preview_host !== '') {
    $loohood_preview_host = preg_replace('~^https?://~i', '', $loohood_preview_host);
    $loohood_preview_host = preg_replace('~/.*$~', '', $loohood_preview_host);
    $loohood_preview_host = rtrim($loohood_preview_host, '.');
    while (substr($loohood_preview_host, -18) === '.pages.dev.pages.dev') {
        $loohood_preview_host = substr($loohood_preview_host, 0, -9);
    }
    if (substr($loohood_preview_host, -9) !== '.pages.dev' && strpos($loohood_preview_host, '.') === false) {
        $loohood_preview_host .= '.pages.dev';
    }
}

$loohood_not_found_type = get_option('loohood_not_found_target_type', '404');
$loohood_not_found_type = is_string($loohood_not_found_type) ? $loohood_not_found_type : '404';
$loohood_not_found_page_id = intval(get_option('loohood_not_found_target_page_id', 0));
$loohood_auto_deploy_enabled = get_option('loohood_auto_deploy_enabled') ? 1 : 0;
?>

<div class="wrap" id="loohood-settings-root">
    <div class="bg-[#f6f7f9] font-sans text-slate-900 min-h-screen -mx-5 -mt-2">
        <?php loohood_render_plugin_header('Settings'); ?>

        <main class="max-w-7xl mx-auto px-6 py-8 space-y-8">
            <div class="rounded-2xl border border-[#f49300] bg-[#f49300]/10 px-6 py-5 text-[#f49300]">
                <div class="font-extrabold mb-1">Note</div>
                <div class="text-sm text-[#f49300]/80">
                    This plugin uses an automatic setup wizard for GitHub &amp; Cloudflare configuration. Most settings are configured automatically during the initial setup.
                    To fully reset configuration, open the <a class="text-[#f49300] font-semibold hover:underline" href="<?php echo esc_url(admin_url('admin.php?page=wp-static-exporter')); ?>">Dashboard</a> and click “Reset &amp; Disconnect”.
                </div>
            </div>

            <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-extrabold mb-6">Current Configuration</h2>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <tbody>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500 w-1/3">GitHub Owner</td>
                                <td class="py-3 text-slate-800"><?php echo esc_html($loohood_owner !== '' ? $loohood_owner : '-'); ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500">GitHub Repository</td>
                                <td class="py-3 text-slate-800">
                                    <?php if ($loohood_owner !== '' && $loohood_repo !== '') : ?>
                                        <a class="text-[#f49300] font-semibold hover:underline" href="<?php echo esc_url('https://github.com/' . rawurlencode($loohood_owner) . '/' . rawurlencode($loohood_repo)); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($loohood_owner . '/' . $loohood_repo); ?></a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500">GitHub Branch</td>
                                <td class="py-3 text-slate-800 font-mono text-xs"><?php echo esc_html($loohood_branch !== '' ? $loohood_branch : 'main'); ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500">Cloudflare Account ID</td>
                                <td class="py-3 text-slate-800 font-mono text-xs"><?php echo esc_html($loohood_account_id !== '' ? (substr($loohood_account_id, 0, 8) . '...') : '-'); ?></td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500">Cloudflare Pages</td>
                                <td class="py-3 text-slate-800">
                                    <?php if ($loohood_preview_host !== '') : ?>
                                        <a class="text-[#f49300] font-semibold hover:underline" href="<?php echo esc_url('https://' . $loohood_preview_host); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($loohood_preview_host); ?></a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="border-b border-slate-100">
                                <td class="py-3 font-semibold text-slate-500">Custom Domain</td>
                                <td class="py-3 text-slate-800">
                                    <?php if ($loohood_custom_domain !== '') : ?>
                                        <a class="text-[#f49300] font-semibold hover:underline" href="<?php echo esc_url('https://' . $loohood_custom_domain); ?>" target="_blank" rel="noreferrer noopener"><?php echo esc_html($loohood_custom_domain); ?></a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="py-3 font-semibold text-slate-500">Setup Completed</td>
                                <td class="py-3"><?php echo esc_html( $loohood_setup_completed ? 'Yes' : 'No' ); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-extrabold mb-6">Token Status</h2>
                <div class="space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-5 py-4">
                        <div class="flex-none">
                            <svg class="w-8 h-8" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.041-1.416-4.041-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"></path></svg>
                        </div>
                        <div class="w-full flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-extrabold uppercase tracking-widest text-slate-400">GitHub Token</div>
                                <div class="text-sm font-semibold text-slate-800"><?php echo esc_html( get_option( 'loohood_github_token' ) ? 'Configured (stored securely)' : 'Not configured' ); ?></div>
                            </div>
                            <button type="button" class="loohood-change-token-btn px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 transition-colors font-semibold" data-token-type="github">Change</button>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50 px-5 py-4">
                        <div class="flex-none">
                            <svg class="w-8 h-8 text-[#f38020]" viewBox="0 0 24 24" fill="currentColor"><path d="M16.5088 16.8447c.1475-.5068.0908-.9707-.1553-1.2863-.2246-.2871-.5765-.4404-1.0098-.4619l-8.6914-.0786a.1876.1876 0 01-.1602-.0903.2894.2894 0 01-.0361-.1831c.0283-.0996.1204-.1757.2205-.1815l8.7904-.0787c.9903-.0518 2.0633-.8718 2.4314-1.8936l.4687-1.2863a.3845.3845 0 00.0244-.2198c-.3989-2.7877-2.7905-4.911-5.6826-4.911-2.5558 0-4.7217 1.6851-5.4524 4.0106-.4867-.3646-1.1049-.5765-1.7843-.5382-1.2359.0787-2.2302 1.0856-2.296 2.3254-.0189.3522.0273.6918.1182 1.0071-1.5189.0127-2.7401 1.2522-2.7401 2.789 0 .1471.0119.2908.0283.4345a.206.206 0 00.2001.1757h15.0056c.0983 0 .1931-.0631.2198-.1553l.2638-.7178c.0521-.1402.0483-.2929-.0035-.4321a.5073.5073 0 00-.3957-.2602z"/><path d="M19.4058 13.7467l-.3544-.9666c-.0908-.2488-.3117-.4177-.5765-.4177h-.1589c-.2166 0-.4091.1388-.4786.3512l-.0394.1188c-.1475.5069-.0908.9707.1553 1.2863.2246.2871.5765.4404 1.0097.4619l.3545.0315c.0981-.0031.1869-.0601.2237-.1553l.2638-.7178c.0521-.1402.0483-.2929-.0035-.4321a.5073.5073 0 00-.3957-.2602z"/></svg>
                        </div>
                        <div class="w-full flex items-center justify-between gap-3">
                            <div>
                                <div class="text-xs font-extrabold uppercase tracking-widest text-slate-400">Cloudflare Token</div>
                                <div class="text-sm font-semibold text-slate-800"><?php echo esc_html( get_option( 'loohood_cloudflare_token' ) ? 'Configured (stored securely)' : 'Not configured' ); ?></div>
                            </div>
                            <button type="button" class="loohood-change-token-btn px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 transition-colors font-semibold" data-token-type="cloudflare">Change</button>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-4">Tokens are stored in the WordPress database. Tokens are not displayed for security reasons.</p>
            </section>

            <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-extrabold mb-2">Auto Deploy</h2>
                <p class="text-sm text-slate-500 mb-6">If enabled, every update to a published post/page/custom post type triggers export and push automatically.</p>

                <form method="post" class="space-y-4">
                    <?php wp_nonce_field('loohood_auto_deploy_settings_nonce'); ?>
                    <input type="hidden" name="loohood_save_auto_deploy_settings" value="1">

                    <label class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50 px-5 py-4">
                        <input type="checkbox" name="loohood_auto_deploy_enabled" value="1" class="!mt-1 !rounded !border-slate-300 !text-[#f49300] checked:!ring-[#f49300]" <?php checked($loohood_auto_deploy_enabled, 1); ?>>
                        <span class="flex-1">
                            <span class="block font-semibold text-slate-800">Enable Auto Deploy</span>
                            <span class="block text-xs text-slate-500 mt-1">Default is manual deploy via “Push to Live”.</span>
                        </span>
                    </label>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#f49300]/90 focus:ring-4 focus:ring-[#f49300]/90 transition-all">Save</button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-extrabold mb-2">Not Found Redirect</h2>
                <p class="text-sm text-slate-500 mb-6">Configure what to show when a visitor opens a URL that does not exist on your static site.</p>

                <form method="post" class="space-y-5">
                    <?php wp_nonce_field('loohood_not_found_settings_nonce'); ?>
                    <input type="hidden" name="loohood_save_not_found_settings" value="1">

                    <div>
                        <label for="loohood_not_found_target_type" class="block text-xs font-extrabold text-slate-700 mb-2">Redirect Target</label>
                        <select name="loohood_not_found_target_type" id="loohood_not_found_target_type" class="w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm !focus:border-[#f49300] !focus:ring-4 !focus:ring-[#f49300]/10">
                            <option value="404" <?php selected($loohood_not_found_type, '404'); ?>>404.html (theme 404 page)</option>
                            <option value="home" <?php selected($loohood_not_found_type, 'home'); ?>>Home page</option>
                            <option value="page" <?php selected($loohood_not_found_type, 'page'); ?>>Specific page</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-2">Applied during export by generating 404.html and a Cloudflare Pages _redirects rule.</p>
                    </div>

                    <div>
                        <label for="loohood_not_found_target_page_id" class="block text-xs font-extrabold text-slate-700 mb-2">Page</label>
                        <?php
                        wp_dropdown_pages([
                            'name' => 'loohood_not_found_target_page_id',
                            'id' => 'loohood_not_found_target_page_id',
                            'selected' => absint( $loohood_not_found_page_id ),
                            'show_option_none' => '— Select a page —',
                            'option_none_value' => '0',
                            'post_status' => 'publish',
                            'class' => 'w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm !focus:border-[#f49300] !focus:ring-4 !focus:ring-[#f49300]/10'
                        ]);
                        ?>
                        <p class="text-xs text-slate-500 mt-2">Used only when Redirect Target is set to Specific page.</p>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#f49300]/90 focus:ring-4 focus:ring-[#f49300]/90 transition-all">Save</button>
                    </div>
                </form>
            </section>

            <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                <h2 class="text-xl font-extrabold mb-2">Custom Asset Paths</h2>
                <p class="text-sm text-slate-500 mb-6">Add additional asset paths to copy during export. These paths are relative to the WordPress installation directory (e.g. <code class="bg-slate-100 px-1 rounded">wp-content/plugins/plugin-name/css/style.css</code>).</p>

                <form method="post" class="space-y-5">
                    <?php wp_nonce_field('loohood_custom_assets_settings_nonce'); ?>
                    <input type="hidden" name="loohood_save_custom_assets_settings" value="1">

                    <div>
                        <label for="loohood_custom_asset_paths" class="block text-xs font-extrabold text-slate-700 mb-2">Asset Paths (one per line)</label>
                        <textarea 
                            name="loohood_custom_asset_paths" 
                            id="loohood_custom_asset_paths" 
                            rows="8" 
                            class="w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm !font-mono !focus:border-[#f49300] !focus:ring-4 !focus:ring-[#f49300]/10"
                            placeholder="wp-content/plugins/wordpress-seo/css/main-sitemap.xsl
wp-content/plugins/your-plugin/assets/style.css
wp-content/themes/your-theme/fonts/custom-font.woff2"
                        ><?php echo esc_textarea(get_option('loohood_custom_asset_paths', '')); ?></textarea>
                        <p class="text-xs text-slate-500 mt-2">Enter one path per line. These files will be copied to the export directory during static export.</p>
                    </div>

                    <div class="rounded-lg border border-slate-100 bg-slate-50 px-5 py-4">
                        <div class="text-xs font-extrabold text-slate-700 mb-2">Supported Path Formats:</div>
                        <ul class="list-disc pl-5 space-y-1 text-xs text-slate-600">
                            <li><code class="bg-white px-1 rounded">wp-content/plugins/plugin-name/file.css</code> - Plugin assets</li>
                            <li><code class="bg-white px-1 rounded">wp-content/themes/theme-name/file.js</code> - Theme assets</li>
                            <li><code class="bg-white px-1 rounded">wp-includes/css/file.css</code> - WordPress core assets</li>
                            <li><code class="bg-white px-1 rounded">wp-content/uploads/2024/01/file.jpg</code> - Upload files</li>
                        </ul>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#f49300]/90 focus:ring-4 focus:ring-[#f49300]/90 transition-all">Save</button>
                    </div>
                </form>
            </section>

            <section class="rounded-2xl border border-amber-200 bg-amber-50 px-6 py-5 text-amber-900">
                <div class="font-extrabold mb-2">Important Notes</div>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li><span class="font-semibold">GitHub Repository:</span> Created as private. It is not automatically deleted on reset.</li>
                    <li><span class="font-semibold">Cloudflare Pages:</span> The project remains after reset. Delete it manually if you no longer need it.</li>
                    <li><span class="font-semibold">Token Security:</span> Never share your GitHub or Cloudflare tokens with anyone.</li>
                    <li><span class="font-semibold">Backup:</span> The GitHub repository acts as a backup for your static site.</li>
                </ul>
            </section>
        </main>

        <footer class="max-w-7xl mx-auto px-6 py-12 text-center text-slate-400 text-sm">
            <div class="flex justify-center gap-6 mb-4">
                <a class="hover:text-[#f49300] transition-colors" href="https://loohood.web.id/docs" target="_blank" rel="noreferrer noopener">Documentation</a>
            </div>
            <p>© <?php echo esc_html(gmdate('Y')); ?> LooHood</p>
        </footer>
    </div>
</div>

<div id="loohood-token-modal" class="hidden fixed inset-0 bg-black/70 z-[99999] items-center justify-center p-6">
    <div class="w-full max-w-xl bg-white rounded-2xl overflow-hidden shadow-2xl border border-slate-200">
        <div class="flex items-center justify-between px-6 py-4 bg-slate-50 border-b border-slate-200">
            <h3 id="loohood-token-modal-title" class="m-0 text-base font-extrabold text-slate-900">Update Token</h3>
            <button class="loohood-modal-close text-slate-500 hover:text-slate-900 text-2xl leading-none" type="button" aria-label="Close">×</button>
        </div>
        <div class="px-6 py-5">
            <input type="hidden" id="loohood-token-modal-type" value="">
            <label for="loohood-token-modal-input" class="block text-xs font-extrabold text-slate-700 mb-2">New Token</label>
            <input type="password" id="loohood-token-modal-input" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:border-[#f49300] focus:ring-4 focus:ring-blue-100" placeholder="">
            <p class="text-xs text-slate-500 mt-3">The current token cannot be shown. Enter a new token to replace it.</p>
            <p id="loohood-token-modal-status" class="text-sm mt-4"></p>
        </div>
        <div class="flex justify-end gap-3 px-6 py-4 bg-slate-50 border-t border-slate-200">
            <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 transition-colors font-semibold" id="loohood-token-modal-cancel">Cancel</button>
            <button type="button" class="px-4 py-2 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-blue-700 focus:ring-4 focus:ring-blue-100 transition-all" id="loohood-token-modal-save">Update Token</button>
        </div>
    </div>
</div>
