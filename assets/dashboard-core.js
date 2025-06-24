jQuery(document).ready(function ($) {
  "use strict";

  const MSD_Core = {
    refreshInterval: null,
    refreshRate: 300000,
    isRefreshing: false,
    retryCount: 0,
    maxRetries: 3,

    init() {
      this.bindEvents();
      this.startAutoRefresh();
      this.setupErrorHandling();
    },

    bindEvents() {
      $(document).on(
        "click",
        ".msd-refresh-btn",
        this.handleRefreshClick.bind(this),
      );

      $(window).on("beforeunload", () => {
        if (this.refreshInterval) {
          clearInterval(this.refreshInterval);
        }
      });
    },

    setupErrorHandling() {
      $(document).ajaxError((event, xhr, settings, error) => {
        if (settings.url && settings.url.includes("msd_")) {
          console.error("MSD Ajax Error:", error, xhr);
          this.showNotice(msdAjax.strings.error_occurred, "error");
        }
      });
    },

    startAutoRefresh() {
      this.refreshInterval = setInterval(() => {
        if (!this.isRefreshing && document.visibilityState === "visible") {
          if (window.MSD_Widgets && window.MSD_Widgets.loadAllWidgets) {
            window.MSD_Widgets.loadAllWidgets();
          }
        }
      }, this.refreshRate);
    },

    handleRefreshClick(e) {
      e.preventDefault();
      const $btn = $(e.currentTarget);
      const widgetType = $btn.data("widget");

      if ($btn.hasClass("refreshing")) return;

      $btn.addClass("refreshing").prop("disabled", true);

      setTimeout(() => {
        $btn.removeClass("refreshing").prop("disabled", false);
      }, 2000);

      if (widgetType && window.MSD_Widgets) {
        window.MSD_Widgets.loadWidget(widgetType);
        this.showNotice(msdAjax.strings.refresh_success, "success", 2000);
      } else if (window.MSD_Widgets) {
        window.MSD_Widgets.loadAllWidgets();
        this.showNotice(msdAjax.strings.refresh_success, "success", 2000);
      }
    },

    makeAjaxRequest(action, data, successCallback, errorCallback) {
      const ajaxData = {
        action: action,
        nonce: msdAjax.nonce,
        ...data,
      };

      return $.post(msdAjax.ajaxurl, ajaxData)
        .done((response) => {
          if (response.success) {
            successCallback(response);
          } else {
            errorCallback(response.data || msdAjax.strings.unknown);
          }
        })
        .fail((xhr, status, error) => {
          errorCallback(error || msdAjax.strings.network_error);
        });
    },

    showNotice(message, type = "info", duration = 5000) {
      const $notice = $(
        `<div class="msd-notice ${type}"><p>${this.escapeHtml(message)}</p></div>`,
      );

      const $container = $(".wrap h1").first();
      if ($container.length) {
        $container.after($notice);
      } else {
        $("body").prepend($notice);
      }

      if (duration > 0) {
        setTimeout(() => {
          $notice.fadeOut(300, () => $notice.remove());
        }, duration);
      }

      $notice.on("click", () => {
        $notice.fadeOut(300, () => $notice.remove());
      });
    },

    formatNumber(num) {
      if (num >= 1000000) {
        return (
          (num / 1000000).toFixed(1) + (msdAjax.strings.million_suffix || "M")
        );
      } else if (num >= 1000) {
        return (
          (num / 1000).toFixed(1) + (msdAjax.strings.thousand_suffix || "K")
        );
      }
      return num.toString();
    },

    formatTime(date) {
      return date.toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      });
    },

    escapeHtml(text) {
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    },

    decodeHtmlEntities(text) {
      const textarea = document.createElement("textarea");
      textarea.innerHTML = text;
      return textarea.value;
    },

    truncateText(text, maxLength) {
      if (!text || text.length <= maxLength) {
        return text;
      }
      return (
        text.substring(0, maxLength).trim() +
        (msdAjax.strings.ellipsis || "...")
      );
    },

    isValidUrl(string) {
      try {
        const url = new URL(string);
        return url.protocol === "http:" || url.protocol === "https:";
      } catch (_) {
        return false;
      }
    },

    getStorageStatusClass(status) {
      const statusMap = {
        critical: "critical",
        warning: "warning",
        good: "",
        default: "",
      };
      return statusMap[status] || statusMap.default;
    },

    getDefaultFavicon() {
      return "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzMiIgaGVpZ2h0PSIzMiIgdmlld0JveD0iMCAwIDMyIDMyIj48cmVjdCB3aWR0aD0iMzIiIGhlaWdodD0iMzIiIGZpbGw9IiNmMGYwZjAiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZm9udC1mYW1pbHk9IkFyaWFsLCBzYW5zLXNlcmlmIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iMC4zNWVtIj5TPC90ZXh0Pjwvc3ZnPg==";
    },

    getDefaultAvatar() {
      return "data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0MCIgaGVpZ2h0PSI0MCIgdmlld0JveD0iMCAwIDQwIDQwIj48Y2lyY2xlIGN4PSIyMCIgY3k9IjIwIiByPSIyMCIgZmlsbD0iI2Y2ZjdmNyIgc3Ryb2tlPSIjZGRkIi8+PGNpcmNsZSBjeD0iMjAiIGN5PSIxNSIgcj0iNiIgZmlsbD0iIzk5OSIvPjxlbGxpcHNlIGN4PSIyMCIgY3k9IjMzIiByeD0iMTAiIHJ5PSI3IiBmaWxsPSIjOTk5Ii8+PC9zdmc+";
    },

    formatNewsDate(dateString) {
      try {
        const date = new Date(dateString);
        const now = new Date();
        const diffTime = Math.abs(now - date);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays === 1) {
          return msdAjax.strings.yesterday;
        } else if (diffDays < 7) {
          return msdAjax.strings.days_ago.replace("%d", diffDays);
        } else {
          return date.toLocaleDateString();
        }
      } catch (e) {
        return "";
      }
    },

    getUserStatusClass(status) {
      const statusMap = {
        active: "good",
        recent: "good",
        inactive: "warning",
        very_inactive: "critical",
        never_logged_in: "neutral",
      };
      return statusMap[status] || "neutral";
    },

    getUserStatusLabel(status) {
      const statusLabels = {
        active: msdAjax.strings.active,
        recent: msdAjax.strings.recent,
        inactive: msdAjax.strings.inactive,
        very_inactive: msdAjax.strings.very_inactive,
        never_logged_in: msdAjax.strings.never_logged_in,
      };
      return statusLabels[status] || msdAjax.strings.unknown;
    },

    getRegistrationLabel(registration) {
      const labels = {
        none: msdAjax.strings.registration_disabled || "Disabled",
        user: msdAjax.strings.registration_users_only || "Users Only",
        blog: msdAjax.strings.registration_sites_only || "Sites Only",
        all: msdAjax.strings.registration_users_sites || "Users & Sites",
      };
      return labels[registration] || msdAjax.strings.unknown;
    },
  };

  window.MSD_Core = MSD_Core;
  MSD_Core.init();

  $("head").append(`
        <style>
            body.modal-open { overflow: hidden; }
            .msd-refresh-btn.refreshing { opacity: 0.6; pointer-events: none; }
            .msd-error-state { text-align: center; padding: 20px; color: var(--msd-text-light); }
            .msd-emoji-icon { font-size: 18px !important; display: inline-block; filter: grayscale(55%); }
        </style>
    `);
});
