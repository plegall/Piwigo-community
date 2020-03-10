/* based on core batchManagerGlobal.js, but simplified */

/* ********** Thumbs */

/* Shift-click: select all photos between the click and the shift+click */
jQuery(document).ready(function() {
	var last_clicked=0,
		last_clickedstatus=true;
	jQuery.fn.enableShiftClick = function() {
		var inputs = [],
			count=0;
		this.find('input[type=checkbox]').each(function() {
			var pos=count;
			inputs[count++]=this;
			$(this).bind("shclick", function (dummy,event) {
				if (event.shiftKey) {
					var first = last_clicked;
					var last = pos;
					if (first > last) {
						first=pos;
						last=last_clicked;
					}

					for (var i=first; i<=last;i++) {
						input = $(inputs[i]);
						$(input).prop('checked', last_clickedstatus).trigger("change");
						if (last_clickedstatus)
						{
							$(input).closest("li").addClass("thumbSelected");
						}
						else
						{
							$(input).closest("li").removeClass("thumbSelected");
						}
					}
				}
				else {
					last_clicked = pos;
					last_clickedstatus = this.checked;
				}
				return true;
			});
			$(this).click(function(event) { $(this).triggerHandler("shclick",event)});
		});
	}
	$('ul.thumbnails').enableShiftClick();
});

jQuery("a.preview-box").colorbox( {photo: true} );

/*
jQuery('.thumbnails img').tipTip({
	'delay' : 0,
	'fadeIn' : 200,
	'fadeOut' : 200
});
*/

/* ********** Actions*/

function progress(success) {
  jQuery('#progressBar').progressBar(derivatives.done, {
    max: derivatives.total,
    textFormat: 'fraction',
    boxImage: 'themes/default/images/progressbar.gif',
    barImage: 'themes/default/images/progressbg_orange.gif'
  });
	if (success !== undefined) {
		var type = success ? 'regenerateSuccess': 'regenerateError',
			s = jQuery('[name="'+type+'"]').val();
		jQuery('[name="'+type+'"]').val(++s);
	}

	if (derivatives.finished()) {
		jQuery('#applyAction').click();
	}
}

/* sync metadatas or delete photos by blocks, with progress bar */
jQuery('#applyAction').click(function(e) {
  if (typeof(elements) != "undefined") {
    return true;
  }

  if (jQuery('[name="selectAction"]').val() == 'delete') {
    if (!jQuery("#action_delete input[name=confirm_deletion]").is(':checked')) {
      jQuery("#action_delete span.errors").show();
      return false;
    }
    e.stopPropagation();
  }
  else {
    return true;
  }

  jQuery('.bulkAction').hide();
  jQuery('#regenerationText').html(lang.deleteProgressMessage);
  var maxRequests=1;

  var queuedManager = jQuery.manageAjax.create('queued', {
    queue: true,
    cacheResponse: false,
    maxRequests: maxRequests
  });

  elements = Array();

  if (jQuery('input[name=setSelected]').is(':checked')) {
    elements = all_elements;
  }
  else {
    jQuery('input[name="selection[]"]').filter(':checked').each(function() {
      elements.push(jQuery(this).val());
    });
  }

  progressBar_max = elements.length;
  var todo = 0;
  var deleteBlockSize = Math.min(
    Number((elements.length/2).toFixed()),
    1000
  );
  var image_ids = Array();

  jQuery('#applyActionBlock').hide();
  jQuery('select[name="selectAction"]').hide();
  jQuery('#regenerationMsg').show();
  jQuery('#progressBar').progressBar(0, {
    max: progressBar_max,
    textFormat: 'fraction',
    boxImage: 'themes/default/images/progressbar.gif',
    barImage: 'themes/default/images/progressbg_orange.gif'
  });

  for (i=0;i<elements.length;i++) {
    image_ids.push(elements[i]);
    if (i % deleteBlockSize != deleteBlockSize - 1 && i != elements.length - 1) {
      continue;
    }

    (function(ids) {
      var thisBatchSize = ids.length;
      queuedManager.add({
        type: 'POST',
        url: 'ws.php?format=json',
        data: {
          method: "pwg.images.delete",
          pwg_token: jQuery("input[name=pwg_token]").val(),
          image_id: ids.join(',')
        },
        dataType: 'json',
        success: function(data) {
          todo += thisBatchSize;
          var isOk = data.stat && "ok" == data.stat;
          if (isOk && data.result != thisBatchSize)
            /*TODO: user feedback only data.result images out of thisBatchSize were deleted*/;
          /*TODO: user feedback if isError*/
          progressionBar(todo, progressBar_max, isOk);
        },
        error: function(data) {
          todo += thisBatchSize;
          /*TODO: user feedback*/
          progressionBar(todo, progressBar_max, false);
        }
      });
    } )(image_ids);

    image_ids = Array();
  }

  /* tell PHP how many photos were deleted */
  jQuery('form').append('<input type="hidden" name="nb_photos_deleted" value="'+elements.length+'">');

  return false;
});

function progressionBar(val, max, success) {
  jQuery('#progressBar').progressBar(val, {
    max: max,
    textFormat: 'fraction',
    boxImage: 'themes/default/images/progressbar.gif',
    barImage: 'themes/default/images/progressbg_orange.gif'
  });

  if (val == max) {
    jQuery('#applyAction').click();
  }
}

jQuery("#action_delete input[name=confirm_deletion]").change(function() {
  jQuery("#action_delete span.errors").hide();
});
