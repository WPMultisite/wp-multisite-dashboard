jQuery(document).ready(function ($) {
  "use strict";

  window.MSD = window.MSD || {};

  window.MSD.clearCache = function (type) {
    if (!confirm(msdAjax.strings.clear_cache_confirm)) {
      return;
    }

    $.post(
      msdAjax.ajaxurl,
      {
        action: "msd_clear_cache",
        cache_type: type,
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          alert(msdAjax.strings.cache_cleared);
        } else {
          alert(
            msdAjax.strings.cache_clear_failed +
              ": " +
              (response.data || msdAjax.strings.unknown_error),
          );
        }
      },
    ).fail(function () {
      alert(
        msdAjax.strings.cache_clear_failed +
          " " +
          msdAjax.strings.network_error_occurred,
      );
    });
  };

  window.MSD.checkForUpdates = function () {
    var $status = $("#msd-update-status");
    var $button = $status.find("button");

    $button.prop("disabled", true).text(msdAjax.strings.checking_updates);

    $.post(
      msdAjax.ajaxurl,
      {
        action: "msd_check_plugin_update",
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          if (response.data.version) {
            var message = msdAjax.strings.update_available.replace(
              "{version}",
              response.data.version,
            );
            $status.html(
              '<span class="msd-update-available">' + message + "</span>",
            );
            if (response.data.details_url) {
              $status.append(
                ' <a href="' +
                  response.data.details_url +
                  '" target="_blank">' +
                  msdAjax.strings.view_details +
                  "</a>",
              );
            }
          } else {
            $status.html(
              '<span class="msd-update-current">' +
                msdAjax.strings.up_to_date +
                "</span>",
            );
          }
        } else {
          $button.prop("disabled", false).text(msdAjax.strings.check_updates);
          alert(
            msdAjax.strings.update_check_failed +
              ": " +
              (response.data || msdAjax.strings.unknown_error),
          );
        }
      },
    ).fail(function () {
      $button.prop("disabled", false).text(msdAjax.strings.check_updates);
      alert(
        msdAjax.strings.update_check_failed +
          " " +
          msdAjax.strings.network_error_occurred,
      );
    });
  };

  window.MSD.clearWidgetCache = function () {
    if (!confirm(msdAjax.strings.clear_widget_cache_confirm)) {
      return;
    }

    $.post(
      msdAjax.ajaxurl,
      {
        action: "msd_clear_widget_cache",
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          alert(msdAjax.strings.widget_cache_cleared);
          location.reload();
        } else {
          alert(
            msdAjax.strings.widget_cache_clear_failed +
              ": " +
              (response.data || msdAjax.strings.unknown_error),
          );
        }
      },
    ).fail(function () {
      alert(
        msdAjax.strings.widget_cache_clear_failed +
          " " +
          msdAjax.strings.network_error_occurred,
      );
    });
  };

  console.log(
    msdAjax.strings.msd_settings_loaded + ":",
    Object.keys(window.MSD),
  );
});
