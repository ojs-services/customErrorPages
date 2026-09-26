{**
 * plugins/generic/customErrorPages/templates/settingsForm.tpl
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Settings modal: pick the error page style from a numbered list, each with a
 * short description and a small preview; optional URL for "your own image".
 *}
<script>
	$(function() {ldelim}
		$('#customErrorPagesSettings').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<style>
#customErrorPagesSettings .cep-styles{ display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:.75rem; margin:0 0 1.5rem; padding:0; border:0; }
#customErrorPagesSettings .cep-styles legend{ font-weight:700; margin-bottom:.75rem; }
#customErrorPagesSettings .cep-style{ display:flex; gap:.75rem; align-items:flex-start; padding:.6rem; border:1px solid #ddd; border-radius:6px; cursor:pointer; background:#fff; }
#customErrorPagesSettings .cep-style:hover{ border-color:#999; }
#customErrorPagesSettings .cep-style input{ margin-top:.3rem; flex:0 0 auto; }
#customErrorPagesSettings .cep-style input:checked + .cep-swatch{ outline:3px solid #006798; outline-offset:2px; }
#customErrorPagesSettings .cep-swatch{ flex:0 0 76px; height:52px; border-radius:4px; border:1px solid #ccc; background:#f2f2f2; background-size:cover; background-position:center; position:relative; overflow:hidden; display:flex; align-items:center; justify-content:center; }
#customErrorPagesSettings .cep-swatch span{ position:relative; font:700 11px/1 sans-serif; color:#333; }
#customErrorPagesSettings .cep-swatch.cep-on-dark span{ color:#fff; }
#customErrorPagesSettings .cep-swatch.cep-overlay-dark::before, #customErrorPagesSettings .cep-swatch.cep-overlay-light::before{ content:""; position:absolute; top:0; right:0; bottom:0; left:0; }
#customErrorPagesSettings .cep-swatch.cep-overlay-dark::before{ background:rgba(15,20,30,.62); }
#customErrorPagesSettings .cep-swatch.cep-overlay-light::before{ background:rgba(255,255,255,.74); }
#customErrorPagesSettings .cep-swatch.cep-card span{ background:#fff; padding:4px 7px; border-radius:3px; box-shadow:0 1px 4px rgba(0,0,0,.3); }
#customErrorPagesSettings .cep-style-text{ font-size:.9rem; line-height:1.35; }
#customErrorPagesSettings .cep-style-text strong{ display:block; margin-bottom:.15rem; }
#customErrorPagesSettings .cep-style-text span{ color:#555; }
</style>

<form class="pkp_form" id="customErrorPagesSettings" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="customErrorPagesSettingsNotification"}

	<p class="pkp_help">{translate key="plugins.generic.customErrorPages.settings.help"}</p>

	{fbvFormArea id="customErrorPagesStyle"}
		<fieldset class="cep-styles">
			<legend>{translate key="plugins.generic.customErrorPages.settings.style"}</legend>
			{foreach from=$styles key=styleId item=s}
				<label class="cep-style" for="cepStyle-{$styleId|escape}">
					<input type="radio" name="style" id="cepStyle-{$styleId|escape}" value="{$styleId|escape}"{if $style == $styleId} checked="checked"{/if}>
					<span class="cep-swatch {$s.classes|escape}"{if $s.swatch} style="{$s.swatch|escape}"{/if}><span>404</span></span>
					<span class="cep-style-text">
						<strong>{$s.number}. {translate key=$s.nameKey}</strong>
						<span>{translate key=$s.descKey}</span>
					</span>
				</label>
			{/foreach}
		</fieldset>
		{fbvFormSection}
			{fbvElement type="text" id="backgroundImageUrl" name="backgroundImageUrl" value=$backgroundImageUrl label="plugins.generic.customErrorPages.settings.backgroundImageUrl" maxlength="255"}
			<p class="pkp_help">{translate key="plugins.generic.customErrorPages.settings.backgroundImageUrl.hint"}</p>
		{/fbvFormSection}
	{/fbvFormArea}

	{fbvFormButtons submitText="common.save"}
</form>
