/**
 * Main JS for WP Engine Update Source Selector.
 *
 * @package WPE_Update_Source_Selector
 */

/* global ajaxurl, wpeUSS */

jQuery(
  function ($) {
    /**
     * Show or hide the source selection depending on whether the use default
     * or set preferred source option is selected.
     */
    function monitorSourceSetting () {
      const settingSelector = '.wpe-update-source-selector-source-setting input[type=radio]'
      const sourceSelector = '.wpe-update-source-selector-preferred-source'

      $(settingSelector).on(
        'change',
        function () {
          if ($(settingSelector + ':checked').val() === 'no') {
            $(sourceSelector).addClass('hidden')
          } else {
            $(sourceSelector).removeClass('hidden')
          }
        }
      )
    }

    /**
     * Check the current source's status and update the status in the UI.
     */
    function checkSourceStatus (force = false) {
      const $errorNotice = $('#wpe-update-source-selector-source-status-error-notice')

      $errorNotice.addClass('hidden')
      $('.wpe-update-source-selector-source-status-label').addClass('hidden')
      $('.wpe-update-source-selector-source-status-checking').removeClass('hidden')

      const data = {
        action: 'wpe_uss_check_source_status',
        _ajax_nonce: wpeUSS.check_source_status_nonce,
        source_key: wpeUSS.current_source_key,
        force
      }

      $.ajax(
        {
          url: ajaxurl,
          type: 'POST',
          dataType: 'JSON',
          data,
          error (jqXHR, textStatus, errorThrown) {
            $errorNotice.removeClass('hidden')
          },
          success (response, textStatus, jqXHR) {
            // Didn't get the shape we expected?
            if (typeof response.success === 'undefined' || typeof response.data === 'undefined') {
              $errorNotice.removeClass('hidden')
              return
            }

            // Did we "successfully" get a WP_Error?
            if (response.success === false) {
              $errorNotice.removeClass('hidden')
              return
            }

            // Useful for debug.
            if (typeof response.data.status === 'undefined' || typeof response.data.title === 'undefined') {
              $errorNotice.removeClass('hidden')
              return
            }

            $('.wpe-update-source-selector-source-status-label').addClass('hidden')

            $('.wpe-update-source-selector-source-status-wrapper').attr('title', response.data.title)
            $('.wpe-update-source-selector-source-status-' + response.data.status).removeClass('hidden')
          }
        }
      )
    }

    /**
     * Once the page is loaded, start monitoring or fire off all the things.
     */
    $(document).ready(
      function () {
        monitorSourceSetting()

        /**
         * If the source status is "checking", fire off a request to check
         * the status.
         */
        const sourceStatusWrapperSelector = '.wpe-update-source-selector-source-status-wrapper'
        const sourceStatus = $(sourceStatusWrapperSelector).attr('data-source-status')

        if (sourceStatus === 'checking') {
          checkSourceStatus()
        }

        // If the source status is clicked, force a check.
        $('.wpe-update-source-selector-source-status-wrapper').on('click', function () {
          checkSourceStatus(true)
        })
      }
    )
  }
)
