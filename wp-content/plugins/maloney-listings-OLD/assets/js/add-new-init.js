/**
 * Add New Listing init
 * - Reads unit_type from URL (condo|rental)
 * - Sets Unit Type dropdown and listing_type taxonomy
 * - Relies on Toolset's own taxonomy-driven visibility to show correct groups
 */
(function($){
  'use strict';

  function selectUnitType(type){
    var ut = (type||'').toLowerCase();
    if (ut !== 'condo' && ut !== 'rental') return;
    var $sel = $('#listing_unit_type');
    if ($sel.length) { $sel.val(ut); }
    // Sync taxonomy "Listing Type" (non-hierarchical tag UI)
    // 1) Remove opposite term if present
    try {
      $('#tagsdiv-listing_type .tagchecklist .ntdelbutton').each(function(){
        var txt = $(this).parent().text().toLowerCase();
        if ((ut === 'condo' && txt.indexOf('rental') !== -1) || (ut === 'rental' && txt.indexOf('condo') !== -1)) {
          $(this).trigger('click');
        }
      });
    } catch(e){}
    // 2) Add desired term if missing
    var haveDesired = false;
    $('#tagsdiv-listing_type .tagchecklist span').each(function(){
      var t = $(this).text().toLowerCase();
      if (t.indexOf(ut) !== -1) haveDesired = true;
    });
    if (!haveDesired) {
      var nameToAdd = (ut === 'condo') ? 'Condo' : 'Rental';
      var $input = $('#new-tag-listing_type');
      var $btn = $('#listing_type-add-submit');
      if ($input.length && $btn.length) {
        $input.val(nameToAdd);
        $btn.trigger('click');
      }
    }
    // Give Toolset a moment to react
    setTimeout(function(){ 
      $(document).trigger('ml:type-applied');
      // Trigger conditional fields toggle to show/hide appropriate fieldsets
      if (typeof window.toggleToolsetFields === 'function') {
        window.toggleToolsetFields();
      }
    }, 100);
  }

  function getUrlUnitType(){
    try { var p = new URLSearchParams(window.location.search); return p.get('unit_type') || ''; } catch(e){ return ''; }
  }

  $(function(){

    // If URL param present, apply immediately
    var ut = getUrlUnitType();
    if (ut) {
      selectUnitType(ut);
      // Also trigger conditional fields after a delay to ensure Toolset has loaded
      setTimeout(function() {
        if (typeof window.toggleToolsetFields === 'function') {
          window.toggleToolsetFields();
        }
      }, 500);
    }

    // When user changes dropdown, mirror to taxonomy
    $(document).on('change', '#listing_unit_type', function(){ 
      selectUnitType($(this).val());
      // Trigger conditional fields toggle
      setTimeout(function() {
        if (typeof window.toggleToolsetFields === 'function') {
          window.toggleToolsetFields();
        }
      }, 200);
    });

    // Safety: if Toolset boxes appear late and no type yet, keep Property Info visible by default (Toolset usually does this already)
    setTimeout(function(){
      if (!ut) {
        $('#wpcf-group-property-info').show();
      }
    }, 300);
    
    // Also trigger on page load after everything is ready (for when coming from modal redirect)
    $(window).on('load', function() {
      setTimeout(function() {
        var currentUt = getUrlUnitType() || $('#listing_unit_type').val();
        if (currentUt && typeof window.toggleToolsetFields === 'function') {
          window.toggleToolsetFields();
        }
      }, 800);
    });
  });
})(jQuery);
