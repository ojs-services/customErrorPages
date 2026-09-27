{**
 * plugins/generic/customErrorPages/templates/frontend/notFound.tpl
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Themed 404 page. Includes the ACTIVE theme's frontend header + footer, so it
 * inherits the site's look. The block below is class-based; a small inline
 * <style> gives a neutral centred fallback for any theme, and a theme can fully
 * restyle it (e.g. Pampas turns it into a full-bleed hero with a floating card
 * via `body.pampas .error-hero`). pampasHideStrip suppresses Pampas' own page
 * nameplate — harmless / ignored on other themes.
 *}
{capture assign="pageTitle"}{translate key=$customErrorKeys.title}{/capture}
{assign var="pampasHideStrip" value=true}
{include file="frontend/components/header.tpl" pageTitleTranslated=$pageTitle}

<section class="error-hero cep-style-{$customErrorStyle.id|escape} {$customErrorStyle.classes|escape}"{if $customErrorStyle.style} style="{$customErrorStyle.style|escape}"{/if}>
	<div class="error-hero-inner">
		<div class="error-card">
			<h1 class="error-title">{translate key=$customErrorKeys.code}</h1>
			<p class="error-msg">{translate key=$customErrorKeys.body}</p>
			<a class="btn btn-primary error-back" href="{$customErrorHome|escape}">{translate key=$customErrorKeys.back}</a>
		</div>
	</div>
</section>

{* Neutral fallback — centred on any theme. Every rule is scoped under
   .error-hero so it outranks a theme's generic content rules (e.g. the default
   theme's `.pkp_structure_main p { margin: … 0 }`, which pushed the text off
   centre). A theme may still restyle it with a more specific selector
   (body.<theme> .error-hero …). *}
<style>
.error-hero{ padding:clamp(2.5rem,7vw,6rem) 1rem; background-size:cover; background-position:center; }
.error-hero .error-hero-inner{ max-width:600px; margin:0 auto; }
.error-hero .error-card{ text-align:center; }
.error-hero .error-title{ font-weight:800; line-height:1; font-size:clamp(2.5rem,8vw,4.5rem); margin:0 0 .5em; }
.error-hero .error-msg{ font-size:1.075rem; line-height:1.6; opacity:.8; margin:0 auto 1.75rem; max-width:46ch; }
.error-hero .error-back{ display:inline-block; padding:.65em 1.6em; border:2px solid currentColor; border-radius:6px; font-weight:600; line-height:1.2; text-decoration:none; }
/* Styles picked in the plugin settings (CustomErrorPagesPlugin::STYLES). */
.error-hero.cep-image, .error-hero.cep-colour{ position:relative; }
.error-hero.cep-overlay-dark::before, .error-hero.cep-overlay-light::before{ content:""; position:absolute; top:0; right:0; bottom:0; left:0; }
.error-hero.cep-overlay-dark::before{ background:rgba(15,20,30,.62); }
.error-hero.cep-overlay-light::before{ background:rgba(255,255,255,.74); }
.error-hero.cep-overlay-dark .error-hero-inner, .error-hero.cep-overlay-light .error-hero-inner{ position:relative; }
.error-hero.cep-on-dark .error-title, .error-hero.cep-on-dark .error-msg, .error-hero.cep-on-dark .error-back{ color:#fff; }
.error-hero.cep-on-dark .error-msg{ opacity:.92; }
.error-hero.cep-on-light .error-title{ color:#1f2328; }
.error-hero.cep-on-light .error-msg{ color:#424a53; opacity:1; }
.error-hero.cep-card .error-card{ background:#fff; color:#1f2328; border-radius:12px; padding:2.75rem 2rem; box-shadow:0 12px 32px rgba(0,0,0,.2); }
.error-hero.cep-card .error-title{ color:#1f2328; }
/* The looks without a card put the text straight on their background. A theme
   that draws its own card (Pampas: a white card on the right) must not keep it
   there — white text on a white card is invisible — so drop it and centre. */
.error-hero.cep-colour:not(.cep-card) .error-hero-inner, .error-hero.cep-image:not(.cep-card) .error-hero-inner{ justify-content:center; }
.error-hero.cep-colour:not(.cep-card) .error-card, .error-hero.cep-image:not(.cep-card) .error-card{ background:none; border:0; box-shadow:none; text-align:center; }
.error-hero.cep-colour:not(.cep-card) .error-msg, .error-hero.cep-image:not(.cep-card) .error-msg{ margin-left:auto; margin-right:auto; }
</style>

{include file="frontend/components/footer.tpl"}
