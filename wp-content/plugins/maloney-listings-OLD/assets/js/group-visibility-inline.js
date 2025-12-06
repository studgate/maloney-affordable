/**
 * Inline toggles in meta box headers to set Condo/Rental/Both for Toolset groups
 */
(function($){
  'use strict';

  function addInlineControls(){
    if (!window.MLToolsetGroups || !Array.isArray(MLToolsetGroups.groups)) return;
    var vis = MLToolsetGroups.visibility || {};
    MLToolsetGroups.groups.forEach(function(g){
      var title = g.name;
      if (!title) return;
      var $h = $('.postbox .hndle').filter(function(){ return $(this).text().trim() === title; }).first();
      if (!$h.length) return;
      if ($h.find('.ml-inline-vis').length) return; // already added
      var current = vis[g.id] || 'both';
      var html = '<span class="ml-inline-vis" style="margin-left:8px; font-size:11px;">' +
                 '<a href="#" data-gid="'+g.id+'" data-val="both" class="ml-vis-pill '+(current==='both'?'on':'')+'">Both</a>'+
                 '<a href="#" data-gid="'+g.id+'" data-val="condo" class="ml-vis-pill '+(current==='condo'?'on':'')+'">Condo</a>'+
                 '<a href="#" data-gid="'+g.id+'" data-val="rental" class="ml-vis-pill '+(current==='rental'?'on':'')+'">Rental</a>'+
                 '</span>';
      $h.append(html);
    });
  }

  function bindEvents(){
    $(document).on('click', '.ml-vis-pill', function(e){
      e.preventDefault();
      var $a = $(this);
      var gid = $a.data('gid');
      var val = $a.data('val');
      $a.closest('.ml-inline-vis').find('.ml-vis-pill').removeClass('on');
      $a.addClass('on');
      // Save via AJAX
      $.post(MLGroupVisInline.ajaxUrl, {
        action: 'ml_save_group_visibility',
        gid: gid,
        val: val,
        nonce: MLGroupVisInline.nonce
      }, function(resp){
        if (resp && resp.success) {
          // Update local cache
          if (!window.MLToolsetGroups.visibility) MLToolsetGroups.visibility = {};
          MLToolsetGroups.visibility[gid] = val;
          // Re-run conditional visibility
          if (typeof window.toggleToolsetFields === 'function') window.toggleToolsetFields();
        } else {
          alert('Failed to save visibility');
        }
      });
    });
  }

  $(document).ready(function(){
    addInlineControls();
    bindEvents();
    // In case of dynamic changes
    setTimeout(addInlineControls, 500);
  });
})(jQuery);

