jQuery(document).ready(function ($) {
  "use strict";

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
        .on("click", ".msd-todo-cancel", this.cancelTodoForm.bind(this));
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
      };

      const action = actionMap[widgetType];
      if (!action) return;

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          action,
          {},
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
        html += '<div class="msd-storage-summary">';
        html += `<div class="msd-storage-total">${msdAjax.strings.total_network_storage}: <strong>${data.total_formatted || msdAjax.strings.zero_bytes}</strong></div>`;
        if (data.summary) {
          html += `<div class="msd-storage-info">${msdAjax.strings.top_5_sites_storage}</div>`;
        }
        html += "</div>";

        html += '<div class="msd-storage-list">';
        data.sites.forEach((site) => {
          const fillWidth = Math.min(site.usage_percentage || 0, 100);
          const fillClass = this.getStorageStatusClass(site.status);

          html += `
                        <div class="msd-storage-item">
                            <div class="msd-storage-info">
                                <div class="msd-storage-site" title="${this.escapeHtml(site.domain)}">${this.escapeHtml(site.name)}</div>
                                <div class="msd-storage-details">${this.escapeHtml(site.domain)}</div>
                            </div>
                            <div class="msd-storage-usage">
                                <div class="msd-storage-amount">${site.storage_formatted || msdAjax.strings.zero_bytes}</div>
                                <div class="msd-storage-percentage">${site.usage_percentage || 0}%</div>
                                <div class="msd-storage-bar">
                                    <div class="msd-storage-fill ${fillClass}" style="width: ${fillWidth}%"></div>
                                </div>
                            </div>
                        </div>
                    `;
        });
        html += "</div>";
      }

      $container.html(html);
    },

    renderVersionInfo($container, data) {
      let html = `
                <button class="msd-refresh-btn" title="${msdAjax.strings.refresh}" data-widget="version_info">
                    ${msdAjax.strings.refresh_symbol || "↻"}
                </button>

                <div class="msd-version-header">
                    <h3>
                        <span class="dashicons dashicons-admin-multisite"></span>
                        ${this.escapeHtml(data.plugin_name || "WP Multisite Dashboard")}
                    </h3>
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
                            <a href="${this.escapeHtml(data.plugin_uri || "")}" target="_blank">${this.escapeHtml(data.plugin_uri || "")}</a>
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
                    <button class="button button-secondary button-small" onclick="MSD.showNewsSourcesModal()">
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
          html += `
                        <div class="msd-todo-item ${completedClass}" data-id="${todo.id}">
                            <input type="checkbox" class="msd-todo-checkbox" ${todo.completed ? "checked" : ""}>
                            <div class="msd-todo-content">
                                <div class="msd-todo-title">${this.escapeHtml(todo.title)}</div>
                                ${todo.description ? `<div class="msd-todo-description">${this.escapeHtml(todo.description)}</div>` : ""}
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
                        <div class="msd-form-field">
                            <label>${msdAjax.strings.priority}</label>
                            <select id="msd-todo-priority">
                                <option value="low">${msdAjax.strings.low_priority}</option>
                                <option value="medium" selected>${msdAjax.strings.medium_priority}</option>
                                <option value="high">${msdAjax.strings.high_priority}</option>
                            </select>
                        </div>
                        <div class="msd-todo-form-actions">
                            <button class="button button-primary msd-todo-save">${msdAjax.strings.save}</button>
                            <button class="button msd-todo-cancel">${msdAjax.strings.cancel}</button>
                        </div>
                    </div>
                </div>
            `;

      $container.html(html);
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
      const title = $item.find(".msd-todo-title").text();
      const description = $item.find(".msd-todo-description").text();

      $("#msd-todo-title").val(title);
      $("#msd-todo-description").val(description);
      $("#msd-todo-form").data("edit-id", id).addClass("active");
      $("#msd-todo-title").focus();
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
  };

  window.MSD_Widgets = MSD_Widgets;
  MSD_Widgets.init();
});
