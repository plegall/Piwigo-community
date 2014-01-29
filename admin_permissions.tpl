{combine_script id='jquery.ui.slider' require='jquery.ui' load='footer' path='themes/default/js/ui/minified/jquery.ui.slider.min.js'}
{combine_css path="themes/default/js/ui/theme/jquery.ui.slider.css"}

{combine_script id='common' load='footer' path='admin/themes/default/js/common.js'}

{literal}
<style>
form fieldset p {text-align:left;margin:0 0 1.5em 0;line-height:20px;}
.permissionActions {text-align:center;height:20px}
.permissionActions a:hover {border:none}
.permissionActions img {margin-bottom:-2px}
.rowSelected {background-color:#C2F5C2 !important}
#community_nb_photos, #community_storage {width:400px; display:inline-block; margin-right:10px;}
</style>
{/literal}

{footer_script}{literal}

$(document).ready(function() {
  $("select[name=who]").change(function () {
    $("[name^=who_]").hide();
    $("[name=who_"+$(this).prop("value")+"]").show();
    checkWhoOptions();
  });

  function checkWhoOptions() {
    if ('any_visitor' == $("select[name=who] option:selected").val()) {
      $("#userAlbumOption").attr("disabled", true);
      $("#userAlbumInfo").hide();

      if (-1 == $("select[name=category] option:selected").val()) {
        $("select[name=category]").val("0");
        checkWhereOptions();
      }
    }
    else {
      $("#userAlbumOption").attr("disabled", false);
      $("#userAlbumInfo").show();
    }
  }
  checkWhoOptions();

  function checkWhereOptions() {
    var recursive = $("input[name=recursive]");
    var create = $("input[name=create_subcategories]");

    if ($("select[name=category] option:selected").val() == 0) {
      $(recursive).attr("disabled", true);
      $(recursive).attr('checked', true);
    }
    else if ($("select[name=category] option:selected").val() == -1) {
      /* user upload only */
      $(recursive).attr("disabled", true).attr('checked', false);
      $(create).attr("disabled", true).attr('checked', false);
    }
    else {
      $(recursive).removeAttr("disabled");
    }

    if (!$(recursive).is(':checked')) {
      $(create).attr('checked', false);
      $(create).attr("disabled", true);
    }
    else {
      $(create).removeAttr("disabled");
    }
  }

  checkWhereOptions();

  $("select[name=category]").change(function() {
    checkWhereOptions();
  });

  $("input[name=recursive]").change(function() {
    checkWhereOptions();
  });

  $("#displayForm").click(function() {
    $("[name=add_permission]").show();
    $(this).hide();
    return false;
  });

  /* ∞ */
  /**
   * find the key from a value in the startStopValues array
   */
  function getSliderKeyFromValue(value, values) {
    for (var key in values) {
      if (values[key] == value) {
        return key;
      }
    }
    return 0;
  }

  var nbPhotosValues = [5,10,20,50,100,500,1000,5000,-1];

  function getNbPhotosInfoFromIdx(idx) {
    if (idx == nbPhotosValues.length - 1) {
      return "{/literal}{'no limit'|@translate}{literal}";
    }

    return sprintf(
      "{/literal}{'up to %d photos (for each user)'|@translate}{literal}",
      nbPhotosValues[idx]
    );
  }

  /* init nb_photos info span */
  var nbPhotos_init = getSliderKeyFromValue(jQuery('input[name=nb_photos]').val(), nbPhotosValues);

  jQuery("#community_nb_photos_info").html(getNbPhotosInfoFromIdx(nbPhotos_init));

  jQuery("#community_nb_photos").slider({
    range: "min",
    min: 0,
    max: nbPhotosValues.length - 1,
    value: nbPhotos_init,
    slide: function( event, ui ) {
      jQuery("#community_nb_photos_info").html(getNbPhotosInfoFromIdx(ui.value));
    },
    stop: function( event, ui ) {
      jQuery("input[name=nb_photos]").val(nbPhotosValues[ui.value]);
    }
  });

  var storageValues = [10,50,100,200,500,1000,5000,-1];

  function getStorageInfoFromIdx(idx) {
    if (idx == storageValues.length - 1) {
      return "{/literal}{'no limit'|@translate}{literal}";
    }

    return sprintf(
      "{/literal}{'up to %dMB (for each user)'|@translate}{literal}",
      storageValues[idx]
    );
  }

  /* init storage info span */
  var storage_init = getSliderKeyFromValue(jQuery('input[name=storage]').val(), storageValues);

  jQuery("#community_storage_info").html(getStorageInfoFromIdx(storage_init));

  jQuery("#community_storage").slider({
    range: "min",
    min: 0,
    max: storageValues.length - 1,
    value: storage_init,
    slide: function( event, ui ) {
      jQuery("#community_storage_info").html(getStorageInfoFromIdx(ui.value));
    },
    stop: function( event, ui ) {
      jQuery("input[name=storage]").val(storageValues[ui.value]);
    }
  });

});
{/literal}{/footer_script}


<div class="titrePage">
  <h2>{'Upload Permissions'|@translate} - {'Community'|@translate}</h2>
</div>

{if not isset($edit)}
<a id="displayForm" href="#">{'Add a permission'|@translate}</a>
{/if}

<form method="post" name="add_permission" action="{$F_ADD_ACTION}" class="properties" {if not isset($edit)}style="display:none"{/if}>
  <fieldset>
    <legend>{if isset($edit)}{'Edit a permission'|@translate}{else}{'Add a permission'|@translate}{/if}</legend>

    <p>
      <strong>{'Who?'|@translate}</strong>
      <br>
      <select name="who">
{html_options options=$who_options selected=$who_options_selected}
      </select>

      <select name="who_user" {if not isset($user_options_selected)}style="display:none"{/if}>
{html_options options=$user_options selected=$user_options_selected}
      </select>

      <select name="who_group" {if not isset($group_options_selected)}style="display:none"{/if}>
{html_options options=$group_options selected=$group_options_selected}
      </select>
    </p>

    <p>
      <strong>{'Where?'|@translate}</strong> {if $community_conf.user_albums}<em id="userAlbumInfo">{'(in addition to user album)'|@translate}</em>{/if}
      <br>
      <select class="categoryDropDown" name="category">
{if $community_conf.user_albums}
        <option value="-1"{if $user_album_selected} selected="selected"{/if} id="userAlbumOption">{'User album only'|@translate}</option>
{/if}
        <option value="0"{if $whole_gallery_selected} selected="selected"{/if}>{'The whole gallery'|@translate}</option>
        <option disabled="disabled">------------</option>
        {html_options options=$category_options selected=$category_options_selected}
      </select>
      <br>
      <label><input type="checkbox" name="recursive" {if $recursive}checked="checked"{/if}> {'Apply to sub-albums'|@translate}</label>
      <br>
      <label><input type="checkbox" name="create_subcategories" {if $create_subcategories}checked="checked"{/if}> {'ability to create sub-albums'|@translate}</label>
    </p>

    <p>
      <strong>{'Which level of trust?'|@translate}</strong>
      <br><label><input type="radio" name="moderated" value="true" {if $moderated}checked="checked"{/if}> <em>{'low trust'|@translate}</em> : {'uploaded photos must be validated by an administrator'|@translate}</label>
      <br><label><input type="radio" name="moderated" value="false" {if not $moderated}checked="checked"{/if}> <em>{'high trust'|@translate}</em> : {'uploaded photos are directly displayed in the gallery'|@translate}</label>
    </p>

    <p style="margin-bottom:0">
      <strong>{'How many photos?'|@translate}</strong>
    </p>
    <div id="community_nb_photos"></div>
    <span id="community_nb_photos_info">{'no limit'|@translate}</span>
    <input type="hidden" name="nb_photos" value="{$nb_photos}">

    <p style="margin-top:1.5em;margin-bottom:0;">
      <strong>{'How much disk space?'|@translate}</strong>
    </p>
    <div id="community_storage"></div>
    <span id="community_storage_info">{'no limit'|@translate}</span>
    <input type="hidden" name="storage" value="{$storage}">

    {if isset($edit)}
      <input type="hidden" name="edit" value="{$edit}">
    {/if}
    
    <p style="margin-top:1.5em;">
      <input class="submit" type="submit" name="submit_add" value="{if isset($edit)}{'Submit'|@translate}{else}{'Add'|@translate}{/if}"/>
      <a href="{$F_ADD_ACTION}">{'Cancel'|@translate}</a>
    </p>
  </fieldset>
</form>

<table class="table2" style="margin:15px auto;">
  <tr class="throw">
    <th>{'Who?'|@translate}</th>
    <th>{'Where?'|@translate}</th>
    <th>{'Options'|@translate}</th>
    <th>{'Actions'|@translate}</th>
  </tr>
{if not empty($permissions)}
  {foreach from=$permissions item=permission name=permission_loop}
  <tr class="{if $smarty.foreach.permission_loop.index is odd}row1{else}row2{/if}{if $permission.HIGHLIGHT} rowSelected{/if}">
    <td>{$permission.WHO}</td>
    <td>{$permission.WHERE}</td>
    <td>
      <span title="{$permission.TRUST_TOOLTIP}">{$permission.TRUST}</span>{if $permission.RECURSIVE},
<span title="{$permission.RECURSIVE_TOOLTIP}">{'sub-albums'|@translate}</span>{/if}{if $permission.NB_PHOTOS},
<span title="{$permission.NB_PHOTOS_TOOLTIP}">{'%d photos'|@translate|sprintf:$permission.NB_PHOTOS}</span>{/if}{if $permission.STORAGE},
<span title="{$permission.STORAGE_TOOLTIP}">{$permission.STORAGE}MB</span>{/if}
    {if $permission.CREATE_SUBCATEGORIES}
, {'sub-albums creation'|@translate}
    {/if}
    </td>
    <td class="permissionActions">
      <a href="{$permission.U_EDIT}">
        <img src="{$ROOT_URL}{$themeconf.admin_icon_dir}/edit_s.png" alt="{'edit'|@translate}" title="{'edit'|@translate}" />
      </a>
      <a href="{$permission.U_DELETE}" onclick="return confirm( document.getElementById('btn_delete').title + '\n\n' + '{'Are you sure?'|@translate|@escape:'javascript'}');">
        <img src="{$ROOT_URL}{$themeconf.admin_icon_dir}/delete.png" id="btn_delete" alt="{'delete'|@translate}" title="{'Delete permission'|@translate}" />
      </a>
    </td>
  </tr>
  {/foreach}
{/if}
</table>
