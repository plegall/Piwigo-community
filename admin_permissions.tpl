{literal}
<style>
form fieldset p {text-align:left;margin:0 0 1.5em 0;line-height:20px;}
</style>
{/literal}

{footer_script}{literal}
$(document).ready(function() {
  $("select[name=who]").click(function () {
    $("[name^=who_]").hide();
    $("[name=who_"+$(this).attr("value")+"]").show();
  });

  function checkWhereOptions() {
    var recursive = $("input[name=recursive]");
    var create = $("input[name=create_subcategories]");

    if ($("select[name=category] option:selected").val() == 0) {
      $(recursive).attr("disabled", true);
      $(recursive).attr('checked', true);
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
});
{/literal}{/footer_script}


<div class="titrePage">
  <h2>{'Upload Permissions'|@translate} - {'Community'|@translate}</h2>
</div>

<a id="displayForm" href="#">{'Add a permission'|@translate}</a>

<form method="post" name="add_permission" action="{$F_ADD_ACTION}" class="properties" style="display:none">
  <fieldset>
    <legend>{'Add a permission'|@translate}</legend>

    <p>
      <strong>{'Who?'|@translate}</strong>
      <br>
      <select name="who">
        <option value="any_visitor">{'any visitor'|@translate}</option>
        <option value="any_registered_user">{'any registered user'|@translate}</option>
        <option value="user">{'a specific user'|@translate}</option>
        <option value="group">{'a group'|@translate}</option>
      </select>

      <select name="who_user" style="display:none">
{html_options options=$user_options selected=$user_options_selected}
      </select>

      <select name="who_group" style="display:none">
{html_options options=$group_options selected=$group_options_selected}
      </select>
    </p>

    <p>
      <strong>{'Where?'|@translate}</strong>
      <br>
      <select class="categoryDropDown" name="category">
        <option value="0">{'The whole gallery'|@translate}</option>
        <option disabled="disabled">------------</option>
        {html_options options=$category_options selected=$category_options_selected}
      </select>
      <br>
      <label><input type="checkbox" name="recursive" checked="checked"> {'Apply to sub-albums'|@translate}</label>
      <br>
      <label><input type="checkbox" name="create_subcategories"> {'ability to create sub-albums'|@translate}</label>
    </p>

    <p>
      <strong>{'Which level of trust?'|@translate}</strong>
      <br><label><input type="radio" name="moderate" value="true" checked="checked"> <em>{'low trust'|@translate}</em> : {'uploaded photos must be validated by an administrator'|@translate}</label>
      <br><label><input type="radio" name="moderate" value="false"> <em>{'high trust'|@translate}</em> : {'uploaded photos are directly displayed in the gallery'|@translate}</label>
    </p>
    
    <p style="margin:0;">
      <input class="submit" type="submit" name="submit_add" value="{'Add'|@translate}"/>
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
  <tr class="{if $smarty.foreach.permission_loop.index is odd}row1{else}row2{/if}">
    <td>{$permission.WHO}</td>
    <td>{$permission.WHERE}</td>
    <td>
      <span title="{$permission.TRUST_TOOLTIP}">{$permission.TRUST}</span>
    {if $permission.RECURSIVE}
, <span title="{$permission.RECURSIVE_TOOLTIP}">{'sub-albums'|@translate}</span>
    {/if}
    {if $permission.CREATE_SUBCATEGORIES}
, {'sub-albums creation'|@translate}
    {/if}
    </td>
    <td style="text-align:center;">
      <a href="{$permission.U_DELETE}" onclick="return confirm( document.getElementById('btn_delete').title + '\n\n' + '{'Are you sure?'|@translate|@escape:'javascript'}');">
        <img src="{$ROOT_URL}{$themeconf.admin_icon_dir}/delete.png" class="button" style="border:none" id="btn_delete" alt="{'delete'|@translate}" title="{'delete'|@translate}" />
      </a>
    </td>
  </tr>
  {/foreach}
{/if}
</table>
