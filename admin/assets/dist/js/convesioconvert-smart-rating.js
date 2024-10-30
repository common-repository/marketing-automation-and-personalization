"use strict";

/**
 * This file is injected to both the WordPress admin area.
 */
(function ($) {
  /**
   * Navigate feedback notification bar notice.
   */
  $('.convesioconvert-feedback-notification-bar-notice-step button').on('click', function () {
    var $step = $(this).closest('.convesioconvert-feedback-notification-bar-notice-step');
    var step = $(this).data('step');

    if (!step) {
      $('.convesioconvert-feedback-notification-bar-notice').find('.notice-dismiss').trigger('click');
      return;
    }

    $step.addClass('hidden');
    $step.siblings('[data-step="' + step + '"]').removeClass('hidden');
  });
  /**
   * Dismiss feedback notification bar notice on close button click.
   */

  $(document).on('click', '.convesioconvert-feedback-notification-bar-notice .notice-dismiss', function (event) {
    var grnonce = $(this).closest('.convesioconvert-feedback-notification-bar-notice').data('nonce');
    event.preventDefault();
    $.post(ajaxurl, {
      action: "convesioconvert_dismiss_smart_rating",
      nonce: grnonce
    });
  });
})(jQuery);