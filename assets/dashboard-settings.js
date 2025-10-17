jQuery(document).ready(function ($) {
  "use strict";

  window.MSD = window.MSD || {};

  // Tab navigation (following wpavatar pattern)
  $('.msd-tab').on('click', function() {
    var tab = $(this).data('tab');
    if (!tab) return;

    $('.msd-tab').removeClass('active');
    $(this).addClass('active');

    $('.msd-section').removeClass('active');
    $('#msd-section-' + tab).addClass('active');

    // Show detection status only on system tab
    if (tab === 'system') {
      $('.msd-detection-status').addClass('show');
    } else {
      $('.msd-detection-status').removeClass('show');
    }

    // Update URL without page reload
    if (window.history && window.history.pushState) {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', tab);
      window.history.pushState({}, '', url);
    }
  });

  // Set active tab on page load
  function initializeTabs() {
    var currentTab = '';
    
    // Check URL parameter first
    if (window.location.search.indexOf('tab=') > -1) {
      var urlParams = new URLSearchParams(window.location.search);
      currentTab = urlParams.get('tab');
    }
    
    // If no URL param, check for active button from server
    if (!currentTab) {
      var $activeTab = $('.msd-tab.active');
      if ($activeTab.length) {
        currentTab = $activeTab.data('tab');
      }
    }
    
    // Fallback to first tab if no tab is determined
    if (!currentTab) {
      currentTab = $('.msd-tab:first').data('tab');
    }
    
    // Ensure the corresponding section exists before showing
    if (currentTab && $('#msd-section-' + currentTab).length) {
      // Remove active class from all sections
      $('.msd-section').removeClass('active');
      // Add active class to target section
      $('#msd-section-' + currentTab).addClass('active');
      // Update tab button states
      $('.msd-tab').removeClass('active');
      $('.msd-tab[data-tab="' + currentTab + '"]').addClass('active');
      
      // Handle detection status visibility
      if (currentTab === 'system') {
        $('.msd-detection-status').addClass('show');
      } else {
        $('.msd-detection-status').removeClass('show');
      }
    }
  }
  
  // Initialize tabs after DOM is ready
  initializeTabs();




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
          // 为保证稳定性，成功后整页刷新
          location.reload();
        } else {
          alert(msdAjax.strings.widget_cache_clear_failed + ": " + (response.data || msdAjax.strings.unknown_error));
        }
      }
    ).fail(function () {
      alert(msdAjax.strings.widget_cache_clear_failed + " " + msdAjax.strings.network_error_occurred);
    });
  };

  window.MSD.forceWidgetDetection = function (includeChildSites) {
    // Convert boolean parameter properly
    var childSites = false;
    if (includeChildSites === true) {
      childSites = true;
    } else if (typeof includeChildSites === 'undefined') {
      // If not specified, ask user
      childSites = confirm(
        "Do you want to include child site widget detection? This may take longer and use more memory."
      );
    }

    // Confirm detection action
    var message = childSites 
      ? "This will perform a deep scan including child sites. Continue?"
      : "This will scan for widgets on the network admin dashboard. Continue?";
    
    if (!confirm(message)) {
      return;
    }

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_force_widget_detection',
        nonce: msdAjax.nonce,
        include_child_sites: childSites ? 1 : 0,
      },
      function (response) {
        if (response.success) {
          // 成功后整页刷新，保证列表与计数同步
          location.reload();
        } else {
          alert(msdAjax.strings.failed_detect_widgets + ': ' + (response.data || msdAjax.strings.unknown_error));
        }
      }
    ).fail(function () {
      alert(msdAjax.strings.failed_detect_widgets + ' ' + msdAjax.strings.network_error_occurred);
    });
  };

  // Add a safer version that only does network detection
  window.MSD.forceNetworkWidgetDetection = function () {
    window.MSD.forceWidgetDetection(false);
  };

  // Import/Export functionality
  window.MSD.validateImportFile = function () {
    var fileInput = document.getElementById('msd-import-file');
    var file = fileInput.files[0];
    
    if (!file) {
      alert('Please select a file first.');
      return;
    }

    var formData = new FormData();
    formData.append('action', 'msd_validate_import_file');
    formData.append('nonce', msdAjax.nonce);
    formData.append('import_file', file);

    var $validateBtn = $('#msd-validate-btn');
    var originalText = $validateBtn.text();
    
    $validateBtn.prop('disabled', true).text(msdAjax.strings.processing);

    $.ajax({
      url: msdAjax.ajaxurl,
      type: 'POST',
      data: formData,
      processData: false,
      contentType: false,
      success: function (response) {
        if (response.success) {
          $('#msd-import-preview').show();
          $('.msd-import-details').html(
            '<p><strong>Export Date:</strong> ' + (response.data.export_date || 'Unknown') + '</p>' +
            '<p><strong>Plugin Version:</strong> ' + (response.data.version || 'Unknown') + '</p>' +
            '<p><strong>Source Site:</strong> ' + (response.data.site_url || 'Unknown') + '</p>' +
            '<p style="color: #00a32a;"><strong>✓ File is valid and ready to import</strong></p>'
          );
          $('#msd-import-btn').prop('disabled', false);
        } else {
          alert('Validation failed: ' + (response.data || 'Unknown error'));
          $('#msd-import-preview').hide();
          $('#msd-import-btn').prop('disabled', true);
        }
        $validateBtn.prop('disabled', false).text(originalText);
      },
      error: function () {
        alert('Network error occurred during validation.');
        $validateBtn.prop('disabled', false).text(originalText);
      }
    });
  };

  // Handle file input change
  $(document).on('change', '#msd-import-file', function () {
    var fileName = this.files[0] ? this.files[0].name : '';
    $('.msd-file-name').text(fileName);
    $('#msd-validate-btn').prop('disabled', !fileName);
    $('#msd-import-btn').prop('disabled', true);
    $('#msd-import-preview').hide();
  });

  // Handle import form submission
  $(document).on('submit', '#msd-import-form', function (e) {
    if (!confirm('Are you sure you want to import these settings? This will overwrite your current configuration.')) {
      e.preventDefault();
      return false;
    }
  });

  // Cache status check
  window.MSD.checkCacheStatus = function() {
    var $stats = $('#msd-cache-stats');
    
    $stats.html('<p>' + msdAjax.strings.loading + '</p>');

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_cache_status',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          var data = response.data;
          var html = '<div class="msd-cache-info">';
          html += '<p><strong>Widget Cache:</strong> ' + (data.widget_cache ? 'Active' : 'Empty') + '</p>';
          html += '<p><strong>Network Data Cache:</strong> ' + (data.network_cache ? 'Active' : 'Empty') + '</p>';
          html += '<p><strong>Transients:</strong> ' + data.transient_count + ' items</p>';
          html += '<p><strong>Last Updated:</strong> ' + (data.last_updated || 'Never') + '</p>';
          html += '</div>';
          $stats.html(html);
        } else {
          $stats.html('<div class="error"><p>' + (response.data || msdAjax.strings.error_occurred) + '</p></div>');
        }
      }
    ).fail(function () {
      $stats.html('<div class="error"><p>' + msdAjax.strings.network_error + '</p></div>');
    });
  };

  // Performance monitoring functions
  window.MSD.checkCachePerformance = function() {
    var $performance = $('#msd-cache-performance');
    
    $performance.html('<p>' + msdAjax.strings.loading + '</p>');

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_performance_stats',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          var data = response.data;
          var html = '<div class="msd-performance-stats">';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Cache Hit Ratio:</span>';
          html += '<span class="msd-stat-value">' + data.cache_hit_ratio + '%</span>';
          html += '</div>';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Memory Cache Items:</span>';
          html += '<span class="msd-stat-value">' + data.memory_cache_items + '</span>';
          html += '</div>';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Transient Cache Items:</span>';
          html += '<span class="msd-stat-value">' + data.transient_cache_items + '</span>';
          html += '</div>';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Object Cache:</span>';
          html += '<span class="msd-stat-value">' + (data.object_cache_enabled ? 'Enabled' : 'Disabled') + '</span>';
          html += '</div>';
          html += '</div>';
          $performance.html(html);
        } else {
          $performance.html('<div class="error"><p>' + (response.data || msdAjax.strings.error_occurred) + '</p></div>');
        }
      }
    ).fail(function () {
      $performance.html('<div class="error"><p>' + msdAjax.strings.network_error + '</p></div>');
    });
  };

  window.MSD.refreshMemoryStats = function() {
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_memory_stats',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          var data = response.data;
          $('#current-memory').text(data.current_usage);
          $('#peak-memory').text(data.peak_usage);
        }
      }
    );
  };

  window.MSD.optimizeCache = function() {
    if (!confirm('This will optimize cache performance. Continue?')) {
      return;
    }

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_optimize_cache',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          alert('Cache optimization completed successfully!');
          window.MSD.checkCachePerformance();
        } else {
          alert('Cache optimization failed: ' + (response.data || 'Unknown error'));
        }
      }
    );
  };

  window.MSD.analyzeDatabasePerformance = function() {
    var $performance = $('#msd-database-performance');
    
    $performance.html('<p>' + msdAjax.strings.loading + '</p>');

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_analyze_database',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          var data = response.data;
          var html = '<div class="msd-database-stats">';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Total Queries:</span>';
          html += '<span class="msd-stat-value">' + data.query_count + '</span>';
          html += '</div>';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Slow Queries:</span>';
          html += '<span class="msd-stat-value">' + data.slow_queries + '</span>';
          html += '</div>';
          html += '<div class="msd-stat-item">';
          html += '<span class="msd-stat-label">Database Size:</span>';
          html += '<span class="msd-stat-value">' + data.database_size + '</span>';
          html += '</div>';
          html += '</div>';
          $performance.html(html);
        } else {
          $performance.html('<div class="error"><p>' + (response.data || msdAjax.strings.error_occurred) + '</p></div>');
        }
      }
    ).fail(function () {
      $performance.html('<div class="error"><p>' + msdAjax.strings.network_error + '</p></div>');
    });
  };

  window.MSD.optimizeDatabase = function() {
    if (!confirm('This will optimize database tables. This may take some time. Continue?')) {
      return;
    }

    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_optimize_database',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          alert('Database optimization completed successfully!');
          window.MSD.analyzeDatabasePerformance();
        } else {
          alert('Database optimization failed: ' + (response.data || 'Unknown error'));
        }
      }
    );
  };

  // 通知系统
  window.MSD.showNotification = function(message, type) {
    type = type || 'info';
    var $notification = $('#msd-status');
    
    $notification
      .removeClass('notice-success notice-error notice-warning notice-info')
      .addClass('notice-' + type)
      .html('<p>' + message + '</p>')
      .show();
    
    // 自动隐藏成功消息
    if (type === 'success') {
      setTimeout(function() {
        $notification.fadeOut();
      }, 3000);
    }
  };

  // 实时刷新小工具列表
  window.MSD.refreshWidgetList = function() {
    var $systemSection = $('#msd-section-system');
    var $widgetGrid = $systemSection.find('.msd-system-widgets-grid');
    var $loadingIndicator = $('<div class="msd-loading-overlay"><p>' + msdAjax.strings.loading + '</p></div>');
    
    // 显示加载指示器
    $widgetGrid.css('position', 'relative').append($loadingIndicator);
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_widget_list',
        nonce: msdAjax.nonce,
      },
      function (response) {
        if (response.success) {
          // 更新小工具列表HTML
          $widgetGrid.html(response.data.html);
          
          // 更新检测状态
          MSD.updateDetectionStatus(response.data.stats);
          
          // 显示检测统计
          if (response.data.stats) {
            var stats = response.data.stats;
            var statsMessage = 'Detection completed: Found ' + stats.total + ' widgets';
            if (stats.third_party > 0) {
              statsMessage += ' (including ' + stats.third_party + ' third-party widgets)';
            }
            MSD.showNotification(statsMessage, 'success');
          }
        } else {
          MSD.showNotification('Failed to refresh widget list', 'error');
        }
      }
    ).fail(function () {
      MSD.showNotification('Network error, unable to refresh widget list', 'error');
    }).always(function() {
      // 移除加载指示器
      $loadingIndicator.remove();
      $widgetGrid.css('position', '');
      
      // 重新启用按钮
      $('.msd-detection-controls .msd-action-buttons button').prop('disabled', false).each(function() {
        var $btn = $(this);
        var originalText = $btn.data('original-text');
        if (originalText) {
          $btn.text(originalText).removeData('original-text');
        }
      });
    });
  };

  // 更新检测状态显示
  window.MSD.updateDetectionStatus = function(stats) {
    if (stats && $('.msd-detection-status').length) {
      var statusText = 'Found ' + stats.total + ' widgets (' + stats.system + ' system, ' + stats.third_party + ' third-party). Last detection: just now';
      $('.msd-detection-status p').html('<span class="dashicons dashicons-info"></span><strong>Detection Status:</strong> ' + statusText);
    }
  };

  // 改进按钮状态管理
  $(document).on('click', '.msd-detection-controls .msd-action-buttons button', function() {
    var $btn = $(this);
    if (!$btn.data('original-text')) {
      // 保存原始文本（去掉图标）
      var originalText = $btn.text().trim();
      $btn.data('original-text', originalText);
    }
  });

  // 页面加载时初始化检测状态
  $(document).ready(function() {
    // 如果在系统小工具标签页，显示检测状态
    if ($('.msd-tab[data-tab="system"]').hasClass('active')) {
      $('.msd-detection-status').addClass('show');
    }
  });

  // Monitoring functions
  window.MSD.loadErrorLogs = function() {
    var $display = $('#msd-error-log-display');
    var $stats = $('#msd-error-log-stats');
    
    var filters = {
      type: $('#msd-error-type-filter').val(),
      search: $('#msd-error-search').val()
    };
    
    $display.html('<p>Loading error logs...</p>');
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_error_logs',
        nonce: msdAjax.nonce,
        limit: 100,
        filters: filters
      },
      function(response) {
        if (response.success && response.data.success) {
          var data = response.data;
          var logs = data.logs;
          
          if (logs.length === 0) {
            $display.html('<p class="description">No errors found with the current filters.</p>');
            $stats.hide();
            return;
          }
          
          var html = '<div class="msd-error-list">';
          logs.forEach(function(log) {
            var typeClass = 'msd-error-' + log.type;
            html += '<div class="msd-error-item ' + typeClass + '">';
            html += '<div class="msd-error-header">';
            html += '<span class="msd-error-type">' + log.type.toUpperCase() + '</span>';
            html += '<span class="msd-error-time">' + log.timestamp + '</span>';
            html += '</div>';
            html += '<div class="msd-error-message"><pre>' + escapeHtml(log.message) + '</pre></div>';
            html += '</div>';
          });
          html += '</div>';
          html += '<p class="description">Showing ' + logs.length + ' of ' + data.total_lines + ' log entries. File size: ' + data.file_size + '</p>';
          
          $display.html(html);
          
          // Update stats
          $.post(msdAjax.ajaxurl, {
            action: 'msd_get_error_logs',
            nonce: msdAjax.nonce,
            limit: 500
          }, function(resp) {
            if (resp.success && resp.data.logs) {
              var stats = {fatal: 0, warning: 0, notice: 0, deprecated: 0, total: resp.data.logs.length};
              resp.data.logs.forEach(function(log) {
                stats[log.type] = (stats[log.type] || 0) + 1;
              });
              $('#error-count-total').text(stats.total);
              $('#error-count-fatal').text(stats.fatal || 0);
              $('#error-count-warning').text(stats.warning || 0);
              $('#error-count-notice').text(stats.notice || 0);
              $stats.show();
            }
          });
        } else {
          $display.html('<div class="error"><p>' + (response.data.message || 'Failed to load error logs') + '</p></div>');
        }
      }
    ).fail(function() {
      $display.html('<div class="error"><p>Network error occurred</p></div>');
    });
  };
  
  window.MSD.clearErrorLogs = function() {
    if (!confirm('Are you sure you want to clear all error logs? This action cannot be undone.')) {
      return;
    }
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_clear_error_logs',
        nonce: msdAjax.nonce
      },
      function(response) {
        if (response.success) {
          alert('Error logs cleared successfully');
          $('#msd-error-log-display').html('<p class="description">Error log has been cleared.</p>');
          $('#msd-error-log-stats').hide();
        } else {
          alert('Failed to clear error logs: ' + (response.data || 'Unknown error'));
        }
      }
    ).fail(function() {
      alert('Network error occurred');
    });
  };
  
  window.MSD.load404Stats = function() {
    var $display = $('#msd-404-stats-display');
    
    $display.html('<p>Loading 404 statistics...</p>');
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_get_404_stats',
        nonce: msdAjax.nonce,
        limit: 20,
        days: 30
      },
      function(response) {
        if (response.success) {
          var data = response.data;
          
          if (data.total_count === 0) {
            $display.html('<p class="description">No 404 errors recorded yet.</p>');
            return;
          }
          
          var html = '<div class="msd-404-summary">';
          html += '<h4>Summary (Last 30 days)</h4>';
          html += '<p><strong>Total 404 Errors:</strong> ' + data.total_count + '</p>';
          html += '</div>';
          
          html += '<div class="msd-404-top-urls">';
          html += '<h4>Top 404 URLs</h4>';
          html += '<table class="wp-list-table widefat fixed striped">';
          html += '<thead><tr><th>URL</th><th>Count</th><th>Last Seen</th></tr></thead>';
          html += '<tbody>';
          
          data.top_urls.forEach(function(item) {
            html += '<tr>';
            html += '<td><code>' + escapeHtml(item.url) + '</code></td>';
            html += '<td>' + item.count + '</td>';
            html += '<td>' + item.last_seen + '</td>';
            html += '</tr>';
          });
          
          html += '</tbody></table>';
          html += '</div>';
          
          $display.html(html);
        } else {
          $display.html('<div class="error"><p>Failed to load 404 statistics</p></div>');
        }
      }
    ).fail(function() {
      $display.html('<div class="error"><p>Network error occurred</p></div>');
    });
  };
  
  window.MSD.clear404Logs = function() {
    if (!confirm('Are you sure you want to clear all 404 logs? This action cannot be undone.')) {
      return;
    }
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_clear_404_logs',
        nonce: msdAjax.nonce
      },
      function(response) {
        if (response.success) {
          alert('404 logs cleared successfully');
          $('#msd-404-stats-display').html('<p class="description">404 logs have been cleared.</p>');
        } else {
          alert('Failed to clear 404 logs: ' + (response.data || 'Unknown error'));
        }
      }
    ).fail(function() {
      alert('Network error occurred');
    });
  };
  
  // 404 monitoring toggle
  $('#msd-404-monitoring-toggle').on('change', function() {
    var enabled = $(this).is(':checked');
    
    $.post(
      msdAjax.ajaxurl,
      {
        action: 'msd_toggle_404_monitoring',
        nonce: msdAjax.nonce,
        enabled: enabled ? 'true' : 'false'
      },
      function(response) {
        if (response.success) {
          if (enabled) {
            $('#msd-404-stats-container').show();
            $('.msd-monitoring-disabled-notice').hide();
            MSD.showNotification('404 monitoring enabled', 'success');
          } else {
            $('#msd-404-stats-container').hide();
            $('.msd-monitoring-disabled-notice').show();
            MSD.showNotification('404 monitoring disabled', 'success');
          }
        } else {
          $('#msd-404-monitoring-toggle').prop('checked', !enabled);
          MSD.showNotification('Failed to toggle 404 monitoring', 'error');
        }
      }
    ).fail(function() {
      $('#msd-404-monitoring-toggle').prop('checked', !enabled);
      MSD.showNotification('Network error occurred', 'error');
    });
  });
  
  // Helper function to escape HTML
  function escapeHtml(text) {
    var map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }

  // Export diagnostics function
  window.MSD.exportDiagnostics = function() {
    if (!window.MSD_Core) {
      alert(msdAjax.strings.error_occurred || 'Error occurred');
      return;
    }

    window.MSD_Core.makeAjaxRequest(
      'msd_export_diagnostics',
      {},
      function(response) {
        if (response.data) {
          // Create a blob and download
          const dataStr = JSON.stringify(response.data, null, 2);
          const dataBlob = new Blob([dataStr], { type: 'application/json' });
          const url = URL.createObjectURL(dataBlob);
          const link = document.createElement('a');
          link.href = url;
          link.download = 'msd-diagnostics-' + new Date().getTime() + '.json';
          document.body.appendChild(link);
          link.click();
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
          
          window.MSD_Core.showNotice(
            msdAjax.strings.export_success || 'Diagnostics exported successfully',
            'success',
            3000
          );
        }
      },
      function() {
        window.MSD_Core.showNotice(
          msdAjax.strings.export_failed || 'Failed to export diagnostics',
          'error'
        );
      }
    );
  };

  console.log(
    msdAjax.strings.msd_settings_loaded + ":",
    Object.keys(window.MSD),
  );
});
