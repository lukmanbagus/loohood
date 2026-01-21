<?php
/**
 * Plugin header template
 *
 * @param string $subtitle_text Subtitle text to display next to the plugin name
 * @param string $dashboard_url URL for the Dashboard menu link
 * @param string $settings_url URL for the Settings menu link
 */

defined('ABSPATH') || exit;
?>

<header class="bg-white border-b border-slate-200 sticky top-0 z-10">
    <div class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-3">
            <img src="<?php echo esc_url(plugin_dir_url(dirname(__FILE__, 2)) . 'assets/icon.png'); ?>" class="w-24" alt="LooHood">
            <h1 class="m-0 text-[18px] leading-tight font-bold text-slate-900">
                <?php if ($subtitle_text !== ''): ?>
                    <span class="text-slate-400 font-semibold"><?php echo esc_html($subtitle_text); ?></span>
                <?php endif; ?>
            </h1>
        </div>
    </div>
</header>
