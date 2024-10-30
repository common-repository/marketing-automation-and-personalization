"use strict";

/**
 * This file is injected to both the WordPress main admin Dashboard page,
 * and our Settings page in wp-admin.
 */
(function ($) {
  /**
   * Stripped down version of the same function in `admin.js`.
   *
   * @param {string} action
   * @param {function} callback
   */
  function ajaxAction(action) {
    var _ref = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : {},
        _ref$callback = _ref.callback,
        callback = _ref$callback === void 0 ? null : _ref$callback;

    $.post(ajaxurl, {
      action: action,
      nonce: convesioconvert.nonce
    }, function (res) {
      if (callback) {
        callback(res);
      }
    });
  }
  /**
   * Integration success notice dismiss and continue buttons
   */


  var $integratedNotice = $("div.convesioconvert-notice-integration-success");
  $integratedNotice.on("click", "button.notice-dismiss", function (event) {
    event.preventDefault();
    ajaxAction("convesioconvert_dismiss_integrated_notice");
    $integratedNotice.slideUp();
  });
  $integratedNotice.on("click", "#convesioconvert-go-to-dashboard-button", function (event) {
    event.preventDefault();
    var originalLink = $(this).attr("href");
    ajaxAction("convesioconvert_dismiss_integrated_notice", {
      callback: function callback() {
        window.location = originalLink;
      }
    });
  });
  /**
   * Caching plugin warning notice dismiss button
   */

  var $cachingPluginNotice = $("div.convesioconvert-notice-caching-plugin"); // Dismiss notice from transient.

  $cachingPluginNotice.on("click", "button.notice-dismiss", function (event) {
    event.preventDefault();
    ajaxAction("convesioconvert_dismiss_caching_plugin_notice");
    $cachingPluginNotice.slideUp();
  });
})(jQuery);