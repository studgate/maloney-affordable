/**
 * Hide/show Toolset field groups based on Unit Type selection
 */
(function($){
  'use strict';

  // Explicit Toolset group IDs (from inspector screenshots)
  var GROUPS = {
    property: '#wpcf-group-property-info',
    condo: ['#wpcf-group-condo-lotteries', '#wpcf-group-condominiums'],
    rental: ['#wpcf-group-rental-lotteries', '#wpcf-group-rental-properties']
  };

  function getCurrentUnitType(){
    var val = $('#listing_unit_type').val();
    if(val){ return val; }
    // URL override (post-new.php?unit_type=condo|rental)
    try {
      var params = new URLSearchParams(window.location.search);
      var ut = params.get('unit_type');
      if (ut && (ut === 'condo' || ut === 'rental')) return ut;
    } catch(e) {}
    // Try taxonomy checklist (fallback)
    var checked = $('#listing_typechecklist input:checked').closest('label').text().toLowerCase();
    if(checked.indexOf('condo') !== -1) return 'condo';
    if(checked.indexOf('rental') !== -1) return 'rental';
    return '';
  }

  function normalize(str){ return (str||'').toString().trim().toLowerCase(); }

  function groupMatchesTitle($box, name){
    var title = '';
    var $h = $box.find('h2.hndle, .postbox-header h2');
    if($h.length){ title = $h.first().text(); }
    if(!title){ title = $box.attr('aria-label') || ''; }
    return normalize(title).indexOf(normalize(name)) !== -1;
  }

  function findGroupBoxesByName(name){
    var matches = $();
    // Classic meta boxes in Gutenberg area
    $('.postbox').each(function(){
      var $b = $(this);
      if(groupMatchesTitle($b, name)){
        matches = matches.add($b);
      }
    });
    return matches;
  }

  function safeShow(sel, on){ try { $(sel).toggle(on); } catch(e){} }

  function applyVisibility(){
    var current = getCurrentUnitType();

    // Always show Property Info
    safeShow(GROUPS.property, true);
    // Default: hide both condo and rental groups until a type is chosen
    GROUPS.condo.forEach(function(s){ safeShow(s, false); });
    GROUPS.rental.forEach(function(s){ safeShow(s, false); });

    if (current === 'condo') {
      GROUPS.condo.forEach(function(s){ safeShow(s, true); });
    } else if (current === 'rental') {
      GROUPS.rental.forEach(function(s){ safeShow(s, true); });
    }

    // If we also have maloneyEditorVisibility groups, we do NOT hide/show other boxes here
    // to avoid conflicting logic; explicit IDs above take precedence.
  }

  function bind(){
    $('#listing_unit_type').on('change', applyVisibility);
    $(document).on('change', '#listing_typechecklist input', applyVisibility);
  }

  function observe(){
    // DOM mutations as Gutenberg may move boxes
    var observer = new MutationObserver(function(){ applyVisibility(); });
    var target = document.getElementById('wpbody-content') || document.body;
    if(target){ observer.observe(target, {childList:true, subtree:true}); }
  }

  $(function(){
    // Select from URL param first if present
    try {
      var params = new URLSearchParams(window.location.search);
      var ut = params.get('unit_type');
      if (ut && (ut === 'condo' || ut === 'rental')) {
        var $sel = $('#listing_unit_type');
        if ($sel.length) { $sel.val(ut); }
      }
    } catch(e) {}
    bind();
    observe();
    // Apply a few times to account for late box inserts
    // Use longer delays to let conditional-fields.js run first
    setTimeout(applyVisibility, 300);
    setTimeout(applyVisibility, 800);
    setTimeout(applyVisibility, 1500);
    setTimeout(applyVisibility, 2500);
  });

})(jQuery);
