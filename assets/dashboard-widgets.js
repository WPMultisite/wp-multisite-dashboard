// Global MSD functions for Version Info widget (must be outside jQuery ready)
window.MSD = window.MSD || {};

window.MSD.clearCache = function (type) {
  if (
    !confirm(
      msdAjax.strings.clear_cache_confirm ||
        "Are you sure you want to clear the cache?",
    )
  ) {
    return;
  }

  if (window.MSD_Core) {
    window.MSD_Core.makeAjaxRequest(
      "msd_clear_cache",
      { type: type || "all" },
      function () {
        window.MSD_Core.showNotice(
          msdAjax.strings.cache_cleared || "Cache cleared successfully!",
          "success",
          3000,
        );
      },
      function () {
        window.MSD_Core.showNotice(
          msdAjax.strings.cache_clear_failed || "Failed to clear cache",
          "error",
        );
      },
    );
  }
};

window.MSD.checkForUpdates = function () {
  if (!window.MSD_Core) {
    alert(msdAjax.strings.error_occurred || "Error occurred");
    return;
  }

  window.MSD_Core.makeAjaxRequest(
    "msd_check_plugin_update",
    {},
    function (response) {
      if (response.data && response.data.update_available) {
        window.MSD_Core.showNotice(
          (
            msdAjax.strings.update_available || "Version {version} available!"
          ).replace("{version}", response.data.version),
          "info",
          5000,
        );
      } else {
        window.MSD_Core.showNotice(
          msdAjax.strings.up_to_date || "Up to date",
          "success",
          3000,
        );
      }
    },
    function () {
      window.MSD_Core.showNotice(
        msdAjax.strings.update_check_failed || "Failed to check for updates",
        "error",
      );
    },
  );
};

window.MSD.exportDiagnostics = function () {
  if (!window.MSD_Core) {
    alert(msdAjax.strings.error_occurred || "Error occurred");
    return;
  }

  window.MSD_Core.makeAjaxRequest(
    "msd_export_diagnostics",
    {},
    function (response) {
      if (response.data) {
        // Create a blob and download
        const dataStr = JSON.stringify(response.data, null, 2);
        const dataBlob = new Blob([dataStr], { type: "application/json" });
        const url = URL.createObjectURL(dataBlob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "msd-diagnostics-" + new Date().getTime() + ".json";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

        window.MSD_Core.showNotice(
          msdAjax.strings.export_success || "Diagnostics exported successfully",
          "success",
          3000,
        );
      }
    },
    function () {
      window.MSD_Core.showNotice(
        msdAjax.strings.export_failed || "Failed to export diagnostics",
        "error",
      );
    },
  );
};

jQuery(document).ready(function ($) {
  "use strict";

  // Simple debounce implementation
  $.debounce = function(delay, fn) {
    let timer = null;
    return function() {
      const context = this;
      const args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function() {
        fn.apply(context, args);
      }, delay);
    };
  };

  const MSD_Widgets = {
    init() {
      this.loadAllWidgets();
      this.bindEvents();
    },

    bindEvents() {
      $(document)
        .on("click", ".msd-user-action-btn", this.handleUserAction.bind(this))
        .on("click", ".msd-todo-add-btn", this.showTodoForm.bind(this))
        .on("click", ".msd-todo-checkbox", this.toggleTodoComplete.bind(this))
        .on("click", ".msd-todo-edit", this.editTodo.bind(this))
        .on("click", ".msd-todo-delete", this.deleteTodo.bind(this))
        .on("click", ".msd-todo-save", this.saveTodo.bind(this))
        .on("click", ".msd-todo-cancel", this.cancelTodoForm.bind(this))
        // Error log monitoring
        .on(
          "change keyup",
          ".msd-log-type, .msd-log-search, .msd-log-limit",
          () => this.loadWidget("error_logs"),
        )
        .on("click", ".msd-clear-error-log", this.clearErrorLogs.bind(this))
        .on("click", ".msd-log-toggle", this.toggleLogEntry.bind(this))
        // 404 monitoring
        .on("change", ".msd-404-limit, .msd-404-days", () =>
          this.loadWidget("monitor_404"),
        )
        .on("click", ".msd-toggle-404", this.toggle404Monitoring.bind(this))
        .on("click", ".msd-clear-404", this.clear404Logs.bind(this));
    },

    loadAllWidgets() {
      if (window.MSD_Core && window.MSD_Core.isRefreshing) return;

      if (window.MSD_Core) {
        window.MSD_Core.isRefreshing = true;
      }

      const widgets = [
        "network_overview",
        "site_list",
        "storage_data",
        "version_info",
        "custom_news",
        "network_settings",
        "user_management",
        "last_edits",
        "todo_items",
        "error_logs",
        "monitor_404",
        "domain_mapping",
      ];

      widgets.forEach((widget) => {
        this.loadWidget(widget);
      });

      setTimeout(() => {
        if (window.MSD_Core) {
          window.MSD_Core.isRefreshing = false;
        }
      }, 1000);
    },

    loadWidget(widgetType) {
      const $container = $(`[data-widget="${widgetType}"]`);
      if ($container.length === 0) return;

      const actionMap = {
        network_overview: "msd_get_network_overview",
        site_list: "msd_get_site_list",
        storage_data: "msd_get_storage_data",
        version_info: "msd_get_version_info",
        custom_news: "msd_get_custom_news",
        network_settings: "msd_get_network_settings",
        user_management: "msd_get_user_management",
        last_edits: "msd_get_last_edits",
        todo_items: "msd_get_todo_items",
        error_logs: "msd_get_error_logs",
        monitor_404: "msd_get_404_stats",
        domain_mapping: "msd_get_domain_mapping_data",
      };

      const action = actionMap[widgetType];
      if (!action) return;

      // Build payload from UI controls if available
      let payload = {};
      if (widgetType === "error_logs") {
        const limit = parseInt(
          $container.find(".msd-log-limit").val() || 100,
          10,
        );
        const type = $container.find(".msd-log-type").val() || "all";
        const search = ($container.find(".msd-log-search").val() || "").trim();
        payload = {
          limit,
          filters: { type, search },
        };
      } else if (widgetType === "monitor_404") {
        const limit = parseInt(
          $container.find(".msd-404-limit").val() || 20,
          10,
        );
        const days = parseInt($container.find(".msd-404-days").val() || 30, 10);
        payload = { limit, days };
      } else if (widgetType === "storage_data") {
        const limit = parseInt(
          $container.find(".msd-storage-limit").val() || 10,
          10,
        );
        const search = ($container.find(".msd-storage-search").val() || "").trim();
        const sort_by = $container.find(".msd-storage-sort").val() || "storage";
        payload = { limit, search, sort_by };
      }

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          action,
          payload,
          (response) => {
            this.renderWidget(widgetType, $container, response.data);
            if (window.MSD_Core) {
              window.MSD_Core.retryCount = 0;
            }
          },
          (error) => {
            this.handleWidgetError($container, error, widgetType);
          },
        );
      }
    },

    renderWidget(widgetType, $container, data) {
      const renderers = {
        network_overview: this.renderNetworkOverview,
        site_list: this.renderQuickSites,
        storage_data: this.renderStorageData,
        version_info: this.renderVersionInfo,
        custom_news: this.renderCustomNews,
        network_settings: this.renderNetworkSettings,
        user_management: this.renderUserManagement,
        last_edits: this.renderLastEdits,
        todo_items: this.renderTodoItems,
        error_logs: this.renderErrorLogs,
        monitor_404: this.render404Stats,
        domain_mapping: this.renderDomainMapping,
      };

      const renderer = renderers[widgetType];
      if (renderer) {
        renderer.call(this, $container, data);
        $container.addClass("fade-in");
      }
    },

    renderNetworkOverview($container, data) {
      const html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="network_overview">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>

                <div class="msd-overview-simple">
                    <div class="msd-overview-row">
                        <div class="msd-overview-item">
                            <span class="msd-overview-number">${this.formatNumber(data.total_posts || 0)}</span>
                            <span class="msd-overview-label">${msdAjax.strings.posts}</span>
                        </div>
                        <div class="msd-overview-item">
                            <span class="msd-overview-number">${this.formatNumber(data.total_pages || 0)}</span>
                            <span class="msd-overview-label">${msdAjax.strings.pages}</span>
                        </div>
                    </div>

                    <div class="msd-overview-config">
                        <div class="msd-config-header">
                            <span class="dashicons dashicons-palmtree"></span>
                            ${msdAjax.strings.multisite_configuration}
                        </div>
                        <div class="msd-config-list">
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.installation_type}:</span>
                                <span class="msd-config-value">${this.escapeHtml(data.multisite_config?.installation_type_label || msdAjax.strings.unknown)}</span>
                            </div>
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.network_admin_email}:</span>
                                <span class="msd-config-value">${this.escapeHtml(data.network_info?.network_admin_email || msdAjax.strings.not_set)}</span>
                            </div>
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.site_upload_quota}:</span>
                                <span class="msd-config-value">${data.network_info?.blog_upload_space_formatted || msdAjax.strings.zero_mb}</span>
                            </div>
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.max_upload_size}:</span>
                                <span class="msd-config-value">${data.network_info?.fileupload_maxk_formatted || msdAjax.strings.unknown}</span>
                            </div>
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.default_language}:</span>
                                <span class="msd-config-value">${this.escapeHtml(data.network_info?.default_language || "en_US")}</span>
                            </div>
                            <div class="msd-config-item">
                                <span class="msd-config-key">${msdAjax.strings.registration}:</span>
                                <span class="msd-config-value">${this.escapeHtml(data.network_info?.registration_label || msdAjax.strings.unknown)}</span>
                            </div>
                        </div>
                    </div>
                </div>
            `;

      $container.html(html);

      setTimeout(() => {
        if (window.MSD_Modals && window.MSD_Modals.initSortable) {
          window.MSD_Modals.initSortable();
        }
      }, 100);
    },

    renderQuickSites($container, sites) {
      let html = `<button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="site_list">${msdAjax.strings.refresh_symbol || "↻"}</button>`;

      if (!sites || sites.length === 0) {
        html += `<div class="msd-empty-state"><p>${msdAjax.strings.no_active_sites}</p></div>`;
      } else {
        html += '<div class="msd-sites-grid">';
        sites.forEach((site) => {
          html += `
                        <div class="msd-site-card">
                            <div class="msd-site-info">
                                <img src="${this.escapeHtml(site.favicon || "")}"
                                     alt="${this.escapeHtml(site.name)}"
                                     class="msd-site-favicon"
                                     onerror="this.src='${this.getDefaultFavicon()}'">
                                <div class="msd-site-details">
                                    <div class="msd-site-name" title="${this.escapeHtml(site.domain)}">${this.escapeHtml(site.name)}</div>
                                    <div class="msd-site-meta">
                                        <span>${site.users || 0} ${msdAjax.strings.users}</span>
                                        <span>${site.last_activity_human || msdAjax.strings.no_activity}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="msd-site-actions">
                                <a href="${site.admin_url}" class="msd-btn-small msd-btn-primary" title="${msdAjax.strings.admin}">${msdAjax.strings.admin}</a>
                                <a href="${site.view_url}" class="msd-btn-small msd-btn-secondary" title="${msdAjax.strings.view}" target="_blank">${msdAjax.strings.view}</a>
                            </div>
                        </div>
                    `;
        });
        html += "</div>";
      }

      $container.html(html);
    },

    renderStorageData($container, data) {
      let html = `<button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="storage_data">${msdAjax.strings.refresh_symbol || "↻"}</button>`;

      if (!data || !data.sites || data.sites.length === 0) {
        html += `<div class="msd-empty-state"><p>${msdAjax.strings.no_storage_data}</p></div>`;
      } else {
        // Controls
        const currentLimit = parseInt($container.find(".msd-storage-limit").val() || 10);
        const currentSort = $container.find(".msd-storage-sort").val() || 'storage';
        const currentSearch = $container.find(".msd-storage-search").val() || '';
        
        html += `<div class="msd-storage-controls">
          <div class="msd-storage-control-group">
            <label>${msdAjax.strings.show || 'Show'}:</label>
            <select class="msd-storage-limit">
              <option value="5" ${currentLimit === 5 ? 'selected' : ''}>5</option>
              <option value="10" ${currentLimit === 10 ? 'selected' : ''}>10</option>
              <option value="20" ${currentLimit === 20 ? 'selected' : ''}>20</option>
              <option value="50" ${currentLimit === 50 ? 'selected' : ''}>50</option>
              <option value="0" ${currentLimit === 0 ? 'selected' : ''}>${msdAjax.strings.all_sites || 'All'}</option>
            </select>
          </div>
          <div class="msd-storage-control-group">
            <label>${msdAjax.strings.sort_by || 'Sort'}:</label>
            <select class="msd-storage-sort">
              <option value="storage" ${currentSort === 'storage' ? 'selected' : ''}>${msdAjax.strings.total_storage || 'Total'}</option>
              <option value="files" ${currentSort === 'files' ? 'selected' : ''}>${msdAjax.strings.files || 'Files'}</option>
              <option value="database" ${currentSort === 'database' ? 'selected' : ''}>${msdAjax.strings.database || 'DB'}</option>
              <option value="name" ${currentSort === 'name' ? 'selected' : ''}>${msdAjax.strings.site_name || 'Name'}</option>
            </select>
          </div>
          <div class="msd-storage-control-group">
            <input type="text" class="msd-storage-search" placeholder="${msdAjax.strings.search_sites || 'Search...'}" value="${this.escapeHtml(currentSearch)}">
          </div>
        </div>`;

        // Summary
        html += `<div class="msd-storage-summary">
          <div class="msd-storage-summary-grid">
            <div class="msd-storage-summary-item">
              <span class="msd-storage-summary-label">${msdAjax.strings.total_storage || 'Total'}</span>
              <span class="msd-storage-summary-value">${data.total_formatted || '0 B'}</span>
            </div>
            <div class="msd-storage-summary-item">
              <span class="msd-storage-summary-label">${msdAjax.strings.files || 'Files'}</span>
              <span class="msd-storage-summary-value">${data.total_files_formatted || '0 B'}</span>
            </div>
            <div class="msd-storage-summary-item">
              <span class="msd-storage-summary-label">${msdAjax.strings.database || 'Database'}</span>
              <span class="msd-storage-summary-value">${data.total_db_formatted || '0 B'}</span>
            </div>
            <div class="msd-storage-summary-item">
              <span class="msd-storage-summary-label">${msdAjax.strings.sites_analyzed || 'Sites'}</span>
              <span class="msd-storage-summary-value">${data.summary ? data.summary.sites_analyzed : 0}</span>
            </div>
          </div>
        </div>`;

        // Site list
        html += '<div class="msd-storage-list">';
        data.sites.forEach((site) => {
          const fillWidth = Math.min(site.usage_percentage || 0, 100);
          const fillClass = this.getStorageStatusClass(site.status);
          
          // Truncate site name if too long
          const siteName = site.name.length > 50 ? site.name.substring(0, 47) + '...' : site.name;
          
          // Build media library URL
          const mediaUrl = site.admin_url ? site.admin_url.replace(/\/wp-admin\/?$/, '') + '/wp-admin/upload.php' : '#';

          html += `<div class="msd-storage-item">
            <div class="msd-storage-item-header">
              <a href="${mediaUrl}" class="msd-storage-site" title="${this.escapeHtml(site.name)}">${this.escapeHtml(siteName)}</a>
              <div class="msd-storage-total-info">
                <span class="msd-storage-amount">${site.storage_formatted || '0 B'}</span>
                <span class="msd-storage-percentage">${site.usage_percentage || 0}%</span>
              </div>
            </div>
            <div class="msd-storage-meta">
              <div class="msd-storage-breakdown">
                <span class="msd-storage-breakdown-item">
                  <span class="dashicons dashicons-media-default"></span>
                  ${site.files_formatted || '0 B'}
                </span>
                <span class="msd-storage-breakdown-item">
                  <span class="dashicons dashicons-database"></span>
                  ${site.db_formatted || '0 B'}
                </span>
              </div>`;
          
          // File types - only show images and videos
          if (site.file_types && (site.file_types.images > 0 || site.file_types.videos > 0)) {
            html += '<div class="msd-storage-types">';
            if (site.file_types.images > 0) {
              html += `<span class="msd-storage-type-item">
                <span class="dashicons dashicons-format-image"></span>
                ${this.formatBytes(site.file_types.images)}
              </span>`;
            }
            if (site.file_types.videos > 0) {
              html += `<span class="msd-storage-type-item">
                <span class="dashicons dashicons-format-video"></span>
                ${this.formatBytes(site.file_types.videos)}
              </span>`;
            }
            html += '</div>';
          }
          
          html += `</div>
            <div class="msd-storage-bar"><div class="msd-storage-fill ${fillClass}" style="width: ${fillWidth}%"></div></div>
          </div>`;
        });
        html += "</div>";
      }

      $container.html(html);
      
      // Bind events
      $container.find('.msd-storage-limit, .msd-storage-sort').on('change', () => {
        this.loadWidget('storage_data');
      });
      
      $container.find('.msd-storage-search').on('keyup', $.debounce(500, () => {
        this.loadWidget('storage_data');
      }));
    },

    renderVersionInfo($container, data) {
      const pluginIconUrl = msdAjax.pluginUrl + "assets/images/icon.svg";

      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="version_info">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>

                <div class="msd-version-header">
                    <div class="msd-version-title-wrapper">
                        <img src="${pluginIconUrl}" alt="Plugin Icon" class="msd-plugin-icon" />
                        <div class="msd-version-title-info">
                            <h3>${this.escapeHtml(data.plugin_name || "WP Multisite Dashboard")}</h3>
                            ${data.last_modified ? `<span class="msd-last-modified">${msdAjax.strings.last_updated || "Last Updated"}: ${this.escapeHtml(data.last_modified)}</span>` : ""}
                        </div>
                    </div>
                    <div class="msd-version-actions">
                        <a href="https://wpmultisite.com/document/wp-multisite-dashboard" target="_blank" class="msd-help-btn msd-help-docs" title="${msdAjax.strings.documentation}">
                            <span class="dashicons dashicons-book"></span>
                        </a>
                        <a href="https://wpmultisite.com/support/" target="_blank" class="msd-help-btn msd-help-docs" title="${msdAjax.strings.support}">
                            <span class="dashicons dashicons-admin-comments"></span>
                        </a>
                        <a href="https://github.com/wpmultisite/wp-multisite-dashboard" target="_blank" class="msd-help-btn msd-help-docs" title="${msdAjax.strings.github}">
                            <span class="dashicons dashicons-admin-links"></span>
                        </a>
                    </div>
                </div>

                ${
                  data.update_available && data.update_info
                    ? `
                    <div class="msd-update-notice">
                        <span class="dashicons dashicons-update"></span>
                        <span>${msdAjax.strings.update_available.replace("{version}", this.escapeHtml(data.update_info.version))}</span>
                        ${data.update_info.details_url ? `<a href="${this.escapeHtml(data.update_info.details_url)}" target="_blank" class="msd-update-link">${msdAjax.strings.view_details}</a>` : ""}
                    </div>
                `
                    : ""
                }

                <div class="msd-version-specs">
                    <div class="msd-version-item">
                        <span class="msd-version-icon dashicons dashicons-tag"></span>
                        <span class="msd-version-label">${msdAjax.strings.plugin_version}</span>
                        <span class="msd-version-value">${this.escapeHtml(data.plugin_version || "")}</span>
                    </div>
                    <div class="msd-version-item">
                        <span class="msd-version-icon dashicons dashicons-admin-links"></span>
                        <span class="msd-version-label">${msdAjax.strings.author_uri}</span>
                        <span class="msd-version-value">
                            <a href="${this.escapeHtml(data.plugin_uri || "")}" target="_blank">${this.escapeHtml((data.plugin_uri || "").replace(/^https?:\/\//, ""))}</a>
                        </span>
                    </div>
                    <div class="msd-version-item">
                        <span class="msd-version-icon dashicons dashicons-editor-code"></span>
                        <span class="msd-version-label">${msdAjax.strings.required_php}</span>
                        <span class="msd-version-value msd-status-good">${this.escapeHtml(data.required_php || "")}</span>
                    </div>
                    <div class="msd-version-item">
                        <span class="msd-version-icon dashicons dashicons-database"></span>
                        <span class="msd-version-label">${msdAjax.strings.database_tables}</span>
                        <span class="msd-version-value ${data.database_status === "active" ? "msd-db-status-good" : "msd-db-status-warning"}">
                            ${data.database_status === "active" ? msdAjax.strings.check_mark : msdAjax.strings.warning_mark} ${data.database_status === "active" ? msdAjax.strings.activity_table_created : msdAjax.strings.activity_table_missing}
                        </span>
                    </div>
                </div>

                <div class="msd-version-quick-actions">
                    <h4>${msdAjax.strings.quick_actions || "Quick Actions"}</h4>
                    <div class="msd-quick-actions-grid">
                        <button class="msd-action-btn" onclick="MSD.checkForUpdates()" title="${msdAjax.strings.check_updates || "Check for Updates"}">
                            <span class="dashicons dashicons-update"></span>
                            <span>${msdAjax.strings.check_updates || "Check Updates"}</span>
                        </button>
                        <button class="msd-action-btn" onclick="MSD.clearCache('all')" title="${msdAjax.strings.clear_cache || "Clear All Cache"}">
                            <span class="dashicons dashicons-trash"></span>
                            <span>${msdAjax.strings.clear_cache || "Clear Cache"}</span>
                        </button>
                        <button class="msd-action-btn" onclick="MSD.exportDiagnostics()" title="${msdAjax.strings.export_diagnostics || "Export Diagnostics"}">
                            <span class="dashicons dashicons-download"></span>
                            <span>${msdAjax.strings.export_diagnostics || "Export Info"}</span>
                        </button>
                    </div>
                </div>
            `;

      $container.html(html);
    },

    renderCustomNews($container, news) {
      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="custom_news">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>
            `;

      if (!news || news.length === 0) {
        html += `
                    <div class="msd-news-empty">
                        <p>${msdAjax.strings.no_news_items}</p>
                        <p>${msdAjax.strings.configure_news_sources}</p>
                    </div>
                `;
      } else {
        html += '<div class="msd-news-list">';
        news.forEach((item) => {
          const date = item.date ? this.formatNewsDate(item.date) : "";
          const cleanTitle = this.decodeHtmlEntities(item.title || "");
          const cleanDescription = this.decodeHtmlEntities(
            item.description || "",
          );

          html += `
                        <div class="msd-news-item">
                            <div class="msd-news-meta">
                                <span class="msd-news-source">${this.escapeHtml(item.source || "")}</span>
                                <span class="msd-news-date">${date}</span>
                            </div>
                            <h4 class="msd-news-title">
                                <a href="${this.escapeHtml(item.link || "")}" target="_blank" rel="noopener noreferrer">
                                    ${this.escapeHtml(cleanTitle)}
                                </a>
                            </h4>
                            <p class="msd-news-description">${this.escapeHtml(cleanDescription)}</p>
                        </div>
                    `;
        });
        html += "</div>";
      }

      html += `
                <div class="msd-news-settings">
                    <button class="msd-settings-link" onclick="MSD.showNewsSourcesModal()">
                        <span class="dashicons dashicons-admin-generic"></span>
                        ${msdAjax.strings.configure_sources}
                    </button>
                </div>
            `;

      $container.html(html);
    },

    renderNetworkSettings($container, data) {
      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="network_settings">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>

                <div class="msd-settings-simple">
                    <div class="msd-settings-info">
                        <div class="msd-setting-row">
                            <span class="msd-setting-key">${msdAjax.strings.network}:</span>
                            <span class="msd-setting-val">${this.escapeHtml(data.network_info?.network_name || msdAjax.strings.not_available)}</span>
                        </div>
                        <div class="msd-setting-row">
                            <span class="msd-setting-key">${msdAjax.strings.registration}:</span>
                            <span class="msd-setting-val">${this.getRegistrationLabel(data.network_info?.registration || "none")}</span>
                        </div>
                        <div class="msd-setting-row">
                            <span class="msd-setting-key">${msdAjax.strings.upload_limit}:</span>
                            <span class="msd-setting-val">${data.network_info?.blog_upload_space || 0} ${msdAjax.strings.mb}</span>
                        </div>
                        <div class="msd-setting-row">
                            <span class="msd-setting-key">${msdAjax.strings.active_plugins}:</span>
                            <span class="msd-setting-val">${data.theme_plugin_settings?.network_active_plugins || 0}</span>
                        </div>
                        <div class="msd-setting-row">
                            <span class="msd-setting-key">${msdAjax.strings.network_themes}:</span>
                            <span class="msd-setting-val">${data.theme_plugin_settings?.network_themes || 0}</span>
                        </div>
                    </div>

                    <div class="msd-settings-actions">
                        <a href="${data.quick_actions?.network_settings_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-admin-settings"></i>
                            ${msdAjax.strings.settings}
                        </a>
                        <a href="${data.quick_actions?.network_sites_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-admin-multisite"></i>
                            ${msdAjax.strings.sites}
                        </a>
                        <a href="${data.quick_actions?.network_users_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-admin-users"></i>
                            ${msdAjax.strings.users}
                        </a>
                        <a href="${data.quick_actions?.network_themes_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-admin-appearance"></i>
                            ${msdAjax.strings.themes}
                        </a>
                        <a href="${data.quick_actions?.network_plugins_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-admin-plugins"></i>
                            ${msdAjax.strings.plugins}
                        </a>
                        <a href="${data.quick_actions?.network_updates_url || "#"}" class="msd-settings-link">
                            <i class="dashicons dashicons-update"></i>
                            ${msdAjax.strings.updates}
                        </a>
                    </div>
                </div>
            `;

      $container.html(html);
    },

    renderUserManagement($container, data) {
      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="user_management">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>

                <div class="msd-user-simple">
                    <div class="msd-user-header">
                        <span class="msd-user-count">${data.total_users || 0} ${msdAjax.strings.users} (${data.super_admin_count || 0} ${msdAjax.strings.admins})</span>
                        <span class="msd-user-registration">${data.registration_status?.description || msdAjax.strings.unknown}</span>
                    </div>

                    <div class="msd-user-recent">
            `;

      if (data.recent_registrations && data.recent_registrations.length > 0) {
        data.recent_registrations.slice(0, 5).forEach((user) => {
          const statusClass = this.getUserStatusClass(user.status);
          html += `
                        <div class="msd-user-row">
                            <img src="${user.avatar_url || this.getDefaultAvatar()}"
                                 alt="${this.escapeHtml(user.display_name)}"
                                 class="msd-user-avatar"
                                 onerror="this.src='${this.getDefaultAvatar()}'">
                            <div class="msd-user-data">
                                <div class="msd-user-name">${this.escapeHtml(user.display_name)}</div>
                                <div class="msd-user-info">${user.registered_ago} ${msdAjax.strings.separator} ${user.sites_count} ${msdAjax.strings.sites}</div>
                            </div>
                            <div class="msd-user-actions">
                                <span class="msd-user-status ${statusClass}">${this.getUserStatusLabel(user.status)}</span>
                                <a href="${user.profile_url}" class="msd-user-edit">${msdAjax.strings.edit}</a>
                            </div>
                        </div>
                    `;
        });
      } else {
        html += `<div class="msd-user-empty">${msdAjax.strings.no_recent_registrations}</div>`;
      }

      html += "</div>";

      if (data.pending_activations && data.pending_activations.length > 0) {
        html += `
                    <div class="msd-user-pending">
                        <div class="msd-pending-header">${data.pending_activations.length} ${msdAjax.strings.pending_activations}</div>
                `;

        data.pending_activations.slice(0, 3).forEach((signup) => {
          html += `
                        <div class="msd-pending-row">
                            <div class="msd-pending-email">${this.escapeHtml(signup.user_email)}</div>
                            <a href="${signup.activate_url}" class="msd-pending-activate">${msdAjax.strings.activate}</a>
                        </div>
                    `;
        });

        html += "</div>";
      }

      html += "</div>";

      $container.html(html);
    },

    renderLastEdits($container, activities) {
      let html = `<button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="last_edits">${msdAjax.strings.refresh_symbol || "↻"}</button>`;

      if (!activities || activities.length === 0) {
        html += `<div class="msd-empty-state"><p>${msdAjax.strings.no_recent_activity}</p></div>`;
      } else {
        html += '<div class="msd-activity-list">';
        activities.forEach((activity) => {
          const truncatedContent = activity.content
            ? this.truncateText(activity.content, 30)
            : "";
          html += `
                        <div class="msd-activity-item">
                            <div class="msd-activity-header">
                                <h4 class="msd-activity-title">
                                    <a href="${activity.view_url}" target="_blank">${this.truncateText(this.escapeHtml(activity.title), 60)}</a>
                                </h4>
                                <span class="msd-activity-type ${activity.type}">${activity.type_label}</span>
                            </div>
                            <div class="msd-activity-meta">
                                <span class="msd-activity-site">${this.escapeHtml(activity.site_name)}</span>
                                <span class="msd-activity-date">${activity.date_human}</span>
                            </div>
                            ${truncatedContent ? `<p class="msd-activity-content">${this.escapeHtml(truncatedContent)}</p>` : ""}
                            <div class="msd-activity-actions">
                                <a href="${activity.edit_url}" class="msd-activity-action">${msdAjax.strings.edit}</a>
                                <a href="${activity.view_url}" class="msd-activity-action" target="_blank">${msdAjax.strings.view}</a>
                            </div>
                        </div>
                    `;
        });
        html += "</div>";
      }

      $container.html(html);
    },

    renderTodoItems($container, todos) {
      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="todo_items">${msdAjax.strings.refresh_symbol || "↻"}</button>

                <div class="msd-todo-container">
                    <div class="msd-todo-header">
                        <div class="msd-todo-stats">
                            <div class="msd-todo-stat">
                                <span class="dashicons dashicons-list-view"></span>
                                <span>${todos.length} ${msdAjax.strings.total}</span>
                            </div>
                            <div class="msd-todo-stat">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <span>${todos.filter((t) => t.completed).length} ${msdAjax.strings.done}</span>
                            </div>
                        </div>
                        <button class="msd-todo-add-btn">
                            ${msdAjax.strings.add_todo}
                        </button>
                    </div>
            `;

      if (todos.length === 0) {
        html += `
                    <div class="msd-empty-state">
                        <p>${msdAjax.strings.no_todos}</p>
                    </div>
                `;
      } else {
        html += '<div class="msd-todo-list">';
        todos.forEach((todo) => {
          const completedClass = todo.completed ? "completed" : "";
          const priorityLabel =
            msdAjax.strings[todo.priority + "_priority"] || todo.priority;
          const dueStatusClass = todo.due_status
            ? `msd-due-${todo.due_status}`
            : "";
          html += `
                        <div class="msd-todo-item ${completedClass} ${dueStatusClass}" data-id="${todo.id}">
                            <input type="checkbox" class="msd-todo-checkbox" ${todo.completed ? "checked" : ""}>
                            <div class="msd-todo-content">
                                <div class="msd-todo-title">${this.escapeHtml(todo.title)}</div>
                                ${todo.description ? `<div class="msd-todo-description">${this.escapeHtml(todo.description)}</div>` : ""}
                                ${
                                  todo.due_date
                                    ? `
                                    <div class="msd-todo-due-date">
                                        <span class="dashicons dashicons-calendar-alt"></span>
                                        <span class="msd-due-date-text">${todo.due_date_formatted}</span>
                                        ${todo.due_status !== "none" ? `<span class="msd-due-status ${todo.due_status}">${todo.due_status_text}</span>` : ""}
                                    </div>
                                `
                                    : ""
                                }
                                <div class="msd-todo-meta">
                                    <span class="msd-todo-priority ${todo.priority}">${priorityLabel}</span>
                                    <span class="msd-todo-date">${todo.created_at_human}</span>
                                </div>
                            </div>
                            <div class="msd-todo-actions">
                                <button class="msd-todo-btn msd-todo-edit" title="${msdAjax.strings.edit}">
                                    <span class="dashicons dashicons-edit"></span>
                                </button>
                                <button class="msd-todo-btn msd-todo-delete" title="${msdAjax.strings.delete}">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </div>
                    `;
        });
        html += "</div>";
      }

      html += `
                    <div class="msd-todo-form" id="msd-todo-form">
                        <div class="msd-form-field">
                            <label>${msdAjax.strings.title}</label>
                            <input type="text" id="msd-todo-title" placeholder="${msdAjax.strings.todo_placeholder}">
                        </div>
                        <div class="msd-form-field">
                            <label>${msdAjax.strings.description_optional}</label>
                            <textarea id="msd-todo-description" placeholder="${msdAjax.strings.additional_details}"></textarea>
                        </div>
                        <div class="msd-form-row">
                            <div class="msd-form-field">
                                <label>${msdAjax.strings.priority}</label>
                                <select id="msd-todo-priority">
                                    <option value="low">${msdAjax.strings.low_priority}</option>
                                    <option value="medium" selected>${msdAjax.strings.medium_priority}</option>
                                    <option value="high">${msdAjax.strings.high_priority}</option>
                                </select>
                            </div>
                            <div class="msd-form-field">
                                <label>${msdAjax.strings.due_date || "Due Date"} <span class="msd-optional">(${msdAjax.strings.optional || "optional"})</span></label>
                                <input type="date" id="msd-todo-due-date" min="${new Date().toISOString().split("T")[0]}">
                            </div>
                        </div>
                        <div class="msd-todo-form-actions">
                            <button class="button button-primary msd-todo-save">${msdAjax.strings.save}</button>
                            <button class="button msd-todo-cancel">${msdAjax.strings.cancel}</button>
                        </div>
                    </div>
                </div>
            `;

      $container.html(html);
      // Cache todos data for editing
      $container.data("todos", todos);
    },

    showTodoForm() {
      $("#msd-todo-form").addClass("active");
      $("#msd-todo-title").focus();
    },

    cancelTodoForm() {
      $("#msd-todo-form").removeClass("active");
      this.clearTodoForm();
    },

    clearTodoForm() {
      $("#msd-todo-title").val("");
      $("#msd-todo-description").val("");
      $("#msd-todo-priority").val("medium");
      $("#msd-todo-due-date").val("");
      $("#msd-todo-form").removeData("edit-id");
    },

    saveTodo() {
      const title = $("#msd-todo-title").val().trim();
      if (!title) {
        if (window.MSD_Core) {
          window.MSD_Core.showNotice(msdAjax.strings.title_required, "error");
        }
        return;
      }

      const data = {
        title: title,
        description: $("#msd-todo-description").val().trim(),
        priority: $("#msd-todo-priority").val(),
        due_date: $("#msd-todo-due-date").val(),
      };

      const editId = $("#msd-todo-form").data("edit-id");
      const action = editId ? "msd_update_todo_item" : "msd_save_todo_item";

      if (editId) {
        data.id = editId;
      }

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          action,
          data,
          (response) => {
            window.MSD_Core.showNotice(response.data.message, "success");
            this.cancelTodoForm();
            this.loadWidget("todo_items");
          },
          (error) => {
            window.MSD_Core.showNotice(
              error || msdAjax.strings.failed_create_todo,
              "error",
            );
          },
        );
      }
    },

    editTodo(e) {
      const $item = $(e.currentTarget).closest(".msd-todo-item");
      const id = $item.data("id");

      // Get todo data from the widget's cached data
      const $container = $item.closest(".msd-widget-content");
      const todos = $container.data("todos") || [];
      const todo = todos.find((t) => t.id === id);

      if (todo) {
        $("#msd-todo-title").val(todo.title);
        $("#msd-todo-description").val(todo.description || "");
        $("#msd-todo-priority").val(todo.priority || "medium");
        $("#msd-todo-due-date").val(todo.due_date || "");
        $("#msd-todo-form").data("edit-id", id).addClass("active");
        $("#msd-todo-title").focus();
      }
    },

    deleteTodo(e) {
      if (!confirm(msdAjax.strings.confirm_delete)) {
        return;
      }

      const $item = $(e.currentTarget).closest(".msd-todo-item");
      const id = $item.data("id");

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_delete_todo_item",
          { id },
          (response) => {
            window.MSD_Core.showNotice(response.data.message, "success");
            this.loadWidget("todo_items");
          },
          (error) => {
            window.MSD_Core.showNotice(
              error || msdAjax.strings.failed_delete_todo,
              "error",
            );
          },
        );
      }
    },

    toggleTodoComplete(e) {
      const $item = $(e.currentTarget).closest(".msd-todo-item");
      const id = $item.data("id");

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_toggle_todo_complete",
          { id },
          (response) => {
            this.loadWidget("todo_items");
          },
          (error) => {
            window.MSD_Core.showNotice(
              error || msdAjax.strings.failed_update_status,
              "error",
            );
          },
        );
      }
    },

    handleUserAction(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const action = $btn.data("action");
      const userId = $btn.data("user-id");

      if (!action || !userId) {
        if (window.MSD_Core) {
          window.MSD_Core.showNotice(
            msdAjax.strings.invalid_widget_id,
            "error",
          );
        }
        return;
      }

      if (!confirm(msdAjax.strings.confirm_action)) {
        return;
      }

      $btn.prop("disabled", true).text(msdAjax.strings.processing);

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_manage_user_action",
          {
            user_action: action,
            user_id: userId,
          },
          (response) => {
            window.MSD_Core.showNotice(
              response.data.message || msdAjax.strings.action_completed,
              "success",
            );
            this.loadWidget("user_management");
          },
          (error) => {
            window.MSD_Core.showNotice(
              error || msdAjax.strings.failed_user_action,
              "error",
            );
          },
        ).always(() => {
          $btn
            .prop("disabled", false)
            .text($btn.data("original-text") || msdAjax.strings.action);
        });
      }
    },

    handleWidgetError($container, error, widgetType) {
      if (window.MSD_Core) {
        window.MSD_Core.retryCount++;

        if (window.MSD_Core.retryCount <= window.MSD_Core.maxRetries) {
          setTimeout(() => {
            this.loadWidget(widgetType);
          }, 2000 * window.MSD_Core.retryCount);
          return;
        }
      }

      const html = `
                <div class="msd-error-state">
                    <p>${msdAjax.strings.unable_to_load}</p>
                    <button class="msd-refresh-btn" data-widget="${widgetType}">${msdAjax.strings.try_again}</button>
                </div>
            `;
      $container.html(html);
    },

    formatNumber: window.MSD_Core
      ? window.MSD_Core.formatNumber
      : function (num) {
          return num.toString();
        },
    escapeHtml: window.MSD_Core
      ? window.MSD_Core.escapeHtml
      : function (text) {
          return text;
        },
    decodeHtmlEntities: window.MSD_Core
      ? window.MSD_Core.decodeHtmlEntities
      : function (text) {
          return text;
        },
    formatBytes(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },
    truncateText: window.MSD_Core
      ? window.MSD_Core.truncateText
      : function (text, maxLength) {
          return text;
        },
    getDefaultFavicon: window.MSD_Core
      ? window.MSD_Core.getDefaultFavicon
      : function () {
          return "";
        },
    getDefaultAvatar: window.MSD_Core
      ? window.MSD_Core.getDefaultAvatar
      : function () {
          return "";
        },
    formatNewsDate: window.MSD_Core
      ? window.MSD_Core.formatNewsDate
      : function (date) {
          return date;
        },
    getUserStatusClass: window.MSD_Core
      ? window.MSD_Core.getUserStatusClass
      : function (status) {
          return status;
        },
    getUserStatusLabel: window.MSD_Core
      ? window.MSD_Core.getUserStatusLabel
      : function (status) {
          return status;
        },
    getRegistrationLabel: window.MSD_Core
      ? window.MSD_Core.getRegistrationLabel
      : function (reg) {
          return reg;
        },
    getStorageStatusClass: window.MSD_Core
      ? window.MSD_Core.getStorageStatusClass
      : function (status) {
          return status;
        },

    // Error log monitoring methods
    renderErrorLogs($container, data) {
      const stats = data || {};
      const logs = stats.logs || [];
      const fileSize = stats.file_size || "";
      const logEnabled = stats.log_enabled !== false;

      let html = `
      <div class="msd-monitoring-header">
        <div class="msd-header-title">
          <span>PHP Error Logs</span>
          ${logEnabled ? '<span class="msd-status-badge enabled">Active</span>' : '<span class="msd-status-badge disabled">Disabled</span>'}
        </div>
        <button class="button button-small msd-clear-error-log" title="Clear logs">
          Clear
        </button>
        <a href="${msdAjax.settingsUrl}&tab=monitoring" class="msd-settings-link">
          <span class="dashicons dashicons-admin-generic"></span>
          Configure monitoring
        </a>
        <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="error_logs">
          ${msdAjax.strings.refresh_symbol || "↻"}
        </button>
      </div>
      <div class="msd-log-controls">
        <div class="msd-control-group">
          <label>Type</label>
          <select class="msd-log-type">
            <option value="all">All Types</option>
            <option value="fatal">Fatal</option>
            <option value="warning">Warning</option>
            <option value="notice">Notice</option>
            <option value="deprecated">Deprecated</option>
            <option value="parse">Parse</option>
            <option value="other">Other</option>
          </select>
        </div>
        <div class="msd-control-group msd-control-search">
          <label>Search</label>
          <input type="text" class="msd-log-search" placeholder="Search errors..." />
        </div>
        <div class="msd-control-group">
          <label>Show</label>
          <select class="msd-log-limit">
            <option value="50">50</option>
            <option value="100" selected>100</option>
            <option value="200">200</option>
          </select>
        </div>
      </div>
    `;

      if (!logEnabled) {
        html += `
        <div class="msd-alert msd-alert-warning">
          <span class="dashicons dashicons-warning"></span>
          <div>
            Debug logging is not enabled.
          </div>
        </div>
      `;
      }

      if (!logs.length) {
        html += `<div class="msd-empty-state"><span class="dashicons dashicons-yes-alt"></span><p>${msdAjax.strings.no_logs || "No log entries found."}</p></div>`;
      } else {
        html += `
        <div class="msd-log-summary">
          <div class="msd-summary-item">
            <strong>${stats.displayed_count || logs.length}</strong> entries displayed • ${fileSize}
          </div>
        </div>
      `;
        html += '<div class="msd-errorlog-list">';
        logs.forEach((log, idx) => {
          const type = log.type || "other";
          const time = this.escapeHtml(log.timestamp || "");
          const messagePreview = this.truncateText(log.message || "", 80);
          const messageFull = this.escapeHtml(log.message || "");
          const typeIcons = {
            fatal:
              '<span class="dashicons dashicons-dismiss" style="color:#d63638"></span>',
            warning:
              '<span class="dashicons dashicons-warning" style="color:#dba617"></span>',
            notice:
              '<span class="dashicons dashicons-info" style="color:#2271b1"></span>',
            deprecated:
              '<span class="dashicons dashicons-editor-help" style="color:#9b59b6"></span>',
            parse:
              '<span class="dashicons dashicons-code-standards" style="color:#e67e22"></span>',
            other:
              '<span class="dashicons dashicons-marker" style="color:#95a5a6"></span>',
          };
          const icon = typeIcons[type] || typeIcons.other;
          html += `
          <div class="msd-log-item msd-log-${type}">
            <div class="msd-log-head">
              <span class="msd-log-icon">${icon}</span>
              <span class="msd-log-time">${time}</span>
              <span class="msd-log-type-badge msd-badge-${type}">${type.toUpperCase()}</span>
              <button class="msd-log-toggle" aria-expanded="false"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
            </div>
            <div class="msd-log-preview">${this.escapeHtml(messagePreview)}</div>
            <pre class="msd-log-body" style="display:none;">${messageFull}</pre>
          </div>
        `;
        });
        html += "</div>";
      }

      $container.html(html);
    },

    toggleLogEntry(e) {
      const $btn = $(e.currentTarget);
      const $item = $btn.closest(".msd-log-item");
      const $preview = $item.find(".msd-log-preview");
      const $body = $item.find(".msd-log-body");
      const expanded = $btn.attr("aria-expanded") === "true";

      if (expanded) {
        $body.slideUp(200);
        $preview.show();
        $btn
          .find(".dashicons")
          .removeClass("dashicons-arrow-up-alt2")
          .addClass("dashicons-arrow-down-alt2");
      } else {
        $body.slideDown(200);
        $preview.hide();
        $btn
          .find(".dashicons")
          .removeClass("dashicons-arrow-down-alt2")
          .addClass("dashicons-arrow-up-alt2");
      }

      $btn.attr("aria-expanded", expanded ? "false" : "true");
    },

    clearErrorLogs(e) {
      e.preventDefault();
      if (!confirm(msdAjax.strings.confirm_action)) return;
      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_clear_error_logs",
          {},
          (response) => {
            window.MSD_Core.showNotice(
              response.data.message || "Cleared",
              "success",
            );
            this.loadWidget("error_logs");
          },
          (error) => {
            window.MSD_Core.showNotice(error || "Failed", "error");
          },
        );
      }
    },

    // 404 monitoring methods
    render404Stats($container, data) {
      const stats = data || {};
      const top = stats.top_urls || [];
      const trend = stats.daily_trend || [];
      const monitoring = !!stats.monitoring_enabled;
      const totalCount = stats.total_count || 0;

      let html = `
      <div class="msd-monitoring-header">
        <div class="msd-header-title">
          <span>404 Monitor</span>
          <label class="msd-toggle-switch">
            <input type="checkbox" class="msd-toggle-404" ${monitoring ? "checked" : ""} />
            <span class="msd-toggle-slider"></span>
            <span class="msd-toggle-label">${monitoring ? "ON" : "OFF"}</span>
          </label>
        </div>
        <button class="button button-small msd-clear-404" title="Clear logs">
          Clear
        </button>
        <a href="${msdAjax.settingsUrl}&tab=monitoring" class="msd-settings-link">
          <span class="dashicons dashicons-admin-generic"></span>
          Configure monitoring
        </a>
        <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="monitor_404">
          ${msdAjax.strings.refresh_symbol || "↻"}
        </button>
      </div>
      <div class="msd-404-controls">
        <div class="msd-control-group">
          <label>Period</label>
          <select class="msd-404-days">
            <option value="7">7 days</option>
            <option value="14">14 days</option>
            <option value="30" selected>30 days</option>
          </select>
        </div>
        <div class="msd-control-group">
          <label>Show</label>
          <select class="msd-404-limit">
            <option value="10">Top 10</option>
            <option value="20" selected>Top 20</option>
            <option value="50">Top 50</option>
          </select>
        </div>
      </div>
    `;

      // Show monitoring status alert
      if (!monitoring) {
        html += `
        <div class="msd-alert msd-alert-info">
          <span class="dashicons dashicons-info"></span>
            404 Monitoring is disabled.
          </div>
        </div>
      `;
      }

      html += `
      <div class="msd-404-stats-row">
        <div class="msd-stat-card">
          <div class="msd-stat-content">
            <div class="msd-stat-value">${totalCount}</div>
            <div class="msd-stat-label">Total 404s</div>
          </div>
        </div>
        <div class="msd-stat-card">
          <div class="msd-stat-content">
            <div class="msd-stat-value">${top.length}</div>
            <div class="msd-stat-label">Unique URLs</div>
          </div>
        </div>
        <div class="msd-stat-card ${monitoring ? "active" : "inactive"}">
          <div class="msd-stat-content">
            <div class="msd-stat-value">${monitoring ? "ON" : "OFF"}</div>
            <div class="msd-stat-label">Monitoring</div>
          </div>
        </div>
      </div>
    `;

      if (trend.length) {
        html += '<div class="msd-404-trend-container">';
        html +=
          '<h4 class="msd-trend-title"><span class="dashicons dashicons-chart-line"></span> Daily Trend</h4>';
        html += '<div class="msd-404-trend">';
        const maxCount = Math.max(
          ...trend.map((d) => parseInt(d.count || 0, 10)),
          1,
        );
        trend.forEach((d) => {
          const count = parseInt(d.count || 0, 10);
          const height = Math.max(5, (count / maxCount) * 100);
          const dateShort = d.date ? d.date.substring(5) : "";
          html += `
          <div class="msd-404-bar-wrapper" title="${this.escapeHtml(d.date)}: ${count} errors">
            <div class="msd-404-bar" style="height:${height}%"></div>
            <div class="msd-404-bar-label">${dateShort}</div>
          </div>
        `;
        });
        html += "</div></div>";
      }

      // Widget footer (for additional controls if needed)
      html += `
      <div class="msd-widget-footer">

      </div>
    `;

      if (!top.length) {
        html += `<div class="msd-empty-state"><span class="dashicons dashicons-yes-alt"></span><p>${msdAjax.strings.no_404s || "No 404 errors found."}</p></div>`;
      } else {
        html += '<div class="msd-404-section">';
        html += '<h4 class="msd-section-title">Top 404 Errors</h4>';
        html += '<div class="msd-404-list">';
        top.forEach((row, idx) => {
          const severity =
            row.count > 50 ? "critical" : row.count > 20 ? "warning" : "normal";
          const severityLabel =
            row.count > 50 ? "HIGH" : row.count > 20 ? "MED" : "LOW";
          html += `
          <div class="msd-404-item msd-severity-${severity}">
            <div class="msd-404-rank">#${idx + 1}</div>
            <div class="msd-404-info">
              <div class="msd-404-url-row">
                <span class="msd-404-url" title="${this.escapeHtml(row.url)}">${this.escapeHtml(this.truncateText(row.url, 60))}</span>
                <span class="msd-severity-label msd-severity-${severity}">${severityLabel}</span>
              </div>
              <div class="msd-404-meta">
                <span class="msd-meta-item">${row.count}× hits</span>
                <span>•</span>
                <span class="msd-meta-item">Last: ${this.escapeHtml(row.last_seen)}</span>
              </div>
            </div>
          </div>
        `;
        });
        html += "</div></div>";
      }

      $container.html(html);
    },

    toggle404Monitoring(e) {
      const $checkbox = $(e.currentTarget);
      const enabled = $checkbox.is(":checked");

      // Show confirmation for disabling
      if (
        !enabled &&
        !confirm(
          "Are you sure you want to disable 404 monitoring? This will stop tracking new 404 errors.",
        )
      ) {
        $checkbox.prop("checked", true);
        return;
      }

      if (window.MSD_Core) {
        // Disable checkbox during request
        $checkbox.prop("disabled", true);

        window.MSD_Core.makeAjaxRequest(
          "msd_toggle_404_monitoring",
          { enabled: enabled ? "true" : "false" },
          (response) => {
            window.MSD_Core.showNotice(
              response.data.message ||
                (enabled ? "Monitoring enabled" : "Monitoring disabled"),
              "success",
            );
            this.loadWidget("monitor_404");
          },
          (error) => {
            window.MSD_Core.showNotice(
              error || "Failed to update monitoring status",
              "error",
            );
            // Revert checkbox on error
            $checkbox.prop("checked", !enabled);
          },
        ).always(() => {
          $checkbox.prop("disabled", false);
        });
      }
    },

    clear404Logs(e) {
      e.preventDefault();
      if (!confirm(msdAjax.strings.confirm_action)) return;
      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_clear_404_logs",
          {},
          (response) => {
            window.MSD_Core.showNotice(
              response.data.message || "Cleared",
              "success",
            );
            this.loadWidget("monitor_404");
          },
          (error) => {
            window.MSD_Core.showNotice(error || "Failed", "error");
          },
        );
      }
    },

    renderDomainMapping($container, data) {
      // Domain mapping widget is rendered server-side
      // Just update the content if HTML is provided
      if (data && data.html) {
        $container.html(data.html);
        this.initDomainMappingHandlers($container);
      }
    },

    initDomainMappingHandlers($container) {
      // Refresh health button
      $container.find(".msd-dm-refresh-health").on("click", (e) => {
        e.preventDefault();
        const $button = $(e.currentTarget);
        const originalHtml = $button.html();

        $button
          .prop("disabled", true)
          .html(
            '<span class="dashicons dashicons-update dashicons-update-spin"></span> ' +
              (msdAjax.strings.processing || "Processing..."),
          );

        if (window.MSD_Core) {
          window.MSD_Core.makeAjaxRequest(
            "msd_refresh_domain_health",
            {},
            (response) => {
              if (response.data && response.data.html) {
                $container.html(response.data.html);
                this.initDomainMappingHandlers($container);
              }
              window.MSD_Core.showNotice(
                response.data.message ||
                  msdAjax.strings.refresh_success ||
                  "Refreshed",
                "success",
              );
            },
            (error) => {
              window.MSD_Core.showNotice(
                error || msdAjax.strings.error_occurred || "Error",
                "error",
              );
            },
            () => {
              $button.prop("disabled", false).html(originalHtml);
            },
          );
        }
      });
    },
  };

  window.MSD_Widgets = MSD_Widgets;
  MSD_Widgets.init();
});
