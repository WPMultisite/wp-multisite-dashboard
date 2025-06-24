<?php
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wp-multisite-dashboard') . '</p></div>';
}

$plugin_core = WP_MSD_Plugin_Core::get_instance();
$enabled_widgets = $plugin_core->get_enabled_widgets();
$settings_manager = new WP_MSD_Settings_Manager();
?>

<div class="wrap">
  <h1><?php echo esc_html( get_admin_page_title() ); ?>
      <span style="font-size: 13px; padding-left: 10px;">
          <?php printf( esc_html__( 'Version: %s', 'wp-multisite-dashboard' ), esc_html( WP_MSD_VERSION ) ); ?>
      </span>
      <a href="https://wpmultisite.com/document/wp-multisite-dashboard" target="_blank" class="button button-secondary" style="margin-left: 10px;">
          <?php esc_html_e( 'Documentation', 'wp-multisite-dashboard' ); ?>
      </a>
      <a href="https://wpmultisite.com/support/" target="_blank" class="button button-secondary">
          <?php esc_html_e( 'Support', 'wp-multisite-dashboard' ); ?>
      </a>
  </h1>

    <div class="msd-card">
        <h2><?php _e('Plugin Widget Configuration', 'wp-multisite-dashboard'); ?></h2>
        <p><?php _e('Enable or disable custom dashboard widgets provided by this plugin.', 'wp-multisite-dashboard'); ?></p>

        <form method="post" action="">
            <?php wp_nonce_field('msd_settings', 'msd_settings_nonce'); ?>

            <div class="msd-settings-grid">
                <?php foreach ($widget_options as $widget_id => $widget_name): ?>
                    <div class="msd-widget-toggle">
                        <label>
                            <input
                                type="checkbox"
                                name="widgets[<?php echo esc_attr($widget_id); ?>]"
                                value="1"
                                <?php checked(!empty($enabled_widgets[$widget_id])); ?>
                            />
                            <?php echo esc_html($widget_name); ?>
                        </label>
                        <p class="description">
                            <?php echo $settings_manager->get_widget_description($widget_id); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <h3><?php _e('System & Third-Party Widgets', 'wp-multisite-dashboard'); ?></h3>
            <p><?php _e('Control the display of WordPress system widgets and widgets from other plugins.', 'wp-multisite-dashboard'); ?></p>

            <?php
            $available_widgets = $settings_manager->get_available_system_widgets();
            $disabled_widgets = get_site_option('msd_disabled_system_widgets', []);

            if (!empty($available_widgets)):
            ?>
                <div class="msd-system-widgets-grid">
                    <?php
                    $system_widgets = array_filter($available_widgets, function($widget) {
                        return $widget['is_system'];
                    });

                    $third_party_widgets = array_filter($available_widgets, function($widget) {
                        return !$widget['is_system'] && !$widget['is_custom'];
                    });
                    ?>

                    <?php if (!empty($system_widgets)): ?>
                        <div class="msd-widget-section">
                            <h4><?php _e('WordPress System Widgets', 'wp-multisite-dashboard'); ?></h4>
                            <?php foreach ($system_widgets as $widget_id => $widget_data): ?>
                                <div class="msd-widget-toggle">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="system_widgets[<?php echo esc_attr($widget_id); ?>]"
                                            value="1"
                                            <?php checked(!in_array($widget_id, $disabled_widgets)); ?>
                                        />
                                        <?php echo esc_html($widget_data['title']); ?>
                                        <span class="msd-widget-meta">(<?php echo esc_html($widget_data['context']); ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($third_party_widgets)): ?>
                        <div class="msd-widget-section">
                            <h4><?php _e('Third-Party Plugin Widgets', 'wp-multisite-dashboard'); ?></h4>
                            <?php foreach ($third_party_widgets as $widget_id => $widget_data): ?>
                                <div class="msd-widget-toggle">
                                    <label>
                                        <input
                                            type="checkbox"
                                            name="system_widgets[<?php echo esc_attr($widget_id); ?>]"
                                            value="1"
                                            <?php checked(!in_array($widget_id, $disabled_widgets)); ?>
                                        />
                                        <?php echo esc_html($widget_data['title']); ?>
                                        <span class="msd-widget-meta">(<?php echo esc_html($widget_data['context']); ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="msd-widget-section">
                            <h4><?php _e('Third-Party Plugin Widgets', 'wp-multisite-dashboard'); ?></h4>
                            <div class="msd-no-third-party">
                                <p><?php _e('No third-party widgets detected yet.', 'wp-multisite-dashboard'); ?></p>
                                <p class="description"><?php _e('Third-party widgets are automatically detected when you visit the network dashboard. If you have plugins that add dashboard widgets, visit the dashboard first, then return here to see them.', 'wp-multisite-dashboard'); ?></p>
                                <a href="<?php echo network_admin_url(); ?>" class="button button-secondary">
                                    <?php _e('Visit Network Dashboard', 'wp-multisite-dashboard'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="msd-no-widgets">
                    <p><?php _e('No system widgets found.', 'wp-multisite-dashboard'); ?></p>
                </div>
            <?php endif; ?>

            <p class="submit">
                <?php submit_button(__('Save Widget Settings', 'wp-multisite-dashboard'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>

    <div class="msd-card">
        <h2><?php _e('Cache Management', 'wp-multisite-dashboard'); ?></h2>
        <p><?php _e('Clear cached data to refresh dashboard widgets.', 'wp-multisite-dashboard'); ?></p>

        <div class="msd-cache-actions">
            <button type="button" class="button" onclick="MSD.clearCache('all')">
                ↻
                <?php _e('Clear All Caches', 'wp-multisite-dashboard'); ?>
            </button>
            <button type="button" class="button" onclick="MSD.clearCache('network')">
                <span class="dashicons dashicons-admin-multisite"></span>
                <?php _e('Clear Network Data', 'wp-multisite-dashboard'); ?>
            </button>
            <button type="button" class="button" onclick="MSD.clearWidgetCache()">
                <span class="dashicons dashicons-dashboard"></span>
                <?php _e('Clear Widget Cache', 'wp-multisite-dashboard'); ?>
            </button>
        </div>

        <p class="description">
            <?php _e('Clearing caches will force the dashboard widgets to reload fresh data on the next page visit. Widget cache contains the list of detected third-party widgets.', 'wp-multisite-dashboard'); ?>
        </p>
    </div>

    <div class="msd-card">
        <h2><?php _e('Plugin Information', 'wp-multisite-dashboard'); ?></h2>
        <p><?php _e('Current plugin status and update information.', 'wp-multisite-dashboard'); ?></p>

        <div class="msd-plugin-info">
            <div class="msd-info-row">
                <span class="msd-info-label"><?php _e('Current Version:', 'wp-multisite-dashboard'); ?></span>
                <span class="msd-info-value"><?php echo esc_html(WP_MSD_VERSION); ?></span>
            </div>

            <div class="msd-info-row">
                <span class="msd-info-label"><?php _e('Update Status:', 'wp-multisite-dashboard'); ?></span>
                <span class="msd-info-value" id="msd-update-status">
                    <button type="button" class="button button-small" onclick="MSD.checkForUpdates()">
                        <?php _e('Check for Updates', 'wp-multisite-dashboard'); ?>
                    </button>
                </span>
            </div>
        </div>
    </div>
</div>

<style>
.msd-cache-actions {
    display: flex;
    gap: 12px;
    margin: 16px 0;
    flex-wrap: wrap;
}

.msd-cache-actions .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

.msd-plugin-info {
    background: #f8f9fa;
    padding: 16px;
    border-radius: 4px;
    border: 1px solid #e9ecef;
}

.msd-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #e9ecef;
}

.msd-info-row:last-child {
    border-bottom: none;
}

.msd-info-label {
    font-weight: 600;
    color: var(--msd-text);
}

.msd-info-value {
    color: var(--msd-text-light);
}

.msd-update-available {
    color: #d63638;
    font-weight: 600;
}

.msd-update-current {
    color: #00a32a;
    font-weight: 600;
}

.msd-settings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.msd-widget-toggle {
    background: var(--msd-bg-light);
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    padding: 20px;
    transition: all 0.2s ease;
}

.msd-widget-toggle:hover {
    border-color: var(--msd-primary);
}

.msd-widget-toggle label {
    font-weight: 400;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
    cursor: pointer;
}

.msd-widget-toggle input[type="checkbox"] {
    margin: 0;
    width: 18px;
    height: 18px;
}

.msd-widget-toggle .description {
    margin: 0;
    font-size: 11px;
    color: #8c8c8c;
    line-height: 1.4;
}

.msd-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    max-width: unset;
    margin-top: 20px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    overflow: hidden;
}

.msd-system-widgets-grid {
    margin-top: 20px;
}

.msd-widget-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.msd-widget-section h4 {
    margin: 0 0 15px 0;
    color: var(--msd-text);
    font-size: 15px;
    font-weight: 600;
    padding-bottom: 8px;
    border-bottom: 2px solid #007cba;
}

.msd-widget-section .msd-widget-toggle {
    margin-bottom: 12px;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border: 1px solid #ddd;
    transition: border-color 0.2s ease;
}

.msd-widget-section .msd-widget-toggle:hover {
    border-color: var(--msd-primary);
}

.msd-widget-section .msd-widget-toggle label {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    font-size: 14px;
}

.msd-widget-meta {
    font-size: 12px;
    color: var(--msd-text-light);
    font-style: italic;
    margin-left: auto;
}

.msd-no-widgets,
.msd-no-third-party {
    text-align: center;
    padding: 40px 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 2px dashed #ddd;
    color: var(--msd-text-light);
}

@media screen and (max-width: 768px) {
    .msd-cache-actions {
        flex-direction: column;
    }

    .msd-info-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .msd-widget-section {
        margin-bottom: 20px;
        padding: 15px;
    }

    .msd-widget-section .msd-widget-toggle label {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }

    .msd-widget-meta {
        margin-left: 0;
    }

    .msd-settings-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    'use strict';

    // 确保全局 MSD 对象存在
    window.MSD = window.MSD || {};

    // 清除缓存功能
    window.MSD.clearCache = function(type) {
        if (!confirm('Are you sure you want to clear the cache?')) {
            return;
        }

        $.post(msdAjax.ajaxurl, {
            action: 'msd_clear_cache',
            cache_type: type,
            nonce: msdAjax.nonce
        }, function(response) {
            if (response.success) {
                alert('Cache cleared successfully!');
            } else {
                alert('Failed to clear cache: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Failed to clear cache due to network error.');
        });
    };

    // 检查更新功能
    window.MSD.checkForUpdates = function() {
        var $status = $('#msd-update-status');
        var $button = $status.find('button');

        $button.prop('disabled', true).text('Checking...');

        $.post(msdAjax.ajaxurl, {
            action: 'msd_check_plugin_update',
            nonce: msdAjax.nonce
        }, function(response) {
            if (response.success) {
                if (response.data.version) {
                    $status.html('<span class="msd-update-available">Version ' + response.data.version + ' available!</span>');
                    if (response.data.details_url) {
                        $status.append(' <a href="' + response.data.details_url + '" target="_blank">View Details</a>');
                    }
                } else {
                    $status.html('<span class="msd-update-current">Up to date</span>');
                }
            } else {
                $button.prop('disabled', false).text('Check for Updates');
                alert('Failed to check for updates: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            $button.prop('disabled', false).text('Check for Updates');
            alert('Failed to check for updates due to network error.');
        });
    };

    // 清除小部件缓存功能
    window.MSD.clearWidgetCache = function() {
        if (!confirm('Are you sure you want to clear the widget cache? This will refresh the list of detected widgets.')) {
            return;
        }

        $.post(msdAjax.ajaxurl, {
            action: 'msd_clear_widget_cache',
            nonce: msdAjax.nonce
        }, function(response) {
            if (response.success) {
                alert('Widget cache cleared successfully! Please reload the page to see updated widgets.');
                location.reload();
            } else {
                alert('Failed to clear widget cache: ' + (response.data || 'Unknown error'));
            }
        }).fail(function() {
            alert('Failed to clear widget cache due to network error.');
        });
    };

    // 调试信息
    console.log('MSD Settings loaded with functions:', Object.keys(window.MSD));
    console.log('msdAjax object:', msdAjax);
});

</script>
