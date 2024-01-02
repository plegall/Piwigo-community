{combine_script id='common' require='jquery' load='footer' path='admin/themes/default/js/common.js'}
{combine_script id='jquery.colorbox' load='footer' require='jquery' path='themes/default/js/plugins/jquery.colorbox.min.js'}
{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_script id='jquery.progressBar' load='async' path='themes/default/js/plugins/jquery.progressbar.min.js'}
{combine_script id='jquery.ajaxmanager' load='async' path='themes/default/js/plugins/jquery.ajaxmanager.js'}
{*
{combine_script id='jquery.tipTip' load='footer' path='themes/default/js/plugins/jquery.tipTip.minified.js'}
*}
{combine_script id='batchManagerGlobal' load='async' require='jquery' path='plugins/community/edit_photos.js'}
{combine_script id='LocalStorageCache' require='jquery' load='footer' path='admin/themes/default/js/LocalStorageCache.js'}

{combine_css path="plugins/community/fontello/css/fontello.css" order=-10}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.{$themeconf.colorscheme}.css"}
{combine_css id='batchManagerGlobal' path="plugins/community/edit_photos.css"}
{combine_css path="plugins/community/edit_photos-{$themeconf.colorscheme}.css"}
{combine_css id='jquery.colorbox' path="themes/default/js/plugins/colorbox/style2/colorbox.css"}

{footer_script}
var lang = {
  Cancel: '{'Cancel'|translate|escape:'javascript'}',
  deleteProgressMessage: "{'Deletion in progress'|translate|escape:'javascript'}",
  syncProgressMessage: "{'Synchronization in progress'|translate|escape:'javascript'}",
  AreYouSure: "{'Are you sure?'|translate|escape:'javascript'}"
};

jQuery(document).ready(function() {
  var tagsCache = new TagsCache({
    serverKey: '{$CACHE_KEYS.tags}',
    serverId: '{$CACHE_KEYS._hash}',
    rootUrl: '{$ROOT_URL}'
  });

  tagsCache.selectize(jQuery('[data-selectize=tags]'), { lang: {
    'Add': '{'Create'|translate}'
  }});
});

var nb_thumbs_page = {$nb_thumbs_page};
var nb_thumbs_set = {$nb_thumbs_set};
var applyOnDetails_pattern = "{'on the %d selected photos'|@translate}";
var all_elements = [{if !empty($all_elements)}{','|@implode:$all_elements}{/if}];

var selectedMessage_pattern = "{'%d of %d photos selected'|@translate}";
var selectedMessage_none = "{'No photo selected, %d photos in current set'|@translate}";
var selectedMessage_all = "{'All %d photos are selected'|@translate}";

$(document).ready(function() {
  function checkPermitAction() {
    var nbSelected = 0;
    if ($("input[name=setSelected]").is(':checked')) {
      nbSelected = nb_thumbs_set;
    }
    else {
      nbSelected = $(".thumbnails input[type=checkbox]").filter(':checked').length;
    }

    if (nbSelected == 0) {
      $("#permitAction").hide();
      $("#forbidAction").show();
    }
    else {
      $("#permitAction").show();
      $("#forbidAction").hide();
    }

    $("#applyOnDetails").text(
      sprintf(
        applyOnDetails_pattern,
        nbSelected
      )
    );

    // display the number of currently selected photos in the "Selection" fieldset
    if (nbSelected == 0) {
      $("#selectedMessage").text(
        sprintf(
          selectedMessage_none,
          nb_thumbs_set
        )
      );
    }
    else if (nbSelected == nb_thumbs_set) {
      $("#selectedMessage").text(
        sprintf(
          selectedMessage_all,
          nb_thumbs_set
        )
      );
    }
    else {
      $("#selectedMessage").text(
        sprintf(
          selectedMessage_pattern,
          nbSelected,
          nb_thumbs_set
        )
      );
    }
  }

  $("[id^=action_]").hide();

  $("select[name=selectAction]").change(function () {
    $("[id^=action_]").hide();

    var action = $(this).prop("value");
    if (action == 'move') {
      action = 'associate';
    }

    $("#action_"+action).show();

    if ($(this).val() != -1) {
      $("#applyActionBlock").show();
    }
    else {
      $("#applyActionBlock").hide();
    }
  });

  $(".wrap1 label").click(function (event) {
    $("input[name=setSelected]").prop('checked', false);

    var li = $(this).closest("li");
    var checkbox = $(this).children("input[type=checkbox]");

    checkbox.triggerHandler("shclick",event);

    if ($(checkbox).is(':checked')) {
      $(li).addClass("thumbSelected");
    }
    else {
      $(li).removeClass('thumbSelected');
    }

    checkPermitAction();
  });

  $("#selectAll").click(function () {
    $("input[name=setSelected]").prop('checked', false);
    selectPageThumbnails();
    checkPermitAction();
    return false;
  });

  function selectPageThumbnails() {
    $(".thumbnails label").each(function() {
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', true).trigger("change");
      $(this).closest("li").addClass("thumbSelected");
    });
  }

  $("#selectNone").click(function () {
    $("input[name=setSelected]").prop('checked', false);

    $(".thumbnails label").each(function() {
      var checkbox = $(this).children("input[type=checkbox]");

      if (jQuery(checkbox).is(':checked')) {
        $(checkbox).prop('checked', false).trigger("change");
      }

      $(this).closest("li").removeClass("thumbSelected");
    });
    checkPermitAction();
    return false;
  });

  $("#selectInvert").click(function () {
    $("input[name=setSelected]").prop('checked', false);

    $(".thumbnails label").each(function() {
      var checkbox = $(this).children("input[type=checkbox]");

      $(checkbox).prop('checked', !$(checkbox).is(':checked')).trigger("change");

      if ($(checkbox).is(':checked')) {
        $(this).closest("li").addClass("thumbSelected");
      }
      else {
        $(this).closest("li").removeClass('thumbSelected');
      }
    });
    checkPermitAction();
    return false;
  });

  $("#selectSet").click(function () {
    selectPageThumbnails();
    $("input[name=setSelected]").prop('checked', true);
    checkPermitAction();
    return false;
  });

  checkPermitAction();

});

{/footer_script}

<div id="batchManagerGlobal">

  <form action="{$F_ACTION}" method="post">
  <input type="hidden" name="start" value="{$START}">
  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

  <fieldset class="edit-photos-filter">
{assign var="username_display" value='<span class="community-edit-user"><i class="icon-user"></i>'|cat:$USERNAME|cat:'</span>'}

{if isset($EDIT_ALBUM)}
  {assign var="edit_album_display" value='<span class="community-edit-album"><i class="icon-sitemap"></i>'|cat:$EDIT_ALBUM|cat:'</span>'}
    <p>{'Photos posted by %s in album %s'|translate:$username_display:$edit_album_display}</p>
{else}
    <p>{'Photos posted by %s'|translate:$username_display}</p>
{/if}
  </fieldset>

  <fieldset>

    <legend>{'Selection'|@translate}</legend>

  {if !empty($thumbnails)}
  <p id="checkActions">
{if $nb_thumbs_set > $nb_thumbs_page}
    <a href="#" id="selectAll">{'The whole page'|@translate}</a>
    <a href="#" id="selectSet">{'The whole set'|@translate}</a>
{else}
    <a href="#" id="selectAll">{'All'|@translate}</a>
{/if}
    <a href="#" id="selectNone">{'None'|@translate}</a>
    <a href="#" id="selectInvert">{'Invert'|@translate}</a>

    <span id="selectedMessage"></span>

    <input type="checkbox" name="setSelected" style="display:none" {if count($selection) == $nb_thumbs_set}checked="checked"{/if}>
  </p>

  <ul class="thumbnails">
    {html_style}
UL.thumbnails SPAN.wrap2{ldelim}
  width: {$thumb_params->max_width()+2}px;
}
UL.thumbnails SPAN.wrap2 {ldelim}
  height: {$thumb_params->max_height()+25}px;
}
    {/html_style}
    {foreach from=$thumbnails item=thumbnail}
    {assign var='isSelected' value=$thumbnail.id|@in_array:$selection}
    <li{if $isSelected} class="thumbSelected"{/if}>
      <span class="wrap1">
        <label class="font-checkbox">
          <span class="icon-check"></span><input type="checkbox" name="selection[]" value="{$thumbnail.id}" {if $isSelected}checked="checked"{/if}>
          <span class="wrap2">
          <div class="actions">
            <a href="{$thumbnail.U_JUMPTO}" target="_blank" class="icon-eye" title="{'jump to photo'|@translate}"></a>
            <a href="{$thumbnail.FILE_SRC}" class="preview-box icon-zoom-square" title="{'Zoom'|@translate}"></a>
          </div>
            {if $thumbnail.level > 0}
            <em class="levelIndicatorB">{'Level %d'|@sprintf:$thumbnail.level|@translate}</em>
            <em class="levelIndicatorF" title="{'Who can see these photos?'|@translate} : ">{'Level %d'|@sprintf:$thumbnail.level|@translate}</em>
            {/if}
            <img src="{$thumbnail.thumb->get_url()}" alt="{$thumbnail.file}" title="{$thumbnail.TITLE|@escape:'html'}" {$thumbnail.thumb->get_size_htm()}>
          </span>
        </label>
      </span>
    </li>
    {/foreach}
  </ul>

  {if !empty($navbar) }
  <div style="clear:both;">

    <div style="float:left">
    {include file='plugins/community/navigation_bar.tpl'|@get_extent:'navbar'}
    </div>

    <div class="thumbnailsActionsNumber">
      <span class="thumbnailsActionsShow" style="font-weight: bold;">{'display'|@translate}</span>
      <a href="{$U_DISPLAY}&amp;display=20">20</a>
      <a href="{$U_DISPLAY}&amp;display=50">50</a>
      <a href="{$U_DISPLAY}&amp;display=100">100</a>
      <a href="{$U_DISPLAY}&amp;display=all">{'all'|@translate}</a>
    </div>
  </div>
  {/if}

  {else}
  <div class="selectionEmptyBlock">{'No photo in the current set.'|@translate}</div>
  {/if}
  </fieldset>

  <fieldset id="action">

    <legend>{'Action'|@translate}</legend>
      <div id="forbidAction"{if count($selection) != 0} style="display:none"{/if}>{'No photo selected, no action possible.'|@translate}</div>
      <div id="permitAction"{if count($selection) == 0} style="display:none"{/if}>
    
    <div class="permitActionListButton">
      <div>
        <select name="selectAction">
          <option value="-1">{'Choose an action'|@translate}</option>
          <option disabled="disabled">------------------</option>
          <option value="delete" class="icon-trash">{'Delete selected photos'|@translate}</option>
          <option value="add_tags">{'Add tags'|@translate}</option>
{if !empty($associated_tags)}
          <option value="del_tags">{'remove tags'|@translate}</option>
{/if}
        </select>
      </div>
      
      <p id="applyActionBlock" style="display:none" class="actionButtons">
        <button id="applyAction" name="submit" type="submit" class="buttonLike">
          <i class="icon-cog-alt"></i> {'Apply action'|translate}
        </button>

        <span id="applyOnDetails"></span>
      </p>
    </div>
    <div class="permitActionItem">
      <!-- delete -->
      <div id="action_delete" class="bulkAction">
      <p><label class="font-checkbox"><span class="icon-check"></span><input type="checkbox" name="confirm_deletion" value="1"> {'Are you sure?'|@translate}</label><span class="errors" style="display:none">{"You need to confirm deletion"|translate}</span></p>
      </div>

      <!-- add_tags -->
      <div id="action_add_tags" class="bulkAction">
        <select data-selectize="tags" data-create="true" placeholder="{'Type in a search term'|translate}" name="add_tags[]" multiple style="width:400px;"></select>
      </div>

      <!-- del_tags -->
      <div id="action_del_tags" class="bulkAction">
  {if !empty($associated_tags)}
        <select data-selectize="tags" name="del_tags[]" multiple style="width:400px;" placeholder="{'Type in a search term'|translate}">
    {foreach from=$associated_tags item=tag}
          <option value="{$tag.id}">{$tag.name}</option>
    {/foreach}
        </select>
  {/if}
      </div>

      <!-- progress bar -->
      <div id="regenerationMsg" class="bulkAction" style="display:none">
        <p id="regenerationText" style="margin-bottom:10px;">Nice placeholder</p>
        <span class="progressBar" id="progressBar"></span>
        <input type="hidden" name="regenerateSuccess" value="0">
        <input type="hidden" name="regenerateError" value="0">
      </div>

      </div> {* .permitActionItem *}
    </div> {* #permitAction *}
  </fieldset>

  </form>

</div> <!-- #batchManagerGlobal -->
