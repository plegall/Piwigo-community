{literal}
<style>
form p {
  text-align: left;
}
</style>
{/literal}

<form method="post">

  <p>
    <strong>{'Album of user'|translate}</strong>
    <select name="community_user">
      <option value="">--</option>
      {html_options options=$community_user_options selected=$community_user_selected}
    </select>
    <br><em>{'a user can own only one album'|translate}</em>
  </p>

	<p class="formButtons">
		<input type="submit" name="submit" value="{'Save Settings'|@translate}">
	</p>

</form>
