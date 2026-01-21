<?php
defined('ABSPATH') || exit;

$loohood_github_owner = (string) get_option('loohood_github_owner', '');
$loohood_github_repo = (string) get_option('loohood_github_repo', '');
$loohood_github_label = ($loohood_github_owner !== '' && $loohood_github_repo !== '') ? ($loohood_github_owner . '/' . $loohood_github_repo) : '';
$loohood_github_url = ($loohood_github_label !== '') ? ('https://github.com/' . rawurlencode($loohood_github_owner) . '/' . rawurlencode($loohood_github_repo)) : '';

$loohood_custom_domain = (string) get_option('loohood_cloudflare_custom_domain', '');
$loohood_pages_host = (string) get_option('loohood_cloudflare_pages_host', '');
$loohood_project = (string) get_option('loohood_cloudflare_project', '');
$loohood_account_id = (string) get_option('loohood_cloudflare_account_id', '');

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

$loohood_preview_url = ($loohood_preview_host !== '') ? ('https://' . $loohood_preview_host) : '';
$loohood_live_url = ($loohood_custom_domain !== '') ? ('https://' . $loohood_custom_domain) : $loohood_preview_url;

$loohood_deploy_logs = get_option('loohood_deploy_logs', []);
$loohood_last_deploy_ts = 0;
if (is_array($loohood_deploy_logs) && !empty($loohood_deploy_logs)) {
    $loohood_last = end($loohood_deploy_logs);
    if (is_array($loohood_last) && isset($loohood_last['timestamp'])) {
        $loohood_last_deploy_ts = (int) $loohood_last['timestamp'];
    }
    reset($loohood_deploy_logs);
}
$loohood_last_deployed_text = 'Never';
if ($loohood_last_deploy_ts > 0) {
    $loohood_last_deployed_text = human_time_diff($loohood_last_deploy_ts, time()) . ' ago';
}

$loohood_current_user = wp_get_current_user();
$loohood_current_user_label = ($loohood_current_user instanceof WP_User && $loohood_current_user->exists()) ? $loohood_current_user->user_login : '';
?>

<div class="wrap" id="loohood-dashboard-root">
    <div class="bg-[#f6f7f9] font-sans text-slate-900 min-h-screen -mx-5 -mt-2">
        <?php loohood_render_plugin_header('Dashboard'); ?>

        <main class="max-w-7xl mx-auto px-6 py-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <div class="lg:col-span-7 space-y-8">
                    <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                            <div>
                                <h2 class="text-xl font-bold mb-1">Deploy Static Site</h2>
                                <p class="text-slate-500 text-sm">Push your latest WordPress changes to GitHub and Cloudflare Pages.</p>
                            </div>
                            <div class="text-right shrink-0">
                                <form method="post">
                                    <?php wp_nonce_field('loohood_export_nonce'); ?>
                                    <button type="submit" name="loohood_export" class="w-full px-10 py-4 bg-[#f49300] text-white font-bold rounded-lg hover:bg-[#d37f00] focus:ring-4 focus:ring-orange-100 transition-all shadow-lg shadow-orange-500/20 flex items-center justify-center gap-2">
                                        <span class="material-symbols-outlined">rocket_launch</span>
                                        Push to Live
                                    </button>
                                </form>
                                <p class="mt-3 text-xs text-slate-400 font-medium">
                                    Last deployed: <?php echo esc_html($loohood_last_deployed_text); ?><?php if ($loohood_current_user_label !== ''): ?> by <?php echo esc_html($loohood_current_user_label); ?><?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div id="loohood-deploy-status" class="hidden mt-6">
                            <div class="bg-slate-50 px-4 py-3 rounded-lg border border-slate-200 text-sm flex items-center gap-2">
                                <span id="loohood-status-icon" class="text-base">⏳</span>
                                <span id="loohood-status-text">Exporting...</span>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-2xl overflow-hidden shadow-sm border border-slate-200">
                        <div class="px-8 py-5 border-b border-slate-100 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-settings"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M10.325 4.317c.426 -1.756 2.924 -1.756 3.35 0a1.724 1.724 0 0 0 2.573 1.066c1.543 -.94 3.31 .826 2.37 2.37a1.724 1.724 0 0 0 1.065 2.572c1.756 .426 1.756 2.924 0 3.35a1.724 1.724 0 0 0 -1.066 2.573c.94 1.543 -.826 3.31 -2.37 2.37a1.724 1.724 0 0 0 -2.572 1.065c-.426 1.756 -2.924 1.756 -3.35 0a1.724 1.724 0 0 0 -2.573 -1.066c-1.543 .94 -3.31 -.826 -2.37 -2.37a1.724 1.724 0 0 0 -1.065 -2.572c-1.756 -.426 -1.756 -2.924 0 -3.35a1.724 1.724 0 0 0 1.066 -2.573c-.94 -1.543 .826 -3.31 2.37 -2.37c1 .608 2.296 .07 2.572 -1.065" /><path d="M9 12a3 3 0 1 0 6 0a3 3 0 0 0 -6 0" /></svg>
                            <h2 class="text-lg font-bold text-slate-800">Active Configuration</h2>
                        </div>
                        <div class="divide-y divide-slate-100">
                            <div class="p-8">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <a class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group" href="<?php echo esc_url($loohood_github_url !== '' ? $loohood_github_url : '#'); ?>" target="_blank" rel="noreferrer noopener">
                                        <div class="w-8 h-8 rounded-lg bg-white flex-none shadow-sm">
                                            <div class="flex w-full h-full items-center justify-center">
                                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.041-1.416-4.041-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"></path></svg>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Repository</div>
                                            <div class="text-sm font-medium truncate text-[#f49300] group-hover:underline"><?php echo esc_html($loohood_github_label !== '' ? $loohood_github_label : 'Not connected'); ?></div>
                                        </div>
                                    </a>
                                    <a class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group" href="<?php echo esc_url($loohood_live_url !== '' ? $loohood_live_url : '#'); ?>" target="_blank" rel="noreferrer noopener">
                                        <div class="w-8 h-8 rounded-lg bg-white flex-none shadow-sm">
                                            <div class="flex w-full h-full items-center justify-center">
                                                <span class="material-symbols-outlined text-xl">globe</span>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Live Site</div>
                                            <div class="text-sm font-medium truncate text-[#f49300] group-hover:underline"><?php echo esc_html($loohood_custom_domain !== '' ? $loohood_custom_domain : ($loohood_preview_host !== '' ? $loohood_preview_host : 'Not connected')); ?></div>
                                        </div>
                                    </a>
                                    <a class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 hover:bg-slate-100 border border-slate-100 transition-colors group" href="<?php echo esc_url($loohood_preview_url !== '' ? $loohood_preview_url : '#'); ?>" target="_blank" rel="noreferrer noopener">
                                        <div class="w-8 h-8 rounded-lg bg-white flex-none shadow-sm">
                                            <div class="flex w-full h-full items-center justify-center">
                                                <span class="material-symbols-outlined text-xl">visibility</span>
                                            </div>
                                        </div>
                                        <div class="overflow-hidden">
                                            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Preview Site</div>
                                            <div class="text-sm font-medium truncate text-[#f49300] group-hover:underline"><?php echo esc_html($loohood_preview_host !== '' ? $loohood_preview_host : 'Not connected'); ?></div>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            <div class="px-8 pb-6">
                                <table class="w-full text-sm">
                                    <tbody>
                                        <tr class="border-b border-slate-50">
                                            <td class="py-3 font-semibold text-slate-500 w-1/3">GitHub Owner</td>
                                            <td class="py-3 text-slate-700"><?php echo esc_html($loohood_github_owner !== '' ? $loohood_github_owner : '-'); ?></td>
                                        </tr>
                                        <tr class="border-b border-slate-50">
                                            <td class="py-3 font-semibold text-slate-500">Cloudflare Account ID</td>
                                            <td class="py-3 text-slate-700 font-mono text-xs"><?php echo esc_html($loohood_account_id !== '' ? (substr($loohood_account_id, 0, 8) . '...') : '-'); ?></td>
                                        </tr>
                                        <tr>
                                            <td class="py-3 font-semibold text-slate-500">Cloudflare Pages Project</td>
                                            <td class="py-3 text-slate-700"><?php echo esc_html($loohood_project !== '' ? $loohood_project : '-'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>

                    <section class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                        <div class="flex items-center gap-2 mb-6">
                            <span class="material-symbols-outlined">language</span>
                            <h2 class="text-lg font-bold">Custom Domain</h2>
                        </div>
                        <div class="space-y-4">
                            <p class="text-sm text-slate-500 leading-relaxed">
                                Attach a custom domain to your Cloudflare Pages project. The domain must be in the same Cloudflare account as the project.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="relative flex-grow">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 material-symbols-outlined text-slate-400 text-lg">link</span>
                                    <input id="loohood-admin-custom-domain" class="w-full !pl-11 !pr-4 !py-1 !bg-slate-50 !border !border-slate-200 !rounded-lg !focus:ring-2 !focus:ring-[#f49300] !focus:border-transparent !outline-none !transition-all" placeholder="example.com" type="text" value="<?php echo esc_attr($loohood_custom_domain); ?>"/>
                                </div>
                                <button type="button" id="loohood-add-custom-domain-btn" class="px-8 py-1 bg-slate-100 text-slate-700 font-bold rounded-lg hover:bg-slate-200 transition-all border border-slate-200">
                                    Add Domain
                                </button>
                            </div>
                        </div>
                    </section>

                    <section class="p-8 bg-red-50/50 border border-red-100 rounded-2xl">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 text-red-800 mb-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="icon icon-tabler icons-tabler-outline icon-tabler-alert-triangle"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 9v4" /><path d="M10.363 3.591l-8.106 13.534a1.914 1.914 0 0 0 1.636 2.871h16.214a1.914 1.914 0 0 0 1.636 -2.87l-8.106 -13.536a1.914 1.914 0 0 0 -3.274 0" /><path d="M12 16h.01" /></svg>
                                    <h2 class="text-lg font-bold text-red-800">Danger Zone</h2>
                                </div>
                                <p class="text-red-600/80 text-sm">Resetting will remove all configurations and delete the repository connection.</p>
                            </div>
                            <form method="post">
                                <?php wp_nonce_field('loohood_disconnect_nonce'); ?>
                                <button type="submit" name="loohood_disconnect" class="px-6 py-2 bg-white text-red-600 border border-red-200 text-xs font-bold rounded-lg hover:bg-red-50 transition-all">
                                    Reset &amp; Disconnect
                                </button>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="lg:col-span-5 lg:sticky lg:top-24 space-y-6">
                    <div id="loohood-terminal-modal" class="loohood-terminal-inline bg-[#1e1e1e] rounded-2xl overflow-hidden shadow-2xl border border-slate-800">
                        <div class="bg-[#2d2d2d] px-4 py-3 flex items-center justify-between border-b border-black/20">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-[#ff5f56]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#ffbd2e]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#27c93f]"></div>
                            </div>
                            <div class="text-[10px] font-mono text-slate-400 uppercase tracking-widest font-bold">Deployment History</div>
                            <div class="w-12"></div>
                        </div>
                        <div class="p-6 font-mono text-sm leading-relaxed h-[520px] overflow-y-auto [&::-webkit-scrollbar]:hidden [-ms-overflow-style:none] [scrollbar-width:none]">
                            <div id="loohood-terminal-output" class="space-y-2 text-slate-300">
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-emerald-400">➜</span>
                                    <span class="text-slate-300 font-bold">Awaiting new deployment trigger...</span>
                                    <span class="inline-block w-2 h-4 bg-emerald-400 animate-pulse"></span>
                                </div>
                            </div>
                        </div>
                        <div class="hidden">
                            <button id="loohood-close-terminal-btn" type="button">Close</button>
                        </div>
                    </div>

                    <div class="p-5 bg-[#f49300]/10 border border-[#f49300] rounded-2xl flex gap-4">
                        <span class="material-symbols-outlined text-[#f49300] shrink-0 mt-0.5">info</span>
                        <div>
                            <h4 class="text-sm font-bold text-[#f49300] mb-1">Automatic Webhooks</h4>
                            <p class="text-xs text-[#f49300]/70 leading-normal">
                                Deployment logs are streamed in real-time. You can also view more detailed build logs directly on your Cloudflare dashboard.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="max-w-7xl mx-auto px-6 py-12 text-center text-slate-400 text-sm">
            <div class="flex justify-center gap-6 mb-4">
                <a class="hover:text-[#f49300] transition-colors" href="https://loohood.web.id/docs" target="_blank" rel="noreferrer noopener">Documentation</a>
            </div>
            <p>© <?php echo esc_html(gmdate('Y')); ?> LooHood</p>
        </footer>
    </div>
</div>
