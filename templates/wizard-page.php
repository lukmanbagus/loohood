<?php defined('ABSPATH') || exit; ?>
<div class="wrap" id="loohood-wizard-root">
    <div class="bg-[#f6f7f9] font-sans text-slate-900 min-h-screen -mx-5 -mt-2">
        <?php loohood_render_plugin_header('Setup Wizard'); ?>

        <main class="max-w-7xl mx-auto px-6 py-8">
            <div class="loohood-step-navigation flex items-center justify-between max-w-4xl mx-auto mb-8">
                <div class="loohood-step-item loohood-step-1 flex flex-col items-center gap-2 cursor-pointer select-none opacity-50" data-step="1">
                    <div class="loohood-step-nav-number w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-extrabold">1</div>
                    <div class="loohood-step-nav-label text-xs font-semibold text-slate-500">GitHub</div>
                </div>
                <div class="loohood-step-connector flex-1 h-0.5 bg-slate-200 mx-4 rounded mb-5"></div>
                <div class="loohood-step-item loohood-step-2 flex flex-col items-center gap-2 cursor-pointer select-none opacity-50" data-step="2">
                    <div class="loohood-step-nav-number w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-extrabold">2</div>
                    <div class="loohood-step-nav-label text-xs font-semibold text-slate-500">Repo</div>
                </div>
                <div class="loohood-step-connector flex-1 h-0.5 bg-slate-200 mx-4 rounded mb-5"></div>
                <div class="loohood-step-item loohood-step-3 flex flex-col items-center gap-2 cursor-pointer select-none opacity-50" data-step="3">
                    <div class="loohood-step-nav-number w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-extrabold">3</div>
                    <div class="loohood-step-nav-label text-xs font-semibold text-slate-500">CF Token</div>
                </div>
                <div class="loohood-step-connector flex-1 h-0.5 bg-slate-200 mx-4 rounded mb-5"></div>
                <div class="loohood-step-item loohood-step-4 flex flex-col items-center gap-2 cursor-pointer select-none opacity-50" data-step="4">
                    <div class="loohood-step-nav-number w-10 h-10 rounded-full bg-slate-200 text-slate-600 flex items-center justify-center font-extrabold">4</div>
                    <div class="loohood-step-nav-label text-xs font-semibold text-slate-500">CF Project</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                <section class="lg:col-span-7">
                    <div id="loohood-error" class="hidden rounded-2xl border border-rose-200 bg-rose-50 px-6 py-5 text-rose-900">
                        <div class="font-extrabold mb-1">Error</div>
                        <div id="loohood-error-message" class="text-sm text-rose-700 mb-4"></div>
                        <button type="button" class="px-4 py-2 rounded-lg border border-rose-200 bg-white hover:bg-rose-100 transition-colors font-semibold" id="loohood-retry-btn">Retry</button>
                    </div>

                    <div id="loohood-step-1" class="loohood-step active">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-2xl bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M7 12l5 5l-1.5 1.5a3.536 3.536 0 1 1 -5 -5l1.5 -1.5" /><path d="M17 12l-5 -5l1.5 -1.5a3.536 3.536 0 1 1 5 5l-1.5 1.5" /><path d="M3 21l2.5 -2.5" /><path d="M18.5 5.5l2.5 -2.5" /><path d="M10 11l-2 2" /><path d="M13 14l-2 2" /></svg>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-extrabold mb-1">Connect GitHub</h2>
                                    <p class="text-slate-500 text-sm">Link your GitHub account to manage your static site's source code.</p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-extrabold text-slate-700 mb-2" for="loohood-github-token">GitHub Personal Access Token</label>
                                <input type="password" id="loohood-github-token" class="!w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm focus:!border-[#f49300] focus:!ring-4 focus:!ring-orange-100" placeholder="ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx">
                                <div class="mt-3 flex items-center gap-1 text-xs text-slate-500">
                                    <span class="material-symbols-outlined text-[16px] text-slate-500">info</span>
                                    <span>Requires <b>repo</b> and <b>workflow</b> permissions.</span>
                                </div>

                                <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                    <a class="text-sm font-semibold text-[#f49300] hover:underline inline-flex items-center gap-1" href="https://github.com/settings/tokens/new?scopes=repo,workflow&description=WP%20Static%20Exporter" target="_blank" rel="noreferrer noopener">
                                        Create a new token on GitHub
                                        <span aria-hidden="true">↗</span>
                                    </a>
                                    <button type="button" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] focus:ring-4 focus:ring-orange-100 transition-all" id="loohood-step-1-btn">Connect GitHub Account</button>
                                </div>

                                <div class="my-7 h-px bg-slate-100"></div>

                                <div>
                                    <div class="text-[11px] font-extrabold uppercase tracking-widest text-slate-400 mb-4">Why do we need this?</div>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div class="flex gap-3 items-start">
                                            <div class="w-8 h-8 rounded-lg bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                                <span class="material-symbols-outlined text-lg">alt_route</span>
                                            </div>
                                            <div class="text-xs text-slate-500 leading-relaxed">To automatically push your static files to a repository.</div>
                                        </div>
                                        <div class="flex gap-3 items-start">
                                            <div class="w-8 h-8 rounded-lg bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                                <span class="material-symbols-outlined text-lg">rocket_launch</span>
                                            </div>
                                            <div class="text-xs text-slate-500 leading-relaxed">To trigger CI/CD pipelines for seamless deployments.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="loohood-step-2" class="loohood-step hidden">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-2xl bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M15 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M11 8a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M11 16a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" /><path d="M12 15v-6" /><path d="M15 11l-2 -2" /><path d="M11 7l-1.9 -1.9" /><path d="M13.446 2.6l7.955 7.954a2.045 2.045 0 0 1 0 2.892l-7.955 7.955a2.045 2.045 0 0 1 -2.892 0l-7.955 -7.955a2.045 2.045 0 0 1 0 -2.892l7.955 -7.955a2.045 2.045 0 0 1 2.892 0" /></svg>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-extrabold mb-1">Create GitHub Repository</h2>
                                    <p class="text-slate-500 text-sm">Create a private repo, clone it, create <code class="font-mono text-xs px-2 py-0.5 rounded-lg bg-slate-50 border border-slate-200">index.html</code>, then commit &amp; push.</p>
                                </div>
                            </div>

                            <label class="block text-xs font-extrabold text-slate-700 mb-2" for="loohood-repo-name">Repository Name</label>
                            <input type="text" id="loohood-repo-name" class="!w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm focus:!border-[#f49300] focus:!ring-4 focus:!ring-orange-100" value="wp-static-<?php echo esc_attr( time() ); ?>">
                            <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                <span class="material-symbols-outlined text-base text-slate-400">lock</span>
                                <span>Repository will be created as private.</span>
                            </div>

                            <div class="mt-6 flex justify-end">
                                <button type="button" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] focus:ring-4 focus:ring-orange-100 transition-all" id="loohood-step-2-btn">Create Repository</button>
                            </div>
                        </div>
                    </div>

                    <div id="loohood-step-3" class="loohood-step hidden">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-2xl bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M3 20h7" /><path d="M14 20h7" /><path d="M10 20a2 2 0 1 0 4 0a2 2 0 0 0 -4 0" /><path d="M12 16v2" /><path d="M8 16.004h-1.343c-2.572 -.004 -4.657 -2.011 -4.657 -4.487c0 -2.475 2.085 -4.482 4.657 -4.482c.393 -1.762 1.794 -3.2 3.675 -3.773c1.88 -.572 3.956 -.193 5.444 1c1.488 1.19 2.162 3.007 1.77 4.769h.99c1.913 0 3.464 1.56 3.464 3.486c0 1.927 -1.551 3.487 -3.465 3.487h-2.535" /></svg>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-extrabold mb-1">Connect Cloudflare</h2>
                                    <p class="text-slate-500 text-sm">Connect Cloudflare Pages using an API token.</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm mb-6">
                                For token permission, please read documentation <a class="text-[#f49300] font-semibold hover:underline" href="https://loohood.web.id/docs/" target="_blank" rel="noreferrer noopener">here</a>.
                            </div>

                            <label class="block text-xs font-extrabold text-slate-700 mb-2" for="loohood-cloudflare-token">Cloudflare API Token</label>
                            <input type="password" id="loohood-cloudflare-token" class="!w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm focus:!border-[#f49300] focus:!ring-4 focus:!ring-orange-100" placeholder="Your Cloudflare API Token">
                            <div class="mt-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <a class="text-sm font-semibold text-[#f49300] hover:underline inline-flex items-center gap-1" href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noreferrer noopener">
                                    Create a new token on Cloudflare
                                    <span aria-hidden="true">↗</span>
                                </a>
                                <button type="button" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] focus:ring-4 focus:ring-orange-100 transition-all" id="loohood-step-3-btn">Connect Cloudflare</button>
                            </div>
                        </div>
                    </div>

                    <div id="loohood-step-4" class="loohood-step hidden">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-2xl bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M12 3l8 4.5l0 9l-8 4.5l-8 -4.5l0 -9l8 -4.5" /><path d="M12 12l8 -4.5" /><path d="M12 12l0 9" /><path d="M12 12l-8 -4.5" /><path d="M16 5.25l-8 4.5" /></svg>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-extrabold mb-1">Create Cloudflare Pages Project</h2>
                                    <p class="text-slate-500 text-sm">Create a Pages project and connect it to your GitHub repository.</p>
                                </div>
                            </div>

                            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-900 text-sm mb-6">
                                Before creating the project, install &amp; authorize the <a class="text-[#f49300] font-semibold hover:underline" href="https://github.com/apps/cloudflare-workers-and-pages" target="_blank" rel="noreferrer noopener">Cloudflare Workers and Pages</a> GitHub App so Cloudflare can access your repository.
                                <?php
                                $loohood_cf_github_install_url = 'https://github.com/apps/cloudflare-workers-and-pages/installations/new';
                                ?>
                                <div class="mt-3">
                                    <button type="button" class="px-4 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 transition-colors font-semibold" id="loohood-open-github-cloudflare-app" data-install-url="<?php echo esc_attr($loohood_cf_github_install_url); ?>">Install &amp; Authorize</button>
                                </div>
                            </div>

                            <div class="space-y-5">
                                <div>
                                    <label class="block text-xs font-extrabold text-slate-700 mb-2" for="loohood-project-name">Project Name</label>
                                    <input type="text" id="loohood-project-name" class="!w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm focus:!border-[#f49300] focus:!ring-4 focus:!ring-orange-100" value="wp-static-<?php echo esc_attr( time() ); ?>-pages">
                                    <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                        <span class="material-symbols-outlined text-base text-slate-400">info</span>
                                        <span>The project will be connected to the GitHub repository automatically.</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-xs font-extrabold text-slate-700 mb-2" for="loohood-custom-domain">Custom Domain (Optional)</label>
                                    <input type="text" id="loohood-custom-domain" class="!w-full !rounded-lg !border !border-slate-200 !bg-slate-50 !px-4 !py-3 !text-sm focus:!border-[#f49300] focus:!ring-4 focus:!ring-orange-100" placeholder="example.com">
                                    <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                        <span class="material-symbols-outlined text-base text-slate-400">info</span>
                                        <span>If provided, the plugin will attach this domain.</span>
                                    </div>
                                </div>

                                <div class="flex justify-end">
                                    <button type="button" class="px-6 py-3 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] focus:ring-4 focus:ring-orange-100 transition-all" id="loohood-step-4-btn">Create Project</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="loohood-success" class="loohood-step hidden">
                        <div class="bg-white rounded-2xl p-8 shadow-sm border border-slate-200">
                            <div class="flex items-start gap-4 mb-6">
                                <div class="w-14 h-14 rounded-2xl bg-[#f49300]/10 text-[#f49300] flex items-center justify-center">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M8.56 3.69a9 9 0 0 0 -2.92 1.95" /><path d="M3.69 8.56a9 9 0 0 0 -.69 3.44" /><path d="M3.69 15.44a9 9 0 0 0 1.95 2.92" /><path d="M8.56 20.31a9 9 0 0 0 3.44 .69" /><path d="M15.44 20.31a9 9 0 0 0 2.92 -1.95" /><path d="M20.31 15.44a9 9 0 0 0 .69 -3.44" /><path d="M20.31 8.56a9 9 0 0 0 -1.95 -2.92" /><path d="M15.44 3.69a9 9 0 0 0 -3.44 -.69" /><path d="M9 12l2 2l4 -4" /></svg>
                                </div>
                                <div class="flex-1">
                                    <h2 class="text-xl font-extrabold mb-1">Setup Complete!</h2>
                                    <p class="text-slate-500 text-sm">LooHood has been configured and deployed.</p>
                                </div>
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div>
                                    <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-3">GitHub Repository</div>
                                    <a id="loohood-github-link" href="#" target="_blank" class="inline-flex items-center justify-center gap-2 px-5 py-1 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] hover:!text-white focus:ring-4 focus:ring-orange-100 transition-all w-full sm:w-auto">
                                        <span class="material-symbols-outlined text-lg">code</span>
                                        View on GitHub
                                    </a>
                                </div>
                                <div>
                                    <div class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mb-3">Cloudflare Pages</div>
                                    <div class="flex flex-wrap gap-3">
                                        <a id="loohood-cloudflare-link" href="#" target="_blank" class="inline-flex items-center justify-center gap-2 px-5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 transition-colors font-extrabold">
                                            <span class="material-symbols-outlined text-lg">language</span>
                                            View Live Site
                                        </a>
                                        <a id="loohood-cloudflare-preview-link" href="#" target="_blank" class="inline-flex items-center justify-center gap-2 px-5 py-1 rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 transition-colors font-extrabold">
                                            <span class="material-symbols-outlined text-lg">visibility</span>
                                            View .pages.dev Preview
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex justify-end">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=wp-static-exporter')); ?>" class="inline-flex items-center justify-center gap-2 px-6 py-2 rounded-lg bg-[#f49300] text-white font-extrabold hover:bg-[#d37f00] hover:!text-white focus:ring-4 focus:ring-orange-100 transition-all">
                                    <span class="material-symbols-outlined text-lg">dashboard</span>
                                    Go to Dashboard
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="loohood-progress-bar hidden">
                        <div class="loohood-progress-fill h-1 bg-[#f49300] rounded"></div>
                    </div>
                </section>

                <aside class="lg:col-span-5 lg:sticky lg:top-24 space-y-6">
                    <div id="loohood-wizard-terminal" class="bg-[#1e1e1e] rounded-2xl overflow-hidden shadow-2xl border border-slate-800">
                        <div class="bg-[#2d2d2d] px-4 py-3 flex items-center justify-between border-b border-black/20">
                            <div class="flex gap-2">
                                <div class="w-3 h-3 rounded-full bg-[#ff5f56]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#ffbd2e]"></div>
                                <div class="w-3 h-3 rounded-full bg-[#27c93f]"></div>
                            </div>
                            <div class="text-[10px] font-mono text-slate-400 uppercase tracking-widest font-bold">Terminal</div>
                            <div class="w-12"></div>
                        </div>
                        <div class="p-6 font-mono text-sm leading-relaxed h-[520px] overflow-y-auto">
                            <div id="loohood-terminal-output" class="space-y-2 text-slate-300">
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-sky-400">➜</span>
                                    <span class="text-slate-300">Starting LooHood setup wizard...</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-sky-400">➜</span>
                                    <span class="text-slate-300">Detecting environment hooks...</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-emerald-400">➜</span>
                                    <span class="text-slate-300">✓ Local WordPress instance detected</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-emerald-400">➜</span>
                                    <span class="text-slate-300">✓ Remote API connection established</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-sky-400">➜</span>
                                    <span class="text-slate-300">Waiting for GitHub authentication...</span>
                                </div>
                                <div class="flex gap-3">
                                    <span class="text-slate-500 shrink-0"><?php echo esc_html(gmdate('H:i:s')); ?></span>
                                    <span class="text-amber-300">➜</span>
                                    <span class="text-slate-300">Awaiting Personal Access Token input...</span>
                                    <span class="inline-block w-2 h-4 bg-amber-300 animate-pulse"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
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
