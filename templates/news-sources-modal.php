<?php
if (!defined('ABSPATH')) {
    exit;
}

$news_sources = get_site_option('msd_news_sources', [
    [
        'name' => 'WordPress News',
        'url' => 'https://wordpress.org/news/feed/',
        'enabled' => true
    ]
]);
?>

<div id="msd-news-sources-modal" class="msd-modal" style="display: none;">
    <div class="msd-modal-content">
        <div class="msd-modal-header">
            <h3><?php _e('Configure News Sources', 'wp-multisite-dashboard'); ?></h3>
            <button type="button" class="msd-modal-close" onclick="MSD.hideNewsSourcesModal()">&times;</button>
        </div>

        <div class="msd-modal-body">
            <div id="msd-news-sources-editor">
                <?php if (!empty($news_sources)): ?>
                    <?php foreach ($news_sources as $index => $source): ?>
                        <div class="msd-news-source-item">
                            <div class="msd-source-row">
                                <input type="text"
                                       placeholder="<?php esc_attr_e('Source Name', 'wp-multisite-dashboard'); ?>"
                                       value="<?php echo esc_attr($source['name']); ?>"
                                       class="msd-news-name"
                                       required>
                                <input type="url"
                                       placeholder="<?php esc_attr_e('RSS Feed URL', 'wp-multisite-dashboard'); ?>"
                                       value="<?php echo esc_url($source['url']); ?>"
                                       class="msd-news-url"
                                       required>
                            </div>

                            <div class="msd-source-options">
                                <label class="msd-checkbox-label">
                                    <input type="checkbox"
                                           class="msd-news-enabled"
                                           <?php checked(!empty($source['enabled'])); ?>>
                                    <?php _e('Enabled', 'wp-multisite-dashboard'); ?>
                                </label>

                                <button type="button" class="msd-remove-source">
                                    <?php _e('Remove', 'wp-multisite-dashboard'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="msd-add-source-section">
                <button type="button" id="msd-add-news-source" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add News Source', 'wp-multisite-dashboard'); ?>
                </button>
            </div>
        </div>

        <div class="msd-modal-footer">
            <button type="button" class="button button-primary" onclick="MSD.saveNewsSources()">
                <?php _e('Save News Sources', 'wp-multisite-dashboard'); ?>
            </button>
            <button type="button" class="button" onclick="MSD.hideNewsSourcesModal()">
                <?php _e('Cancel', 'wp-multisite-dashboard'); ?>
            </button>
        </div>
    </div>
</div>

<style>
.msd-modal-intro {
    margin-bottom: 20px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 4px solid var(--msd-primary);
}

.msd-modal-intro p {
    margin: 0;
    color: var(--msd-text);
    font-size: 14px;
    line-height: 1.4;
}

.msd-news-source-item {
    margin-bottom: 16px;
    padding: 16px;
    background: var(--msd-bg);
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    transition: border-color 0.2s ease;
}

.msd-news-source-item:hover {
    border-color: var(--msd-primary);
}

.msd-source-row {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 12px;
    margin-bottom: 12px;
}

.msd-source-options {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.msd-news-name,
.msd-news-url {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius-small);
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.msd-news-name:focus,
.msd-news-url:focus {
    outline: none;
    border-color: var(--msd-primary);
    box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.1);
}

.msd-checkbox-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    color: var(--msd-text);
    cursor: pointer;
    white-space: nowrap;
}

.msd-checkbox-label input[type="checkbox"] {
    margin: 0;
}

.msd-add-source-section {
    text-align: center;
    margin: 24px 0;
    padding: 16px;
    border: 2px dashed var(--msd-border);
    border-radius: var(--msd-radius);
    transition: border-color 0.2s ease;
}

.msd-add-source-section:hover {
    border-color: var(--msd-primary);
}

#msd-add-news-source {
    display: flex;
    align-items: center;
    gap: 6px;
}

.msd-news-help {
    margin-top: 24px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: var(--msd-radius);
    border: 1px solid #e9ecef;
}

.msd-news-help h4 {
    margin: 0 0 12px 0;
    color: var(--msd-text);
    font-size: 14px;
    font-weight: 400;
}

.msd-news-help h5 {
    margin: 16px 0 8px 0;
    color: var(--msd-text);
    font-size: 13px;
    font-weight: 400;
}

.msd-rss-suggestions {
    display: grid;
    gap: 8px;
    margin-bottom: 16px;
}

.msd-rss-suggestion {
    display: grid;
    grid-template-columns: 120px 1fr;
    gap: 8px;
    padding: 8px;
    background: white;
    border-radius: 3px;
    font-size: 12px;
    align-items: start;
}

.msd-rss-suggestion strong {
    color: var(--msd-text);
    font-size: 11px;
    font-weight: 400;
}

.msd-rss-suggestion code {
    background: #e9ecef;
    padding: 2px 4px;
    border-radius: 2px;
    font-family: 'Courier New', monospace;
    font-size: 10px;
    word-break: break-all;
    grid-column: 1 / -1;
    margin: 2px 0;
}

.msd-rss-desc {
    color: var(--msd-text-light);
    font-size: 11px;
    grid-column: 1 / -1;
    line-height: 1.3;
}

.msd-feed-tips {
    background: white;
    padding: 12px;
    border-radius: 3px;
    border: 1px solid #e9ecef;
}

.msd-feed-tips ul {
    margin: 8px 0 0 0;
    padding-left: 16px;
}

.msd-feed-tips li {
    font-size: 12px;
    color: var(--msd-text-light);
    line-height: 1.4;
    margin-bottom: 4px;
}

@media screen and (max-width: 600px) {
    .msd-source-row {
        grid-template-columns: 1fr;
    }

    .msd-source-options {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }

    .msd-rss-suggestion {
        grid-template-columns: 1fr;
    }

    .msd-modal-content {
        margin: 20px;
        width: auto;
    }
}

.msd-news-source-item.error {
    border-color: var(--msd-danger);
    background: #fff5f5;
}

.msd-news-url.error {
    border-color: var(--msd-danger);
}
</style>
