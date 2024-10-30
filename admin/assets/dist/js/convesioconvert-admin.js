"use strict";

/**
 * This file is injected to our Settings page in wp-admin only.
 */
(function ($) {
  var $wrap = $(".wrap");
  /**
   * Utility function to call a simple admin-ajax action.
   *
   * @param {string} action
   * @param {boolean} reload
   * @param {function} callback
   */

  function ajaxAction(action) {
    var _ref = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {},
        _ref$reload = _ref.reload,
        reload = _ref$reload === void 0 ? true : _ref$reload,
        _ref$callback = _ref.callback,
        callback = _ref$callback === void 0 ? null : _ref$callback;

    $.post(ajaxurl, {
      action: action,
      nonce: convesioconvert.nonce
    }, function (res) {
      if (res.success && reload) {
        document.location.reload();
      }

      if (callback) {
        callback(res);
      }
    });
  }
  /**
   * Confirmation dialog creation utility function
   */


  var createDialog = function createDialog(selector, options) {
    options = $.extend({
      resizable: false,
      modal: true,
      autoOpen: false,
      draggable: false,
      dialogClass: "convesioconvert-dialog-wrapper",
      width: 400
    }, options || {});

    options.create = function () {
      var instance = $(this);
      instance.siblings(".ui-dialog-titlebar").remove();
      instance.find(".convesioconvert-dialog-close").click(function (event) {
        event.preventDefault();
        instance.dialog("close");
      });
      $(document).on("click", ".ui-widget-overlay", function () {
        instance.dialog("close");
      });
    };

    var dialog = $(selector).dialog(options);
    dialog.data("uiDialog")._focusTabbable = $.noop;
    return dialog;
  };
  /**
   * Disconnect button
   */


  $(".convesioconvert-disconnect-confirm").on("click", function (event) {
    event.preventDefault();
    var disconnectConfirmDialog = createDialog("#convesioconvert-disconnect-dialog");
    disconnectConfirmDialog.dialog("open");
  });
  /**
   * Perform disconnect
   */

  $("#convesioconvert-disconnect-dialog").on("click", ".convesioconvert-remove-integration", function (e) {
    e.preventDefault();
    ajaxAction("convesioconvert_remove_integration");
  });
  /**
   * Erase Data button
   */

  $(".convesioconvert-destroy-data-confirm").on("click", function (event) {
    event.preventDefault();
    var eraseConfirmDialog = createDialog("#convesioconvert-destroy-data-dialog");
    eraseConfirmDialog.dialog("open");
  });
  /**
   * Perform erase data
   */

  $("#convesioconvert-destroy-data-dialog").on("click", ".convesioconvert-destroy-data", function (e) {
    e.preventDefault();
    ajaxAction("convesioconvert_destroy_data");
  });
  /**
   * Perform pause
   */

  $wrap.on("click", ".convesioconvert-pause-integration", function (event) {
    event.preventDefault();
    ajaxAction("convesioconvert_pause_integration");
  });
  /**
   * Perform resume
   */

  $wrap.on("click", ".convesioconvert-resume-integration", function (event) {
    event.preventDefault();
    ajaxAction("convesioconvert_resume_integration");
  });

  if (['In Progress', 'Error'].includes($("#admin-sync-status").text())) {
    var intervalId;

    var responseHandler = function responseHandler(response) {
      if (response.success && response.level) {
        clearInterval(intervalId);
        document.location.reload();
      }
    };

    var intervalTime = 5000;

    if ($("#admin-sync-status").text() == 'Error') {
      intervalTime = 60000;
    }

    intervalId = window.setInterval(function () {
      ajaxAction("convesioconvert_get_health_level", {
        reload: false,
        callback: responseHandler
      });
    }, intervalTime);
  }
  /**
   * EU Consent
   */


  $('.convesioconvert-help-icon').tooltip();
  $wrap.on('click', '#convesioconvert-save-settings', function (event) {
    event.preventDefault();
    var saveBtn = document.getElementById('convesioconvert-save-settings');
    saveBtn.disabled = 'disabled';
    var data = {
      action: 'convesioconvert_save_settings',
      nonce: convesioconvert.nonce
    };
    var $inputs = document.querySelectorAll('input[name^="convesioconvert_consent_"]');
    $inputs.forEach(function (input) {
      data[input.name] = input.checked;
    });
    data['convesioconvert_consent_statement'] = document.getElementById('convesioconvert_consent_statement').value;
    var $span = document.createElement('span');
    $span.classList.add('convesioconvert-results');
    $.post(ajaxurl, data, function (res) {
      saveBtn.removeAttribute('disabled');

      if (res && res.success) {
        $span.classList.add('success');
        $span.innerText = convesioconvert.success_message;
      } else {
        $span.classList.add('error');
        $span.innerText = convesioconvert.erorr_message;
      }

      saveBtn.insertAdjacentElement('afterend', $span);
      setTimeout(function () {
        $span.innerText = '';
        $span.classList.remove('error', 'success');
      }, 1000);
    });
  });
})(jQuery);