jQuery(document).ready(function ($) {
  "use strict";

  const MSD_Modals = {
    init() {
      this.bindEvents();
      $(document).ready(() => {
        setTimeout(() => {
          this.initSortable();
        }, 1000);
      });
    },

    bindEvents() {
      $(document)
        .on("click", "#msd-add-link", this.addQuickLinkRow.bind(this))
        .on("click", ".msd-remove-link", this.removeQuickLinkRow.bind(this))
        .on("click", "#msd-add-news-source", this.addNewsSourceRow.bind(this))
        .on("click", ".msd-remove-source", this.removeNewsSourceRow.bind(this))
        .on(
          "click",
          ".msd-modal-close, .msd-modal",
          this.handleModalClose.bind(this),
        )
        .on("click", ".msd-modal-content", function (e) {
          e.stopPropagation();
        })
        .on(
          "click",
          ".msd-news-settings button",
          this.showNewsSourcesModal.bind(this),
        );

      window.MSD = {
        showQuickLinksModal: this.showQuickLinksModal.bind(this),
        hideQuickLinksModal: this.hideQuickLinksModal.bind(this),
        saveQuickLinks: this.saveQuickLinks.bind(this),
        showNewsSourcesModal: this.showNewsSourcesModal.bind(this),
        hideNewsSourcesModal: this.hideNewsSourcesModal.bind(this),
        saveNewsSources: this.saveNewsSources.bind(this),
        showContactInfoModal: this.showContactInfoModal.bind(this),
        hideContactInfoModal: this.hideContactInfoModal.bind(this),
        saveContactInfo: this.saveContactInfo.bind(this),
        selectQRImage: this.selectQRImage.bind(this),
        removeQRCode: this.removeQRCode.bind(this),
        clearNewsCache: this.clearNewsCache.bind(this),
      };
    },

    initSortable() {
      $(document).ready(() => {
        setTimeout(() => {
          if (
            $.ui &&
            $.ui.sortable &&
            $("#msd-sortable-links").length &&
            !$("#msd-sortable-links").hasClass("ui-sortable")
          ) {
            $("#msd-sortable-links").sortable({
              tolerance: "pointer",
              cursor: "move",
              placeholder: "ui-sortable-placeholder",
              helper: function (e, ui) {
                ui.addClass("ui-sortable-helper");
                return ui;
              },
              stop: (event, ui) => {
                this.saveQuickLinksOrder();
              },
            });
          }
        }, 500);
      });
    },

    saveQuickLinksOrder() {
      const order = [];
      $("#msd-sortable-links .msd-quick-link-item").each(function () {
        const index = $(this).data("index");
        if (index !== undefined) {
          order.push(index);
        }
      });

      if (order.length > 0 && window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_reorder_quick_links",
          { order },
          () => {
            window.MSD_Core.showNotice(
              msdAjax.strings.save_success,
              "success",
              2000,
            );
          },
          () => {
            window.MSD_Core.showNotice(
              msdAjax.strings.failed_save_order,
              "error",
            );
          },
        );
      }
    },

    handleModalClose(e) {
      if (
        e.target === e.currentTarget ||
        $(e.target).hasClass("msd-modal-close")
      ) {
        this.hideQuickLinksModal();
        this.hideNewsSourcesModal();
        this.hideContactInfoModal();
      }
    },

    showQuickLinksModal() {
      $("#msd-quick-links-modal").fadeIn(200);
      $("body").addClass("modal-open");
    },

    hideQuickLinksModal() {
      $("#msd-quick-links-modal").fadeOut(200);
      $("body").removeClass("modal-open");
    },

    showNewsSourcesModal() {
      $("#msd-news-sources-modal").fadeIn(200);
      $("body").addClass("modal-open");
    },

    hideNewsSourcesModal() {
      $("#msd-news-sources-modal").fadeOut(200);
      $("body").removeClass("modal-open");
    },

    showContactInfoModal() {
      $("#msd-contact-info-modal").fadeIn(200);
      $("body").addClass("modal-open");
    },

    hideContactInfoModal() {
      $("#msd-contact-info-modal").fadeOut(200);
      $("body").removeClass("modal-open");
    },

    addQuickLinkRow() {
      const html = `
                <div class="msd-link-item">
                    <div class="msd-link-row">
                        <input type="text" placeholder="${msdAjax.strings.link_title}" class="msd-link-title" required>
                        <input type="url" placeholder="${msdAjax.strings.url_placeholder}" class="msd-link-url" required>
                    </div>
                    <div class="msd-link-options">
                        <input type="text" placeholder="${msdAjax.strings.icon_placeholder}" class="msd-link-icon">
                        <label class="msd-checkbox-label">
                            <input type="checkbox" class="msd-link-newtab">
                            ${msdAjax.strings.open_new_tab}
                        </label>
                        <button type="button" class="msd-remove-link">${msdAjax.strings.remove}</button>
                    </div>
                </div>
            `;
      $("#msd-quick-links-editor").append(html);
    },

    removeQuickLinkRow(e) {
      $(e.currentTarget)
        .closest(".msd-link-item")
        .fadeOut(200, function () {
          $(this).remove();
        });
    },

    addNewsSourceRow() {
      const html = `
                <div class="msd-news-source-item">
                    <div class="msd-source-row">
                        <input type="text" placeholder="${msdAjax.strings.source_name}" class="msd-news-name" required>
                        <input type="url" placeholder="${msdAjax.strings.rss_feed_url}" class="msd-news-url" required>
                    </div>
                    <div class="msd-source-options">
                        <label class="msd-checkbox-label">
                            <input type="checkbox" class="msd-news-enabled" checked>
                            ${msdAjax.strings.enabled}
                        </label>
                        <button type="button" class="msd-remove-source">${msdAjax.strings.remove}</button>
                    </div>
                </div>
            `;
      $("#msd-news-sources-editor").append(html);
    },

    removeNewsSourceRow(e) {
      $(e.currentTarget)
        .closest(".msd-news-source-item")
        .fadeOut(200, function () {
          $(this).remove();
        });
    },

    saveQuickLinks() {
      const links = [];
      let hasErrors = false;

      $(".msd-link-item").removeClass("error");
      $(".msd-link-url").removeClass("error");

      $(".msd-link-item").each(function () {
        const $item = $(this);
        const title = $item.find(".msd-link-title").val().trim();
        const url = $item.find(".msd-link-url").val().trim();
        const icon = $item.find(".msd-link-icon").val().trim();
        const newTab = $item.find(".msd-link-newtab").is(":checked");

        if (title && url) {
          if (!MSD_Modals.isValidUrl(url)) {
            $item.find(".msd-link-url").addClass("error");
            hasErrors = true;
            return;
          }

          $item.find(".msd-link-url").removeClass("error");
          links.push({ title, url, icon, new_tab: newTab });
        } else if (title || url) {
          $item.addClass("error");
          hasErrors = true;
        }
      });

      if (hasErrors) {
        if (window.MSD_Core) {
          window.MSD_Core.showNotice(
            msdAjax.strings.fill_required_fields,
            "error",
          );
        }
        return;
      }

      const $saveBtn = $(".msd-modal-footer .button-primary");
      $saveBtn.prop("disabled", true).text(msdAjax.strings.saving);

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_save_quick_links",
          { links },
          (response) => {
            window.MSD_Core.showNotice(response.data.message, "success");
            this.hideQuickLinksModal();
            setTimeout(() => location.reload(), 1000);
          },
          (error) => {
            window.MSD_Core.showNotice(
              msdAjax.strings.failed_save_links,
              "error",
            );
            $saveBtn.prop("disabled", false).text(msdAjax.strings.save_links);
          },
        );
      }
    },

    saveNewsSources() {
      const sources = [];
      let hasErrors = false;

      $(".msd-news-source-item").removeClass("error");
      $(".msd-news-url").removeClass("error");

      $(".msd-news-source-item").each(function () {
        const $item = $(this);
        const name = $item.find(".msd-news-name").val().trim();
        const url = $item.find(".msd-news-url").val().trim();
        const enabled = $item.find(".msd-news-enabled").is(":checked");

        if (name && url) {
          if (!MSD_Modals.isValidUrl(url)) {
            $item.find(".msd-news-url").addClass("error");
            hasErrors = true;
            return;
          }

          $item.find(".msd-news-url").removeClass("error");
          sources.push({ name, url, enabled });
        } else if (name || url) {
          $item.addClass("error");
          hasErrors = true;
        }
      });

      if (hasErrors) {
        if (window.MSD_Core) {
          window.MSD_Core.showNotice(
            msdAjax.strings.fill_required_fields,
            "error",
          );
        }
        return;
      }

      const $saveBtn = $("#msd-news-sources-modal .button-primary");
      $saveBtn.prop("disabled", true).text(msdAjax.strings.saving);

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_save_news_sources",
          { sources },
          (response) => {
            window.MSD_Core.showNotice(response.data.message, "success");
            this.hideNewsSourcesModal();
            if (window.MSD_Widgets) {
              window.MSD_Widgets.loadWidget("custom_news");
            }
          },
          (error) => {
            window.MSD_Core.showNotice(
              msdAjax.strings.failed_save_sources,
              "error",
            );
            $saveBtn
              .prop("disabled", false)
              .text(msdAjax.strings.save_news_sources);
          },
        );
      }
    },

    saveContactInfo() {
      const contactInfo = {
        name: $("#msd-contact-name").val().trim(),
        email: $("#msd-contact-email").val().trim(),
        phone: $("#msd-contact-phone").val().trim(),
        website: $("#msd-contact-website").val().trim(),
        description: $("#msd-contact-description").val().trim(),
        qq: $("#msd-contact-qq").val().trim(),
        wechat: $("#msd-contact-wechat").val().trim(),
        whatsapp: $("#msd-contact-whatsapp").val().trim(),
        telegram: $("#msd-contact-telegram").val().trim(),
        qr_code: $("#msd-contact-qr-code").val().trim(),
      };

      if (!contactInfo.name || !contactInfo.email) {
        if (window.MSD_Core) {
          window.MSD_Core.showNotice(
            msdAjax.strings.name_email_required,
            "error",
          );
        }
        return;
      }

      const $saveBtn = $("#msd-contact-info-modal .button-primary");
      $saveBtn.prop("disabled", true).text(msdAjax.strings.saving);

      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_save_contact_info",
          contactInfo,
          (response) => {
            window.MSD_Core.showNotice(response.data.message, "success");
            this.hideContactInfoModal();
            setTimeout(() => location.reload(), 1000);
          },
          (error) => {
            window.MSD_Core.showNotice(
              msdAjax.strings.failed_save_contact,
              "error",
            );
            $saveBtn
              .prop("disabled", false)
              .text(msdAjax.strings.save_contact_info);
          },
        );
      }
    },

    selectQRImage() {
      if (wp && wp.media) {
        const frame = wp.media({
          title: msdAjax.strings.select_qr_image,
          button: { text: msdAjax.strings.use_image },
          multiple: false,
        });

        frame.on("select", function () {
          const attachment = frame.state().get("selection").first().toJSON();
          $("#msd-contact-qr-code").val(attachment.url);
          $("#msd-qr-preview img").attr("src", attachment.url);
          $("#msd-qr-preview").show();
        });

        frame.open();
      } else {
        const url = prompt(msdAjax.strings.enter_qr_url);
        if (url) {
          $("#msd-contact-qr-code").val(url);
          $("#msd-qr-preview img").attr("src", url);
          $("#msd-qr-preview").show();
        }
      }
    },

    removeQRCode() {
      $("#msd-contact-qr-code").val("");
      $("#msd-qr-preview").hide();
    },

    clearNewsCache() {
      if (window.MSD_Core) {
        window.MSD_Core.makeAjaxRequest(
          "msd_refresh_widget_data",
          { widget: "custom_news" },
          (response) => {
            window.MSD_Core.showNotice(
              msdAjax.strings.news_cache_cleared,
              "success",
            );
            if (window.MSD_Widgets) {
              window.MSD_Widgets.loadWidget("custom_news");
            }
          },
          (error) => {
            window.MSD_Core.showNotice(
              msdAjax.strings.failed_clear_news_cache,
              "error",
            );
          },
        );
      }
    },

    isValidUrl(string) {
      if (window.MSD_Core && window.MSD_Core.isValidUrl) {
        return window.MSD_Core.isValidUrl(string);
      }
      try {
        const url = new URL(string);
        return url.protocol === "http:" || url.protocol === "https:";
      } catch (_) {
        return false;
      }
    },
  };

  window.MSD_Modals = MSD_Modals;
  MSD_Modals.init();
});
