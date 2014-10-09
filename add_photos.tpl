{if $upload_mode eq 'multiple'}
{combine_script id='jquery.jgrowl' load='footer' require='jquery' path='themes/default/js/plugins/jquery.jgrowl_minimized.js' }
{combine_script id='jquery.uploadify' load='footer' require='jquery' path='plugins/community/uploadify/jquery.uploadify.v3.0.0.min.js' }
{combine_script id='jquery.ui.progressbar' load='footer'}
{combine_css path="themes/default/js/plugins/jquery.jgrowl.css"}
{combine_css path="plugins/community/uploadify/uploadify.css"}
{/if}

{combine_script id='jquery.colorbox' load='footer' require='jquery' path='themes/default/js/plugins/jquery.colorbox.min.js'}
{combine_css path="themes/default/js/plugins/colorbox/style2/colorbox.css"}

{footer_script}{literal}
jQuery(document).ready(function(){
function sprintf() {
        var i = 0, a, f = arguments[i++], o = [], m, p, c, x, s = '';
        while (f) {
                if (m = /^[^\x25]+/.exec(f)) {
                        o.push(m[0]);
                }
                else if (m = /^\x25{2}/.exec(f)) {
                        o.push('%');
                }
                else if (m = /^\x25(?:(\d+)\$)?(\+)?(0|'[^$])?(-)?(\d+)?(?:\.(\d+))?([b-fosuxX])/.exec(f)) {
                        if (((a = arguments[m[1] || i++]) == null) || (a == undefined)) {
                                throw('Too few arguments.');
                        }
                        if (/[^s]/.test(m[7]) && (typeof(a) != 'number')) {
                                throw('Expecting number but found ' + typeof(a));
                        }
                        switch (m[7]) {
                                case 'b': a = a.toString(2); break;
                                case 'c': a = String.fromCharCode(a); break;
                                case 'd': a = parseInt(a); break;
                                case 'e': a = m[6] ? a.toExponential(m[6]) : a.toExponential(); break;
                                case 'f': a = m[6] ? parseFloat(a).toFixed(m[6]) : parseFloat(a); break;
                                case 'o': a = a.toString(8); break;
                                case 's': a = ((a = String(a)) && m[6] ? a.substring(0, m[6]) : a); break;
                                case 'u': a = Math.abs(a); break;
                                case 'x': a = a.toString(16); break;
                                case 'X': a = a.toString(16).toUpperCase(); break;
                        }
                        a = (/[def]/.test(m[7]) && m[2] && a >= 0 ? '+'+ a : a);
                        c = m[3] ? m[3] == '0' ? '0' : m[3].charAt(1) : ' ';
                        x = m[5] - String(a).length - s.length;
                        p = m[5] ? str_repeat(c, x) : '';
                        o.push(s + (m[4] ? a + p : p + a));
                }
                else {
                        throw('Huh ?!');
                }
                f = f.substring(m[0].length);
        }
        return o.join('');
}

  function checkUploadStart() {
    var nbErrors = 0;
    jQuery("#formErrors").hide();
    jQuery("#formErrors li").hide();

    if (jQuery("#albumSelect option:selected").length == 0) {
      jQuery("#formErrors #noAlbum").show();
      nbErrors++;
    }

    var nbFiles = 0;
    if (jQuery("#uploadBoxes").size() == 1) {
      jQuery("input[name^=image_upload]").each(function() {
        if (jQuery(this).val() != "") {
          nbFiles++;
        }
      });
    }
    else {
      nbFiles = jQuery(".uploadifyQueueItem").size();
    }

    if (nbFiles == 0) {
      jQuery("#formErrors #noPhoto").show();
      nbErrors++;
    }

    if (nbErrors != 0) {
      jQuery("#formErrors").show();
      return false;
    }
    else {
      return true;
    }

  }

  function humanReadableFileSize(bytes) {
    var byteSize = Math.round(bytes / 1024 * 100) * .01;
    var suffix = 'KB';

    if (byteSize > 1000) {
      byteSize = Math.round(byteSize *.001 * 100) * .01;
      suffix = 'MB';
    }

    var sizeParts = byteSize.toString().split('.');
    if (sizeParts.length > 1) {
      byteSize = sizeParts[0] + '.' + sizeParts[1].substr(0,2);
    }
    else {
      byteSize = sizeParts[0];
    }

    return byteSize+suffix;
  }

  function fillCategoryListbox(selectId, selectedValue) {
    jQuery.getJSON(
      "ws.php?format=json&method=pwg.categories.getList",
      {
        recursive: true,
        fullname: true,
        format: "json",
      },
      function(data) {
        jQuery.each(
          data.result.categories,
          function(i,category) {
            var selected = null;
            if (category.id == selectedValue) {
              selected = "selected";
            }
            
            jQuery("<option/>")
              .attr("value", category.id)
              .attr("selected", selected)
              .text(category.name)
              .appendTo("#"+selectId)
              ;
          }
        );
      }
    );
  }

  jQuery(".addAlbumOpen").colorbox({
    inline:true,
    href:"#addAlbumForm",
    onComplete:function(){
      jQuery("input[name=category_name]").focus();
    }
  });

  jQuery("#addAlbumForm form").submit(function(){
      jQuery("#categoryNameError").text("");

      jQuery.ajax({
        url: "ws.php?format=json&method=pwg.categories.add",
        data: {
          parent: jQuery("select[name=category_parent] option:selected").val(),
          name: jQuery("input[name=category_name]").val(),
        },
        beforeSend: function() {
          jQuery("#albumCreationLoading").show();
        },
        success:function(html) {
          jQuery("#albumCreationLoading").hide();

          var newAlbum = jQuery.parseJSON(html).result.id;
          jQuery(".addAlbumOpen").colorbox.close();

          jQuery("#albumSelect").find("option").remove();
          fillCategoryListbox("albumSelect", newAlbum);

          jQuery(".albumSelection").show();

          /* we hide the ability to create another album, this is different from the admin upload form */
          /* in Community, it's complicated to refresh the list of parent albums                       */
          jQuery("#linkToCreate").hide();

          return true;
        },
        error:function(XMLHttpRequest, textStatus, errorThrows) {
            jQuery("#albumCreationLoading").hide();
            jQuery("#categoryNameError").text(errorThrows).css("color", "red");
        }
      });

      return false;
  });

  jQuery("#hideErrors").click(function() {
    jQuery("#formErrors").hide();
    return false;
  });

  jQuery("#uploadWarningsSummary a.showInfo").click(function() {
    jQuery("#uploadWarningsSummary").hide();
    jQuery("#uploadWarnings").show();
  });

  jQuery("#showPermissions").click(function() {
    jQuery(this).parent(".showFieldset").hide();
    jQuery("#permissions").show();
  });

  jQuery("#showPhotoProperties").click(function() {
    jQuery(this).parent(".showFieldset").hide();
    jQuery("#photoProperties").show();
    jQuery("input[name=set_photo_properties]").prop('checked', true);
  });

{/literal}
{if $upload_mode eq 'html'}
  {if isset($limit_nb_photos)}
  var limit_nb_photos = {$limit_nb_photos};
  {/if}
{literal}

  function addUploadBox() {
    var uploadBox = '<p class="file"><input type="file" size="60" name="image_upload[]"></p>';
    jQuery(uploadBox).appendTo("#uploadBoxes");

    if (typeof limit_nb_photos != 'undefined') {
      if (jQuery("input[name^=image_upload]").size() >= limit_nb_photos) {
        jQuery("#addUploadBox").hide();
      }
    }
  }

  addUploadBox();

  jQuery("#addUploadBox A").click(function () {
    if (typeof limit_nb_photos != 'undefined') {
      if (jQuery("input[name^=image_upload]").size() >= limit_nb_photos) {
        alert('tu rigoles mon gaillard !');
        return false;
      }
    }

    addUploadBox();
  });

  jQuery("#uploadForm").submit(function() {
    return checkUploadStart();
  });
{/literal}
{elseif $upload_mode eq 'multiple'}

var uploadify_path = '{$uploadify_path}';
var upload_id = '{$upload_id}';
var session_id = '{$session_id}';
var pwg_token = '{$pwg_token}';
var buttonText = "{'Select files'|@translate}";
var sizeLimit = Math.round({$upload_max_filesize} / 1024); /* in KBytes */
var sumQueueFilesize = 0;
  {if isset($limit_storage)}
var limit_storage = {$limit_storage};
  {/if}

{literal}
  jQuery("#uploadify").uploadify({
    'uploader'       : uploadify_path + '/uploadify.php',
    'langFile'       : uploadify_path + '/uploadifyLang_en.js',
    'swf'            : uploadify_path + '/uploadify.swf',
    'checkExisting'  : false,

    buttonCursor     : 'pointer',
    'buttonText'     : buttonText,
    'width'          : 300,
    'cancelImage'    : uploadify_path + '/cancel.png',
    'queueID'        : 'fileQueue',
    'auto'           : false,
    'multi'          : true,
    'fileTypeDesc'   : 'Photo files',
    'fileTypeExts'   : '{/literal}{$uploadify_fileTypeExts}{literal}',
    'fileSizeLimit'  : sizeLimit,
    'progressData'   : 'percentage',
{/literal}
  {if isset($limit_nb_photos)}
    'queueSizeLimit' : {$limit_nb_photos},
  {/if}
{literal}
    requeueErrors   : false,
    'onSelect'       : function(file) {
      console.log('filesize = '+file.size+'bytes');

      if (typeof limit_storage != 'undefined') {
        if (sumQueueFilesize + file.size > limit_storage) {
          jQuery.jGrowl(
            '<p></p>'+sprintf(
              '{/literal}{'File %s too big (%uMB), quota of %uMB exceeded'|@translate}{literal}',
              file.name,
              Math.round(file.size/(1024*1024)),
              limit_storage/(1024*1024)
            ),
            {
              theme:  'error',
              header: 'ERROR',
              life:   4000,
              sticky: false
            }
          );

          jQuery('#uploadify').uploadifyCancel(file.id);
          return false;
        }
        else {
          sumQueueFilesize += file.size;
        }
      }

      jQuery("#fileQueue").show();
    },
    'onCancel' : function(file) {
      console.log('The file ' + file.name + ' was cancelled ('+file.size+')');
    },
    'onQueueComplete'  : function(stats) {
      jQuery("input[name=submit_upload]").click();
    },
    onUploadError: function (file,errorCode,errorMsg,errorString,swfuploadifyQueue) {
      /* uploadify calls the onUploadError trigger when the user cancels a file! */
      /* There no error so we skip it to avoid panic.                            */
      if ("Cancelled" == errorString) {
        return false;
      }

      var msg = file.name+', '+errorString;

      /* Let's put the error message in the form to display once the form is     */
      /* performed, it makes support easier when user can copy/paste the error   */
      /* thrown.                                                                 */
      jQuery("#uploadForm").append('<input type="hidden" name="onUploadError[]" value="'+msg+'">');

      jQuery.jGrowl(
        '<p></p>onUploadError '+msg,
        {
          theme:  'error',
          header: 'ERROR',
          life:   4000,
          sticky: false
        }
      );

      return false;
    },
    onUploadSuccess: function (file,data,response) {
      var data = jQuery.parseJSON(data);
      jQuery("#uploadedPhotos").parent("fieldset").show();

      /* Let's display the thumbnail of the uploaded photo, no need to wait the  */
      /* end of the queue                                                        */
      jQuery("#uploadedPhotos").prepend('<img src="'+data.thumbnail_url+'" class="thumbnail"> ');
    },
    onUploadComplete: function(file,swfuploadifyQueue) {
      var max = parseInt(jQuery("#progressMax").text());
      var next = parseInt(jQuery("#progressCurrent").text())+1;
      var addToProgressBar = 2;
      if (next <= max) {
        jQuery("#progressCurrent").text(next);
      }
      else {
        addToProgressBar = 1;
      }

      jQuery("#progressbar").progressbar({
        value: jQuery("#progressbar").progressbar("option", "value") + addToProgressBar
      });
    }
  });

  jQuery("input[type=button]").click(function() {
    if (!checkUploadStart()) {
      return false;
    }

    jQuery("#uploadify").uploadifySettings(
      'postData',
      {
        'category_id' : jQuery("select[name=category] option:selected").val(),
        'level' : jQuery("select[name=level] option:selected").val(),
        'upload_id' : upload_id,
        'session_id' : session_id,
        'pwg_token' : pwg_token,
      }
    );

    nb_files = jQuery(".uploadifyQueueItem").size();
    jQuery("#progressMax").text(nb_files);
    jQuery("#progressbar").progressbar({max: nb_files*2, value:1});
    jQuery("#progressCurrent").text(1);

    jQuery("#uploadProgress").show();

    jQuery("#uploadify").uploadifyUpload();
  });

{/literal}
{/if}
});
{/footer_script}

{literal}
<style type="text/css">
/*
#photosAddContent form p {
  text-align:left;
}
*/

#photosAddContent FIELDSET {
  width:650px;
  margin:20px auto;
}

#photosAddContent fieldset#photoProperties {padding-bottom:0}
#photosAddContent fieldset#photoProperties p {text-align:left;margin:0 0 1em 0;line-height:20px;}
#photosAddContent fieldset#photoProperties input[type="text"] {width:320px}
#photosAddContent fieldset#photoProperties textarea {width:500px; height:100px}

#photosAddContent P {
  margin:0;
}

#uploadBoxes P {
  margin:0;
  margin-bottom:2px;
  padding:0;
}

#uploadBoxes .file {margin-bottom:5px;text-align:left;}
#uploadBoxes {margin-top:20px;}
#addUploadBox {margin-bottom:2em;}

p#uploadWarningsSummary {text-align:left;margin-bottom:1em;font-size:90%;color:#999;}
p#uploadWarningsSummary .showInfo {position:static;display:inline;padding:1px 6px;margin-left:3px;}
p#uploadWarnings {display:none;text-align:left;margin-bottom:1em;font-size:90%;color:#999;}
p#uploadModeInfos {text-align:left;margin-top:1em;font-size:90%;color:#999;}

#photosAddContent p.showFieldset {text-align:left;margin: 0 auto 10px auto;width: 650px;}

#uploadProgress {width:650px; margin:10px auto;font-size:90%;}
#progressbar {border:1px solid #ccc; background-color:#eee;}
.ui-progressbar-value { background-image: url(admin/themes/default/images/pbar-ani.gif); height:10px;margin:-1px;border:1px solid #E78F08;}

.showInfo {display:block;position:absolute;top:0;right:5px;width:15px;font-style:italic;font-family:"Georgia",serif;background-color:#464646;font-size:0.9em;border-radius:10px;-moz-border-radius:10px;}
.showInfo:hover {cursor:pointer}
.showInfo {color:#fff;background-color:#999; }
.showInfo:hover {color:#fff;border:none;background-color:#333} 
</style>
{/literal}

<div id="photosAddContent">

{if count($setup_errors) > 0}
<div class="errors">
  <ul>
  {foreach from=$setup_errors item=error}
    <li>{$error}</li>
  {/foreach}
  </ul>
</div>
{else}

  {if count($setup_warnings) > 0}
<div class="warnings">
  <ul>
    {foreach from=$setup_warnings item=warning}
    <li>{$warning}</li>
    {/foreach}
  </ul>
  <div class="hideButton" style="text-align:center"><a href="{$hide_warnings_link}">{'Hide'|@translate}</a></div>
</div>
  {/if}


{if !empty($thumbnails)}
<fieldset>
  <legend>{'Uploaded Photos'|@translate}</legend>
  <div>
  {foreach from=$thumbnails item=thumbnail}
    <a href="{$thumbnail.link}"  class="{if isset($thumbnail.lightbox)}colorboxThumb{else}externalLink{/if}">
      <img src="{$thumbnail.src}" alt="{$thumbnail.file}" title="{$thumbnail.title}" class="thumbnail">
    </a>
  {/foreach}
  </div>
</fieldset>
<p style="margin:10px"><a href="{$another_upload_link}">{'Add another set of photos'|@translate}</a></p>
{else}

<div id="formErrors" class="errors" style="display:none">
  <ul>
    <li id="noAlbum">{'Select an album'|@translate}</li>
    <li id="noPhoto">{'Select at least one photo'|@translate}</li>
  </ul>
  <div class="hideButton" style="text-align:center"><a href="#" id="hideErrors">{'Hide'|@translate}</a></div>
</div>

<div style="display:none">
  <div id="addAlbumForm" style="text-align:left;padding:1em;">
    <form>
      {'Parent album'|@translate}<br>
      <select id ="category_parent" name="category_parent">
{if $create_whole_gallery}
        <option value="0">------------</option>
{/if}
        {html_options options=$category_parent_options selected=$category_parent_options_selected}
      </select>

      <br><br>{'Album name'|@translate}<br><input name="category_name" type="text"> <span id="categoryNameError"></span>
      <br><br><br><input type="submit" value="{'Create'|@translate}"> <span id="albumCreationLoading" style="display:none"><img src="themes/default/images/ajax-loader-small.gif"></span>
    </form>
  </div>
</div>

<form id="uploadForm" enctype="multipart/form-data" method="post" action="{$form_action}" class="properties">
{if $upload_mode eq 'multiple'}
    <input name="upload_id" value="{$upload_id}" type="hidden">
{/if}

    <fieldset>
      <legend>{'Drop into album'|@translate}</legend>

      <span class="albumSelection"{if count($category_options) == 0} style="display:none"{/if}>
      <select id="albumSelect" name="category">
        {html_options options=$category_options selected=$category_options_selected}
      </select>
      </span>
{if $create_subcategories}
      <div id="linkToCreate">
      <span class="albumSelection">{'... or '|@translate}</span><a href="#" class="addAlbumOpen" title="{'create a new album'|@translate}">{'create a new album'|@translate}</a>
      </div>
{/if}      
    </fieldset>

    <fieldset>
      <legend>{'Select files'|@translate}</legend>

    <p id="uploadWarningsSummary">{$upload_max_filesize_shorthand}B. {$upload_file_types}. {if isset($max_upload_resolution)}{$max_upload_resolution}Mpx.{/if} {if isset($quota_summary)}{$quota_summary}{/if}
<a class="showInfo" title="{'Learn more'|@translate}">i</a></p>

    <p id="uploadWarnings">
{'Maximum file size: %sB.'|@translate|@sprintf:$upload_max_filesize_shorthand}
{'Allowed file types: %s.'|@translate|@sprintf:$upload_file_types}
  {if isset($max_upload_resolution)}
{'Approximate maximum resolution: %dM pixels (that\'s %dx%d pixels).'|@translate|@sprintf:$max_upload_resolution:$max_upload_width:$max_upload_height}
  {/if}
{$quota_details}
    </p>

{if $upload_mode eq 'html'}
      <div id="uploadBoxes"></div>
      <div id="addUploadBox">
        <a href="javascript:">{'+ Add an upload box'|@translate}</a>
      </div>

    <p id="uploadModeInfos">{'You are using the Browser uploader. Try the <a href="%s">Flash uploader</a> instead.'|@translate|@sprintf:$switch_url}</p>

{elseif $upload_mode eq 'multiple'}
    <div id="uploadify">You've got a problem with your JavaScript</div> 

    <div id="fileQueue" style="display:none"></div>

    <p id="uploadModeInfos">{'You are using the Flash uploader. Problems? Try the <a href="%s">Browser uploader</a> instead.'|@translate|@sprintf:$switch_url}</p>

{/if}
    </fieldset>

    <p class="showFieldset"><a id="showPhotoProperties" href="#">{'Set Photo Properties'|@translate}</a></p>

    <fieldset id="photoProperties" style="display:none">
      <legend>{'Photo Properties'|@translate}</legend>

      <input type="checkbox" name="set_photo_properties" style="display:none">

      <p>
        {'Title'|@translate}<br>
        <input type="text" class="large" name="name" value="">
      </p>

      <p>
        {'Author'|@translate}<br>
        <input type="text" class="large" name="author" value="">
      </p>

      <p>
        {'Description'|@translate}<br>
        <textarea name="description" id="description" class="description" style="margin:0"></textarea>
      </p>

    </fieldset>

{if $upload_mode eq 'html'}
    <p>
      <input class="submit" type="submit" name="submit_upload" value="{'Start Upload'|@translate}">
    </p>
{elseif $upload_mode eq 'multiple'}
    <p style="margin-bottom:1em">
      <input class="submit" type="button" value="{'Start Upload'|@translate}">
      <input type="submit" name="submit_upload" style="display:none">
    </p>
{/if}
</form>

<div id="uploadProgress" style="display:none">
{'Photo %s of %s'|@translate|@sprintf:'<span id="progressCurrent">1</span>':'<span id="progressMax">10</span>'}
<br>
<div id="progressbar"></div>
</div>

<fieldset style="display:none">
  <legend>{'Uploaded Photos'|@translate}</legend>
  <div id="uploadedPhotos"></div>
</fieldset>

{/if} {* empty($thumbnails) *}
{/if} {* $setup_errors *}

</div> <!-- photosAddContent -->

{* Community specific *}
{footer_script}{literal}
jQuery(document).ready(function(){
  jQuery("a.colorboxThumb").colorbox({rel:"colorboxThumb"});

  jQuery("a.externalLink").click(function() {
    window.open($(this).attr("href"));
    return false;
  });
});
{/literal}{/footer_script}
