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

.msd-contact-qr {
    text-align: center;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--msd-bg);
}

.msd-qr-image {
    max-width: 100%;
    max-height: 200px;
    border: 1px solid var(--msd-border);
    padding: 12px;
    background: var(--msd-bg);
    border-radius: var(--msd-radius-small);
    transition: all 0.2s ease;
}

@media screen and (max-width: 600px) {
    .msd-form-grid {
        grid-template-columns: 1fr;
    }

    .msd-qr-input-group {
        flex-direction: column;
        align-items: stretch;
    }

    .msd-modal-content {
        margin: 20px;
        width: auto;
    }
}
</style>
