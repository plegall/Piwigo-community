{literal}
<style>
.rowSelected {background-color:#C2F5C2 !important}
.comment p {text-align:left; margin:5px 0 0 5px}
.comment table {margin:5px 0 0 0}
.comment table th {padding-right:10px}
</style>
{/literal}

{literal}
<script type="text/javascript">
$(document).ready(function(){

  function checkSelectedRows() {
    $(".checkPhoto").each(function() {
      var row = $(this).parent("tr");
      var checkbox = $(this).children("input[type=checkbox]");

      if ($(checkbox).is(':checked')) {
        $(row).addClass("rowSelected"); 
      }
      else {
        $(row).removeClass("rowSelected"); 
      }
    });
  }


  $(".checkPhoto").click(function(event) {
    if (event.target.type !== 'checkbox') {
      var checkbox = $(this).children("input[type=checkbox]");
      $(checkbox).attr('checked', !$(checkbox).is(':checked'));
    }
    checkSelectedRows();
  });

  $("#selectAll").click(function () {
    $(".checkPhoto input[type=checkbox]").attr('checked', true);
    checkSelectedRows();
    return false;
  });

  $("#selectNone").click(function () {
    $(".checkPhoto input[type=checkbox]").attr('checked', false);
    checkSelectedRows();
    return false;
  });

  $("#selectInvert").click(function () {
    $(".checkPhoto input[type=checkbox]").each(function() {
      $(this).attr('checked', !$(this).is(':checked'));
    });
    checkSelectedRows();
    return false;
  });

});
</script>
{/literal}

<div class="titrePage">
  <h2>{'Pending Photos'|@translate} - {'Community'|@translate}</h2>
</div>

{if !empty($photos) }
<form method="post" action="{$F_ACTION}">
  
  <fieldset>

    <legend>{'Selection'|@translate}</legend>
<table width="99%">
  {foreach from=$photos item=photo name=photo}
  <tr valign="top" class="{if $smarty.foreach.photo.index is odd}row2{else}row1{/if}">
    <td style="width:50px;text-align:center" class="checkPhoto">
      <input type="checkbox" name="photos[]" value="{$photo.ID}" style="z-index:99;display:box;float:left;">
      <img src="{$photo.TN_SRC}" style="margin:0.5em">
    </td>
    <td>
  <div class="comment">
    <p class="commentAction" style="float:left;margin:0.5em 0 0 0.5em"><a href="{$photo.WEBSIZE_SRC}" class="externalLink">{'Zoom'|@translate}</a> &middot; <a href="{$photo.U_EDIT}" class="externalLink">{'Edit'|@translate}</a></p>
    <p class="commentHeader"><strong>{$photo.ADDED_BY}</strong> - <em>{$photo.ADDED_ON}</em></p>
    <table>
      <tr>
        <th>{'Album'|@translate}</th>
        <td>{$photo.ALBUM}</td>
      </tr>
      <tr>
        <th>{'Name'|@translate}</th>
        <td>{$photo.NAME} ({'File'|@translate} {$photo.FILE})</td>
      </tr>
      <tr>
        <th>{'Created on'|@translate}</th>
        <td>{$photo.DATE_CREATION}</td>
      </tr>
      <tr>
        <th>{'Dimensions'|@translate}</th>
        <td>{$photo.DIMENSIONS}</td>
      </tr>
    </table>
  </div>
    </td>
  </tr>
  {/foreach}
</table>

  <p class="checkActions">
    {'Select:'|@translate}
    <a href="#" id="selectAll">{'All'|@translate}</a>,
    <a href="#" id="selectNone">{'None'|@translate}</a>,
    <a href="#" id="selectInvert">{'Invert'|@translate}</a>
  </p>

    </fieldset>

    <fieldset>
      <legend>{'Who can see these photos?'|@translate}</legend>

      <select name="level" size="1">
        {html_options options=$level_options selected=$level_options_selected}
      </select>
    </fieldset>


  <p class="bottomButtons">
    <input class="submit" type="submit" name="validate" value="{'Validate'|@translate}">
    <input class="submit" type="submit" name="reject" value="{'Reject'|@translate}">
  </p>

</form>
{/if}
