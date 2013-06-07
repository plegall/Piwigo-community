{literal}
<style>
form p {text-align:left;}
.subOption {margin-left:2em; margin-bottom:20px;}
</style>
{/literal}

{literal}
<script type="text/javascript">
jQuery(document).ready(function() {
  jQuery("input[name=user_albums]").change(function() {
    if (jQuery(this).is(":checked")) {
      jQuery("#userAlbumsLocation").show();
    }
    else {
      jQuery("#userAlbumsLocation").hide();
    }
  });
});
</script>
{/literal}

<div class="titrePage">
  <h2>{'Configuration'|@translate} - {'Community'|@translate}</h2>
</div>

<form method="post" action="{$F_ACTION}">

  <p><label><input type="checkbox" name="user_albums"{if $user_albums} checked="checked"{/if}> <strong>{'User albums'|@translate}</strong> : <em>{'Piwigo automatically creates an album for each user, on first connection'|@translate}</em></label></p>

  <p class="subOption"{if !$user_albums} style="display:none;"{/if} id="userAlbumsLocation">
    <strong>{'Where should Piwigo create user albums?'|@translate}</strong>
    <br>
    <select class="categoryDropDown" name="user_albums_parent">
      <option value="0" {if not isset($category_options_selected)}selected="selected"{/if}>{'Gallery root'|@translate}</option>
      <option disabled="disabled">------------</option>
      {html_options options=$category_options selected=$category_options_selected}
    </select>
  </p>

	<p class="formButtons">
		<input type="submit" name="submit" value="{'Save Settings'|@translate}">
	</p>

</form>
