<div class="titrePage">
  <h2>{'Community'|@translate}</h2>
</div>

<form method="post" name="add_permission" action="{$F_ADD_ACTION}" class="properties">
  <fieldset>
    <legend>{'Add permissions'|@translate}</legend>

    <table>
      <tr>
        <td>{'User'|@translate}</td>
        <td>
          {html_options name="user_options" options=$user_options}
        </td>
      </tr>

      <tr>
        <td>{'Permission level'|@translate}</td>
        <td>
          {html_options name="permission_level_options" options=$permission_level_options}
        </td>
      </tr>
    </table>
    
    <p>
      <input class="submit" type="submit" name="submit_add" value="{'Add'|@translate}" {$TAG_INPUT_ENABLED}/>
    </p>
  </fieldset>
</form>

<table class="table2">
  <tr class="throw">
    <th>{'User'|@translate}</th>
    <th>{'Permission level'|@translate}</th>
    <th>{'Actions'|@translate}</th>
  </tr>
  {if not empty($users)}
  {foreach from=$users item=user name=user_loop}
  <tr class="{if $smarty.foreach.user_loop.index is odd}row1{else}row2{/if}">
    <td>{$user.NAME}</td>
    <td>{$user.PERMISSION_LEVEL}</td>
    <td style="text-align:center;">
      <a href="{$user.U_DELETE}" onclick="return confirm( document.getElementById('btn_delete').title + '\n\n' + '{'Are you sure?'|@translate|@escape:'javascript'}');">
        <img src="{$ROOT_URL}{$themeconf.admin_icon_dir}/delete.png" class="button" style="border:none" id="btn_delete" alt="{'delete'|@translate}" title="{'delete'|@translate}" {$TAG_INPUT_ENABLED}/>
      </a>
    </td>
  </tr>
  {/foreach}
  {/if}
</table>
