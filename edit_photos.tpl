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
{combine_css path="themes/default/js/plugins/jquery-confirm.min.css"}

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

// Based on add_photos.tpl
// Create album for "move to album" action
<!-- Data to album prefilter -->
function fillCategoryListbox(selectId, selectedValue) {
  jQuery.getJSON(
    rootUrl + "ws.php?format=json&method=pwg.categories.getList",
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

var rootUrl = "{get_absolute_root_url()}";
jQuery("#addAlbumForm form").submit(function(){
    e.preventDefault(); // added to prevent reload

    jQuery("#categoryNameError").text("");

    jQuery.ajax({
      url: rootUrl + "ws.php?format=json&method=pwg.categories.add",
      type:"POST",
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

{/footer_script}

<div id="batchManagerGlobal">

  <form action="{$F_ACTION}" method="post">
  <input type="hidden" name="start" value="{$START}">
  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">

{* show block when user is only permitted to edit photos uploaded by user *}
{* ie. scope permissions are set to false *}
{if !$user_filters.scope.value}
  <fieldset class="edit-photos-filter">
{assign var="username_display" value='<span class="community-edit-user"><i class="icon-user"></i>'|cat:$USERNAME|cat:'</span>'}

{if isset($EDIT_ALBUM)}
  {assign var="edit_album_display" value='<span class="community-edit-album"><i class="icon-sitemap"></i>'|cat:$EDIT_ALBUM|cat:'</span>'}
    <p>{'Photos posted by %s in album %s'|translate:$username_display:$edit_album_display}</p>
{else}
    <p>{'Photos posted by %s'|translate:$username_display}</p>
{/if}
  </fieldset>
{/if}


<!-- Filters -->
{if $user_filters.enable}
<fieldset>
    <legend>{'Filter'|@translate}</legend>

    <div class="filterBlock">
      <ul id="filterList">

      {if $user_filters.scope.value}
        <li id="filter_scope">
          <input type="checkbox" name="filter_scope_use" class="useFilterCheckbox" {if isset($filter.scope)}checked="checked"{/if}>
          <p>{'Scope'|@translate}</p>
          <select name="filter_scope">
            <option value="user" class="{$optionClass}" {if $scope_selected=="user"}selected="selected"{/if}>{'Photos posted by %s'|translate:$USERNAME}</option>
            <option value="all" class="{$optionClass}" {if $scope_selected=="all"}selected="selected"{/if}>{'All photos'|@translate}</option>
          </select>
        </li>
      {/if}

      {if $user_filters.prefilter.value}
        <li id="filter_prefilter" {if !isset($filter.prefilter)}style="display:none"{/if}>
          <input type="checkbox" name="filter_prefilter_use" class="useFilterCheckbox" {if isset($filter.prefilter)}checked="checked"{/if}>
          <p>{'Predefined filter'|@translate}</p>
          <a href="#" class="removeFilter" title="{'remove this filter'|@translate}"><span>[x]</span></a>
          <select name="filter_prefilter">
            {foreach from=$prefilters item=prefilter}
              <option value="{$prefilter.ID}"  class="{$optionClass}" {if isset($filter.prefilter) && $filter.prefilter eq $prefilter.ID}selected="selected"{/if}>{$prefilter.NAME}</option>
            {/foreach}
          </select>
  {if $NB_ORPHANS > 0}
          <a id="delete_orphans" href="#" style="{if !isset($filter.prefilter) or $filter.prefilter ne 'no_album'}display:none{/if}" class="icon-trash">{'Delete %d orphan photos'|translate:$NB_ORPHANS}</a>
  {/if}

          <span id="orphans_deletion" style="display:none">
            <img class="loading" src="themes/default/images/ajax-loader-small.gif">
            <span id="orphans_deleted">0</span>% -
            <span id="orphans_to_delete" data-origin="{$NB_ORPHANS}">{$NB_ORPHANS}</span>
            {'orphans to delete'|translate}
          </span>

          <span id="orphans_deletion_error" class="errors" style="display:none"></span>
        </li>
      {/if}

      {if $user_filters.album.value}
        <li id="filter_category" {if !isset($filter.category)}style="display:none"{/if}>
          <input type="checkbox" name="filter_category_use" class="useFilterCheckbox" {if isset($filter.category)}checked="checked"{/if}>
          <p>{'Album'|@translate}</p>
      <select name="filter_category">
        {html_options options=$category_options selected=$category_options_selected}
      </select>
          <a href="#" class="removeFilter" title="{'remove this filter'|translate}"><span>[x]</span></a>
          <label class="font-checkbox"><span class="icon-check"></span><input type="checkbox" name="filter_category_recursive" {if isset($filter.category_recursive)}checked="checked"{/if}> {'include child albums'|@translate}</label>
        </li>
      {/if}

      {if $user_filters.tags.value}
        <li id="filter_tags" {if !isset($filter.tags)}style="display:none"{/if}>
          <input type="checkbox" name="filter_tags_use" class="useFilterCheckbox" {if isset($filter.tags)}checked="checked"{/if}>
          <p>{'Tags'|@translate}</p>
          <a href="#" class="removeFilter" title="{'remove this filter'|translate}"><span>[x]</span></a>
          <select data-selectize="tags" data-value="{$filter_tags|@json_encode|escape:html}"
            placeholder="{'Type in a search term'|translate}"
            name="filter_tags[]" multiple></select>
          <label class="font-checkbox"><span class="icon-circle-empty"></span><span><input type="radio" name="tag_mode" value="AND" {if !isset($filter.tag_mode) or $filter.tag_mode eq 'AND'}checked="checked"{/if}> {'All tags'|@translate}</span></label>
          <label class="font-checkbox"><span class="icon-circle-empty"></span><span><input type="radio" name="tag_mode" value="OR" {if isset($filter.tag_mode) and $filter.tag_mode eq 'OR'}checked="checked"{/if}> {'Any tag'|@translate}</span></label>
        </li>
      {/if}

      {if $user_filters.q.value}
        <li id="filter_search"{if !isset($filter.search)} style="display:none"{/if}>
          <input type="checkbox" name="filter_search_use" class="useFilterCheckbox"{if isset($filter.search)} checked="checked"{/if}>
          <p>{'Search'|@translate}</p>
          <a href="#" class="removeFilter" title="{'remove this filter'|translate}"><span>[x]</span></a>
          <input name="q" size=40 value="{$filter.search.q|stripslashes|htmlspecialchars}">
          {combine_script id='core.scripts' load='async' path='themes/default/js/scripts.js'}
  {if (isset($no_search_results))}
  <div>{'No results for'|@translate} :
    <em><strong>
    {foreach $no_search_results as $res}
    {if !$res@first} &mdash; {/if}
    {$res}
    {/foreach}
    </strong></em>
  </div>
  {/if}
        </li>
      {/if}
      </ul>

      <div class='noFilter'>{'No filter, add one'|@translate}</div>

      <div class="filterActions">
        <div id="addFilter">
          <div class="addFilter-button icon-plus" onclick="$('.addFilter-dropdown').slideToggle()">{'Add a filter'|@translate}</div>
          <div class="addFilter-dropdown">
            {if $user_filters.scope.value}<a data-value="filter_scope" {if isset($filter.scope)}class="disabled"{/if}>{'Scope'|@translate}</a>{/if}
            {if $user_filters.prefilter.value}<a data-value="filter_prefilter" {if isset($filter.prefilter)}class="disabled"{/if}>{'Predefined filter'|@translate}</a>{/if}
            {if $user_filters.album.value}<a data-value="filter_category" {if isset($filter.category)}class="disabled"{/if}>{'Album'|@translate}</a>{/if}
            {if $user_filters.tags.value}<a data-value="filter_tags" {if isset($filter.tags)}class="disabled"{/if}>{'Tags'|@translate}</a>{/if}
            {if $user_filters.q.value}<a data-value="filter_search"{if isset($filter.search)} class="disabled"{/if}>{'Search'|@translate}</a>{/if}
          </div>
          <a id="removeFilters" class="icon-cancel" style="display: none;">{'Remove all filters'|@translate}</a>
        </div>

        <button id="applyFilter" name="submitFilter" type="submit">
          <i class="icon-arrows-cw"></i> {'Refresh photo set'|@translate}
        </button>
      </div>
    </div>

  </fieldset>
{/if} <!-- filters -->


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
{* hide option if action.value==0 (ie. action disbled); not applicable for del and tags *}
{* hide option scope filter is enabled set to 'all' and action.value==1 *}
          <option value="-1">{'Choose an action'|@translate}</option>
          <option disabled="disabled">------------------</option>
{if !($user_filters.scope.value and $scope_selected=='all' and $user_actions.delete.value<2)}
          <option value="delete" class="icon-trash">{'Delete selected photos'|@translate}</option>
{/if}
{if !($user_filters.scope.value and $scope_selected=='all' and $user_actions.tags.value<2)}
          <option value="add_tags">{'Add tags'|@translate}</option>
  {if !empty($associated_tags)}
          <option value="del_tags">{'remove tags'|@translate}</option>
  {/if}
{/if}
{if $user_actions.move.value>0 and !($user_filters.scope.value and $scope_selected=='all' and $user_actions.move.value<2)}
          <option value="move">{'Move to album'|@translate}</option>
{/if}
{if $user_actions.favorites.value>0 and !($user_filters.scope.value and $scope_selected=='all' and $user_actions.favorites.value<2)}
          <option value="add_fav">{'Add to favorites'|@translate}</option>
  {if isset($filter.prefilter) && $filter.prefilter eq 'favorites'}
          <option value="del_fav">{'Remove from favorites'|@translate}</option>
  {/if}
{/if}
{if $user_actions.download.value>0 and !($user_filters.scope.value and $scope_selected=='all' and $user_actions.download.value<2)}
          <option value="download">{' Download'|@translate}</option>
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

      <!-- move -->
      <div id="action_associate" class="bulkAction">
        <span class="albumSelection"{if count($category_options) == 0} style="display:none"{/if}>
          <select id="albumSelect" name="associate">
            {html_options options=$category_options selected=$category_options_selected}
          </select>
        </span>
        {if $create_subcategories}
        <div id="linkToCreate">
            <a href="#" title="{'create a new album'|@translate}" class="icon-plus addAlbumOpen albumSelection"></a>
        </div>
        {/if}      
      </div>

      <!-- download -->
      <div id="action_download" class="bulkAction">
        <label style="margin-bottom:0"><input type="radio" name="download_type" value="single">&nbsp;{'Download files individually'|@translate}</label><br>
        <label><input type="radio" name="download_type" value="all" checked="checked">&nbsp;{'Download all in ZIP file'|@translate}</label><br>
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


{* Create album form for "move to album" action *}
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
