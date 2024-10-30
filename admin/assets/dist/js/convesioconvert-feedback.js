"use strict";

(function ($) {
  var $modal = $('#convesioconvert-modal-bg');
  var $submit = $('#convesioconvert-send-modal');
  $('#deactivate-marketing-automation-and-personalization').on('click', function (e) {
    e.preventDefault();
    $('textarea').val('');
    $('input:checkbox').removeAttr('checked');
    $modal.show();
    var href = e.target.getAttribute('href');
    $submit.attr('href', href);
  });
  $('[name="convesioconvert-deactivation-reason"]').on('click', function (e) {
    if ($(this).val() == 'other') {
      if (this.checked) {
        $('.convesioconvert-explanation').html('(required)');
      } else {
        $('.convesioconvert-explanation').html('(optional)');
      }
    }
  });
  $submit.on('click', function (e) {
    var $reasons = $('[name="convesioconvert-deactivation-reason"]:checked');
    var reasons = [];
    $reasons.each(function () {
      reasons.push($(this).val());
    });

    if (reasons.includes('other') && !$('[name="convesioconvert-deactivation-note"]').val().length) {
      $('.convesioconvert-err-note').show();
      setTimeout(function () {
        $('.convesioconvert-err-note').fadeOut();
      }, 3000);
      return false;
    }

    if (!reasons.length) {
      $('.convesioconvert-hint').show();
      return false;
    } else {
      e.target.disabled = true;
      $('.convesioconvert-hint').hide();
    }

    var data = {
      action: 'convesioconvert_feedback',
      n: $(e.target).data('nonce'),
      reasons: reasons,
      note: $('[name="convesioconvert-deactivation-note"]').val(),
      contact: $('[name="convesioconvert-deactivation-contact"]').is(':checked')
    };
    $.post(ajaxurl, data); // Deactivate plugin.

    window.location.href = e.target.getAttribute('href');
  }); // Hide when clicking out of modal.

  $('#convesioconvert-modal-bg').on('click', function (event) {
    var $target = $(event.target);

    if (!$target.closest('#convesioconvert-feedback-modal').length || $target.is('#convesioconvert-discard-modal')) {
      $('.convesioconvert-explanation').html('(optional)');
      $modal.hide();
    }
  }); // Support esc key.

  $(document).keydown(function (event) {
    if (event.keyCode == 27) {
      $('.convesioconvert-explanation').html('(optional)');
      $modal.hide();
    }
  });
})(jQuery);