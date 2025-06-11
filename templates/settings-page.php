<?php
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['updated']) && $_GET['updated'] === 'true') {
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wp-multisite-dashboard') . '</p></div>';
}
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
        <h2><?php _e('Widget Configuration', 'wp-multisite-dashboard'); ?></h2>
        <p><?php _e('Enable or disable dashboard widgets according to your needs.', 'wp-multisite-dashboard'); ?></p>

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
                                <?php checked(!empty($this->enabled_widgets[$widget_id])); ?>
                            />
                            <?php echo esc_html($widget_name); ?>
                        </label>
                        <p class="description">
                            <?php echo $this->get_widget_description($widget_id); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="submit">
                <?php submit_button(__('Save Widget Settings', 'wp-multisite-dashboard'), 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>

    <div class="msd-card">
        <h2><?php _e('News Sources Configuration', 'wp-multisite-dashboard'); ?></h2>
        <p><?php _e('Configure custom RSS news sources for the Network News widget.', 'wp-multisite-dashboard'); ?></p>

        <div class="msd-news-sources-config">
            <?php
            $news_sources = get_site_option('msd_news_sources', [
                [
                    'name' => 'WordPress News',
                    'url' => 'https://wordpress.org/news/feed/',
                    'enabled' => true
                ]
            ]);
            ?>

            <div class="msd-current-sources">
                <h3><?php _e('Current News Sources', 'wp-multisite-dashboard'); ?></h3>

                <?php if (!empty($news_sources)): ?>
                    <table class="wp-list-table widefat striped">
                        <thead>
                            <tr>
                                <th><?php _e('Source Name', 'wp-multisite-dashboard'); ?></th>
                                <th><?php _e('RSS URL', 'wp-multisite-dashboard'); ?></th>
                                <th><?php _e('Status', 'wp-multisite-dashboard'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($news_sources as $source): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($source['name']); ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url($source['url']); ?>" target="_blank" class="msd-url-link">
                                            <?php echo esc_html($source['url']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php if ($source['enabled']): ?>
                                            <span class="msd-status-badge msd-status-active"><?php _e('Enabled', 'wp-multisite-dashboard'); ?></span>
                                        <?php else: ?>
                                            <span class="msd-status-badge msd-status-inactive"><?php _e('Disabled', 'wp-multisite-dashboard'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="msd-empty-state">
                        <p><?php _e('No news sources configured.', 'wp-multisite-dashboard'); ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="msd-news-actions">
                <button type="button" class="button button-primary" onclick="MSD.showNewsSourcesModal()">
                    <span class="dashicons dashicons-rss"></span>
                    <?php _e('Manage News Sources', 'wp-multisite-dashboard'); ?>
                </button>

                <button type="button" class="button button-secondary" onclick="MSD.clearNewsCache()">
                    ↻
                    <?php _e('Clear News Cache', 'wp-multisite-dashboard'); ?>
                </button>
            </div>

            <div class="msd-news-help">
                <h4><?php _e('Popular RSS Sources', 'wp-multisite-dashboard'); ?></h4>
                <div class="msd-rss-examples">
                    <div class="msd-rss-example">
                        <strong>WordPress News:</strong>
                        <code>https://wordpress.org/news/feed/</code>
                    </div>
                    <div class="msd-rss-example">
                        <strong>WP Tavern:</strong>
                        <code>https://wptavern.com/feed</code>
                    </div>
                </div>
                <p class="description">
                    <?php _e('You can add any valid RSS or Atom feed URL. The news widget will fetch and display the latest articles from your configured sources.', 'wp-multisite-dashboard'); ?>
                </p>
            </div>
        </div>
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
        </div>

        <p class="description">
            <?php _e('Clearing caches will force the dashboard widgets to reload fresh data on the next page visit.', 'wp-multisite-dashboard'); ?>
        </p>
    </div>
</div>

<style>
.msd-status-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 400;
    text-transform: uppercase;
}

.msd-status-active {
    background: #d1eddb;
    color: #155724;
}

.msd-status-inactive {
    background: #f8d7da;
    color: #721c24;
}

.msd-cache-actions,
.msd-news-actions {
    display: flex;
    gap: 12px;
    margin: 16px 0;
    flex-wrap: wrap;
}

.msd-cache-actions .button,
.msd-news-actions .button {
    display: flex;
    align-items: center;
    gap: 6px;
}

.msd-news-sources-config {
    margin-top: 16px;
}

.msd-current-sources h3 {
    margin-bottom: 12px;
}

.msd-url-link {
    word-break: break-all;
    color: var(--msd-primary);
    text-decoration: none;
}

.msd-url-link:hover {
    text-decoration: underline;
}

.msd-news-help {
    margin-top: 24px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 4px;
}

.msd-news-help h4 {
    margin: 0 0 12px 0;
    color: var(--msd-text);
}

.msd-rss-examples {
    display: grid;
    gap: 8px;
    margin-bottom: 12px;
}

.msd-rss-example {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px;
    background: white;
    border-radius: 3px;
    font-size: 13px;
}

.msd-rss-example strong {
    color: var(--msd-text);
}

.msd-rss-example code {
    background: #e9ecef;
    padding: 2px 6px;
    border-radius: 2px;
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: var(--msd-text-light);
}

@media screen and (max-width: 768px) {
    .msd-cache-actions,
    .msd-news-actions {
        flex-direction: column;
    }

    .msd-rss-example {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}
</style>

<script>
window.MSD = window.MSD || {};

MSD.clearCache = function(type) {
    if (!confirm('<?php echo esc_js(__('Are you sure you want to clear the cache?', 'wp-multisite-dashboard')); ?>')) {
        return;
    }

    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    jQuery.post(ajaxurl, {
        action: 'msd_clear_cache',
        cache_type: type,
        nonce: '<?php echo wp_create_nonce('msd_clear_cache'); ?>'
    }, function(response) {
        if (response.success) {
            alert('<?php echo esc_js(__('Cache cleared successfully!', 'wp-multisite-dashboard')); ?>');
        } else {
            alert('<?php echo esc_js(__('Failed to clear cache.', 'wp-multisite-dashboard')); ?>');
        }
    });
};

MSD.clearNewsCache = function() {
    if (!confirm('<?php echo esc_js(__('Are you sure you want to clear the news cache?', 'wp-multisite-dashboard')); ?>')) {
        return;
    }

    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    jQuery.post(ajaxurl, {
        action: 'msd_refresh_widget_data',
        widget: 'custom_news',
        nonce: '<?php echo wp_create_nonce('msd_ajax_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('<?php echo esc_js(__('News cache cleared successfully!', 'wp-multisite-dashboard')); ?>');
        } else {
            alert('<?php echo esc_js(__('Failed to clear news cache.', 'wp-multisite-dashboard')); ?>');
        }
    });
};

MSD.showNewsSourcesModal = function() {
    if (typeof jQuery !== 'undefined' && jQuery('#msd-news-sources-modal').length) {
        jQuery('#msd-news-sources-modal').fadeIn(200);
        jQuery('body').addClass('modal-open');
    } else {
        alert('<?php echo esc_js(__('Please go to the dashboard to configure news sources.', 'wp-multisite-dashboard')); ?>');
    }
};

MSD.hideNewsSourcesModal = function() {
    if (typeof jQuery !== 'undefined') {
        jQuery('#msd-news-sources-modal').fadeOut(200);
        jQuery('body').removeClass('modal-open');
    }
};

MSD.saveNewsSources = function() {
    if (typeof jQuery === 'undefined') {
        alert('jQuery is required for this functionality');
        return;
    }

    var sources = [];
    jQuery('.msd-news-source-item').each(function() {
        var $item = jQuery(this);
        var name = $item.find('.msd-news-name').val().trim();
        var url = $item.find('.msd-news-url').val().trim();
        var enabled = $item.find('.msd-news-enabled').is(':checked');

        if (name && url) {
            sources.push({
                name: name,
                url: url,
                enabled: enabled
            });
        }
    });

    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    jQuery.post(ajaxurl, {
        action: 'msd_save_news_sources',
        sources: sources,
        nonce: '<?php echo wp_create_nonce('msd_ajax_nonce'); ?>'
    }, function(response) {
        if (response.success) {
            alert('<?php echo esc_js(__('News sources saved successfully!', 'wp-multisite-dashboard')); ?>');
            MSD.hideNewsSourcesModal();
            location.reload();
        } else {
            alert('<?php echo esc_js(__('Failed to save news sources.', 'wp-multisite-dashboard')); ?>');
        }
    });
};
</script>
