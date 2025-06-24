<?php
if (!defined('ABSPATH')) {
    exit;
}

$contact_info = get_site_option('msd_contact_info', [
    'name' => get_network_option(null, 'site_name'),
    'email' => get_network_option(null, 'admin_email'),
    'phone' => '',
    'website' => network_home_url(),
    'description' => 'Network Administrator Contact Information',
    'qq' => '',
    'wechat' => '',
    'whatsapp' => '',
    'telegram' => '',
    'qr_code' => ''
]);

$news_sources = get_site_option('msd_news_sources', [
    [
        'name' => 'WordPress News',
        'url' => 'https://wordpress.org/news/feed/',
        'enabled' => true
    ]
]);

$quick_links = get_site_option('msd_quick_links', []);
?>

<div id="msd-contact-info-modal" class="msd-modal" style="display: none;">
    <div class="msd-modal-content">
        <div class="msd-modal-header">
            <h3><?php _e('Edit Contact Information', 'wp-multisite-dashboard'); ?></h3>
            <button type="button" class="msd-modal-close" onclick="MSD.hideContactInfoModal()">&times;</button>
        </div>

        <div class="msd-modal-body">
            <div class="msd-contact-form">
                <div class="msd-form-section">
                    <div class="msd-form-field">
                        <label><?php _e('Organization Name:', 'wp-multisite-dashboard'); ?></label>
                        <input type="text" id="msd-contact-name" value="<?php echo esc_attr($contact_info['name']); ?>" placeholder="<?php esc_attr_e('Network Administrator', 'wp-multisite-dashboard'); ?>">
                    </div>

                    <div class="msd-form-field">
                        <label><?php _e('Email:', 'wp-multisite-dashboard'); ?></label>
                        <input type="email" id="msd-contact-email" value="<?php echo esc_attr($contact_info['email']); ?>" placeholder="admin@example.com">
                    </div>

                    <div class="msd-form-field">
                        <label><?php _e('Phone:', 'wp-multisite-dashboard'); ?></label>
                        <input type="text" id="msd-contact-phone" value="<?php echo esc_attr($contact_info['phone']); ?>" placeholder="+1 234 567 8900">
                    </div>

                    <div class="msd-form-field">
                        <label><?php _e('Website:', 'wp-multisite-dashboard'); ?></label>
                        <input type="url" id="msd-contact-website" value="<?php echo esc_attr($contact_info['website']); ?>" placeholder="https://example.com">
                    </div>

                    <div class="msd-form-field">
                        <label><?php _e('Description:', 'wp-multisite-dashboard'); ?></label>
                        <textarea id="msd-contact-description" placeholder="<?php esc_attr_e('Brief description or role', 'wp-multisite-dashboard'); ?>"><?php echo esc_textarea($contact_info['description']); ?></textarea>
                    </div>
                </div>

                <div class="msd-form-section">
                    <div class="msd-form-grid">
                        <div class="msd-form-field">
                            <label>
                                <span class="dashicons dashicons-admin-users"></span>
                                <?php _e('QQ:', 'wp-multisite-dashboard'); ?>
                            </label>
                            <input type="text" id="msd-contact-qq" value="<?php echo esc_attr($contact_info['qq']); ?>" placeholder="1234567890">
                        </div>

                        <div class="msd-form-field">
                            <label>
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php _e('WeChat:', 'wp-multisite-dashboard'); ?>
                            </label>
                            <input type="text" id="msd-contact-wechat" value="<?php echo esc_attr($contact_info['wechat']); ?>" placeholder="WeChat_ID">
                        </div>
                    </div>

                    <div class="msd-form-grid">
                        <div class="msd-form-field">
                            <label>
                                <span class="dashicons dashicons-smartphone"></span>
                                <?php _e('WhatsApp:', 'wp-multisite-dashboard'); ?>
                            </label>
                            <input type="text" id="msd-contact-whatsapp" value="<?php echo esc_attr($contact_info['whatsapp']); ?>" placeholder="+1234567890">
                        </div>

                        <div class="msd-form-field">
                            <label>
                                <span class="dashicons dashicons-email-alt"></span>
                                <?php _e('Telegram:', 'wp-multisite-dashboard'); ?>
                            </label>
                            <input type="text" id="msd-contact-telegram" value="<?php echo esc_attr($contact_info['telegram']); ?>" placeholder="@username">
                        </div>
                    </div>
                </div>

                <div class="msd-form-section">
                    <div class="msd-form-field">
                        <label><?php _e('QR Code Image URL:', 'wp-multisite-dashboard'); ?></label>
                        <div class="msd-qr-input-group">
                            <input type="url" id="msd-contact-qr-code" value="<?php echo esc_attr($contact_info['qr_code']); ?>" placeholder="https://example.com/qr-code.png">
                            <button type="button" class="button" onclick="MSD.selectQRImage()"><?php _e('Select Image', 'wp-multisite-dashboard'); ?></button>
                        </div>
                        <p class="description"><?php _e('Upload or provide URL for a QR code image (WeChat, contact info, etc.)', 'wp-multisite-dashboard'); ?></p>

                        <div id="msd-qr-preview" class="msd-qr-preview" style="<?php echo empty($contact_info['qr_code']) ? 'display: none;' : ''; ?>">
                            <img src="<?php echo esc_url($contact_info['qr_code']); ?>" alt="QR Code Preview" class="msd-qr-preview-img">
                            <button type="button" class="msd-qr-remove" onclick="MSD.removeQRCode()">×</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="msd-modal-footer">
            <button type="button" class="button button-primary" onclick="MSD.saveContactInfo()">
                <?php _e('Save Contact Info', 'wp-multisite-dashboard'); ?>
            </button>
            <button type="button" class="button" onclick="MSD.hideContactInfoModal()">
                <?php _e('Cancel', 'wp-multisite-dashboard'); ?>
            </button>
        </div>
    </div>
</div>

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

            <div class="msd-news-help">
                <h4><?php _e('Popular RSS Feeds', 'wp-multisite-dashboard'); ?></h4>
                <div class="msd-rss-suggestions">
                    <div class="msd-rss-suggestion">
                        <strong>WenPai.org News</strong>
                        <code>https://wenpai.org/news/feed/</code>
                        <span class="msd-rss-desc">Official WenPai.org news and updates</span>
                    </div>
                    <div class="msd-rss-suggestion">
                        <strong>WP TEA</strong>
                        <code>https://wptea.com/feed</code>
                        <span class="msd-rss-desc">WordPress China community news and insights</span>
                    </div>
                </div>
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

<div id="msd-quick-links-modal" class="msd-modal" style="display: none;">
    <div class="msd-modal-content">
        <div class="msd-modal-header">
            <h3><?php _e('Configure Quick Links', 'wp-multisite-dashboard'); ?></h3>
            <button type="button" class="msd-modal-close" onclick="MSD.hideQuickLinksModal()">&times;</button>
        </div>

        <div class="msd-modal-body">
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
                                <span><?php _e('Home', 'wp-multisite-dashboard'); ?></span>
                            </div>
                            <div class="msd-icon-example">
                                <span class="dashicons dashicons-chart-bar"></span>
                                <code>dashicons-chart-bar</code>
                                <span><?php _e('Analytics', 'wp-multisite-dashboard'); ?></span>
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
                        </div>
                    </div>
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
    margin-bottom: 24px;
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

.msd-contact-form {
    display: grid;
    gap: 24px;
}

.msd-form-section {
    padding: 16px;
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    background: var(--msd-bg-light);
}

.msd-form-section h4 {
    margin: 0 0 16px 0;
    color: var(--msd-text);
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
    border-bottom: 1px solid var(--msd-border);
    padding-bottom: 8px;
}

.msd-form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.msd-form-field {
    margin-bottom: 16px;
}

.msd-form-field:last-child {
    margin-bottom: 0;
}

.msd-form-field label {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-weight: 500;
    color: var(--msd-text);
    font-size: 13px;
}

.msd-form-field label .dashicons {
    font-size: 16px;
    line-height: 1.5;
    color: var(--msd-primary);
}

.msd-form-field input,
.msd-form-field textarea {
    width: 100%;
    padding: 4px 8px;
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius-small);
    font-size: 14px;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

.msd-form-field input:focus,
.msd-form-field textarea:focus {
    outline: none;
    border-color: var(--msd-primary);
    box-shadow: 0 0 0 2px rgba(34, 113, 177, 0.1);
}

.msd-form-field textarea {
    min-height: 80px;
    resize: vertical;
}

.msd-qr-input-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.msd-qr-input-group input {
    flex: 1;
}

.msd-qr-input-group .button {
    flex-shrink: 0;
    padding: 4px 16px;
    font-size: 13px;
}

.msd-qr-preview {
    margin-top: 12px;
    position: relative;
    display: inline-block;
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
    padding: 8px;
    background: white;
}

.msd-qr-preview-img {
    max-width: 120px;
    max-height: 120px;
    display: block;
}

.msd-qr-remove {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--msd-danger);
    color: white;
    border: none;
    cursor: pointer;
    font-size: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s ease;
}

.msd-qr-remove:hover {
    background: #c82333;
}

.msd-form-field .description {
    margin: 6px 0 0 0;
    font-size: 12px;
    color: var(--msd-text-light);
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

.msd-link-item {
    margin-bottom: var(--msd-spacing);
    padding: var(--msd-spacing);
    background: var(--msd-bg);
    border: 1px solid var(--msd-border);
    border-radius: var(--msd-radius);
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

.msd-remove-source,
.msd-remove-link {
    font-size: 12px;
    padding: 4px 8px;
    color: var(--msd-danger);
    border: 1px solid var(--msd-danger);
    background: none;
    border-radius: var(--msd-radius-small);
    cursor: pointer;
    transition: all 0.2s ease;
}

.msd-remove-source:hover,
.msd-remove-link:hover {
    background: var(--msd-danger);
    color: white;
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

    .msd-link-row {
        grid-template-columns: 1fr;
    }

    .msd-link-options {
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .msd-form-grid {
        grid-template-columns: 1fr;
    }

    .msd-icon-examples {
        grid-template-columns: 1fr;
    }

    .msd-modal-content {
        margin: 20px;
        width: auto;
    }

    .msd-rss-suggestion {
        grid-template-columns: 1fr;
    }
}

.msd-news-source-item.error {
    border-color: var(--msd-danger);
    background: #fff5f5;
}

.msd-news-url.error {
    border-color: var(--msd-danger);
}

.msd-link-item.error {
    border-color: var(--msd-danger);
    background: #fff5f5;
}

.msd-link-url.error {
    border-color: var(--msd-danger);
}
</style>
