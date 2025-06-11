<?php
if (!defined('ABSPATH')) {
    exit;
}

$quick_links = get_site_option('msd_quick_links', []);
?>

<div id="msd-quick-links-modal" class="msd-modal" style="display: none;">
    <div class="msd-modal-content">
        <div class="msd-modal-header">
            <h3><?php _e('Configure Quick Links', 'wp-multisite-dashboard'); ?></h3>
            <button type="button" class="msd-modal-close" onclick="MSD.hideQuickLinksModal()">&times;</button>
        </div>

        <div class="msd-modal-body">
            <div class="msd-modal-intro">
                <p><?php _e('Add custom links to frequently used pages or external tools. These will appear as clickable tiles in your Quick Links widget. You can use WordPress Dashicons or emojis for icons. Links can be reordered by dragging and dropping.', 'wp-multisite-dashboard'); ?></p>
            </div>

            <div id="msd-quick-links-editor">
                <?php if (!empty($quick_links)): ?>
                    <?php foreach ($quick_links as $index => $link): ?>
                        <div class="msd-link-item">
                            <div class="msd-link-row">
                                <input type="text"
                                       placeholder="<?php esc_attr_e('Link Title', 'wp-multisite-dashboard'); ?>"
                                       value="<?php echo esc_attr($link['title']); ?>"
                                       class="msd-link-title"
                                       required>
                                <input type="url"
                                       placeholder="https://example.com"
                                       value="<?php echo esc_url($link['url']); ?>"
                                       class="msd-link-url"
                                       required>
                            </div>

                            <div class="msd-link-options">
                                <input type="text"
                                       placeholder="<?php esc_attr_e('dashicons-admin-home or 🏠', 'wp-multisite-dashboard'); ?>"
                                       value="<?php echo esc_attr($link['icon']); ?>"
                                       class="msd-link-icon"
                                       title="<?php esc_attr_e('Icon (Dashicon class or emoji)', 'wp-multisite-dashboard'); ?>">

                                <label class="msd-checkbox-label">
                                    <input type="checkbox"
                                           class="msd-link-newtab"
                                           <?php checked(!empty($link['new_tab'])); ?>>
                                    <?php _e('Open in new tab', 'wp-multisite-dashboard'); ?>
                                </label>

                                <button type="button" class="msd-remove-link">
                                    <?php _e('Remove', 'wp-multisite-dashboard'); ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="msd-add-link-section">
                <button type="button" id="msd-add-link" class="button button-secondary">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php _e('Add Link', 'wp-multisite-dashboard'); ?>
                </button>
            </div>

            <div class="msd-quick-links-help">
                <h4><?php _e('Icon Options', 'wp-multisite-dashboard'); ?></h4>

                <div class="msd-icon-types">
                    <div class="msd-icon-type-section">
                        <h5><?php _e('WordPress Dashicons', 'wp-multisite-dashboard'); ?></h5>
                        <div class="msd-icon-examples">
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-admin-home"></span>
                                <code>dashicons-admin-home</code>
                                <span><?php _e('Home/Dashboard', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-admin-settings"></span>
                                <code>dashicons-admin-settings</code>
                                <span><?php _e('Settings', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-admin-users"></span>
                                <code>dashicons-admin-users</code>
                                <span><?php _e('Users', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <code>dashicons-chart-bar</code>
                                <span><?php _e('Analytics', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-email"></span>
                                <code>dashicons-email</code>
                                <span><?php _e('Email', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-external"></span>
                                <code>dashicons-external</code>
                                <span><?php _e('External Link', 'wp-multisite-dashboard'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="msd-icon-type-section">
                        <h5><?php _e('Emojis', 'wp-multisite-dashboard'); ?></h5>
                        <div class="msd-icon-examples">
                            <div class="msd-icon-example">
                                <span class="msd-emoji">🏠</span>
                                <code>🏠</code>
                                <span><?php _e('Home', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="msd-emoji">⚙️</span>
                                <code>⚙️</code>
                                <span><?php _e('Settings', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="msd-emoji">👥</span>
                                <code>👥</code>
                                <span><?php _e('Users', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="msd-emoji">📊</span>
                                <code>📊</code>
                                <span><?php _e('Analytics', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="msd-emoji">📧</span>
                                <code>📧</code>
                                <span><?php _e('Email', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="msd-emoji">🔗</span>
                                <code>🔗</code>
                                <span><?php _e('Link', 'wp-multisite-dashboard'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="msd-reorder-tip">
                    <h5><?php _e('Drag & Drop Reordering', 'wp-multisite-dashboard'); ?></h5>
                    <p class="description"><?php _e('After saving your links, you can reorder them by dragging and dropping the tiles in the Quick Links widget.', 'wp-multisite-dashboard'); ?></p>
                </div>

                <p class="description">
                    <strong><?php _e('Dashicons:', 'wp-multisite-dashboard'); ?></strong> <?php _e('Built into WordPress, always available. Use format:', 'wp-multisite-dashboard'); ?> <code>dashicons-icon-name</code><br>
                    <strong><?php _e('Emojis:', 'wp-multisite-dashboard'); ?></strong> <?php _e('Copy and paste emoji directly. Works on all devices.', 'wp-multisite-dashboard'); ?>
                </p>

                <p class="description">
                    <?php printf(__('Find more Dashicons at %s', 'wp-multisite-dashboard'), '<a href="https://developer.wordpress.org/resource/dashicons/" target="_blank">developer.wordpress.org/resource/dashicons/</a>'); ?>
                </p>
            </div>
        </div>

        <div class="msd-modal-footer">
            <button type="button" class="button button-primary" onclick="MSD.saveQuickLinks()">
                <?php _e('Save Links', 'wp-multisite-dashboard'); ?>
            </button>
            <button type="button" class="button" onclick="MSD.hideQuickLinksModal()">
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
}

.msd-link-item {
    margin-bottom: 16px;
    padding: 16px;
    background: var(--msd-bg);
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    transition: border-color 0.2s ease;
}

.msd-link-item:hover {
    border-color: var(--msd-primary);
}

.msd-link-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 12px;
}

.msd-link-options {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 12px;
    align-items: center;
}

.msd-link-title,
.msd-link-url,
.msd-link-icon {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius-small);
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.msd-link-title:focus,
.msd-link-url:focus,
.msd-link-icon:focus {
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

.msd-add-link-section {
    text-align: center;
    margin: 24px 0;
    padding: 16px;
    border: 2px dashed var(--msd-border);
    border-radius: var(--msd-radius);
    transition: border-color 0.2s ease;
}

.msd-add-link-section:hover {
    border-color: var(--msd-primary);
}

#msd-add-link {
    display: flex;
    align-items: center;
    gap: 6px;
}

.msd-quick-links-help {
    margin-top: 24px;
    padding: 16px;
    background: #f8f9fa;
    border-radius: var(--msd-radius);
    border: 1px solid #e9ecef;
}

.msd-quick-links-help h4 {
    margin: 0 0 16px 0;
    color: var(--msd-text);
    font-size: 16px;
    font-weight: 600;
}

.msd-icon-types {
    display: grid;
    gap: 20px;
}

.msd-icon-type-section h5 {
    margin: 0 0 12px 0;
    color: var(--msd-text);
    font-size: 14px;
    font-weight: 600;
    padding-bottom: 6px;
    border-bottom: 1px solid #e9ecef;
}

.msd-icon-examples {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 8px;
    margin-bottom: 12px;
}

.msd-icon-example {
    display: grid;
    grid-template-columns: 24px 1fr auto;
    gap: 8px;
    align-items: center;
    padding: 8px;
    background: white;
    border-radius: 3px;
    font-size: 12px;
    border: 1px solid #e9ecef;
}

.msd-icon-example .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
    color: var(--msd-primary);
}

.msd-icon-example .msd-emoji {
    font-size: 16px;
    text-align: center;
}

.msd-icon-example code {
    background: #e9ecef;
    padding: 2px 4px;
    border-radius: 2px;
    font-family: 'Courier New', monospace;
    font-size: 10px;
    font-weight: 500;
}

.msd-icon-example span:last-child {
    color: var(--msd-text-light);
    font-size: 11px;
}

.msd-reorder-tip {
    margin-top: 20px;
    padding: 12px;
    background: #e7f3ff;
    border-radius: 4px;
    border-left: 4px solid #2196f3;
}

.msd-reorder-tip h5 {
    margin: 0 0 8px 0;
    color: var(--msd-text);
    font-size: 14px;
    font-weight: 600;
}

.msd-quick-links-help .description {
    margin: 12px 0 0 0;
    font-size: 12px;
    color: var(--msd-text-light);
    line-height: 1.4;
}

.msd-quick-links-help .description:last-child {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid #e9ecef;
}

.msd-quick-links-help a {
    color: var(--msd-primary);
    text-decoration: none;
}

.msd-quick-links-help a:hover {
    text-decoration: underline;
}

@media screen and (max-width: 600px) {
    .msd-link-row {
        grid-template-columns: 1fr;
    }

    .msd-link-options {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .msd-icon-examples {
        grid-template-columns: 1fr;
    }

    .msd-modal-content {
        margin: 20px;
        width: auto;
    }
}

.msd-link-item.error {
    border-color: var(--msd-danger);
    background: #fff5f5;
}

.msd-link-url.error {
    border-color: var(--msd-danger);
}
</style>
