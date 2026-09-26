{**
 * plugins/generic/customErrorPages/templates/frontend/notFoundInline.tpl
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * "File not found" for a request that will be shown inside a frame (the HTML
 * galley viewer's ?inline=true, or any iframe): no theme header/footer, which
 * would nest a whole site in the frame. The theme's CSS is not loaded, so the
 * box carries its own styles; logical properties keep it right in RTL too.
 * The link targets _top so it leaves the frame.
 *}
<!DOCTYPE html>
<html lang="{$customErrorLang|escape}" dir="{$customErrorDir|escape}">
<head>
	<meta charset="{$defaultCharset|default:'utf-8'|escape}">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex">
	<title>{translate key=$customErrorKeys.title}</title>
	<style>
		html,body{ margin:0; height:100%; }
		body{ display:flex; align-items:center; justify-content:center; padding:1.5rem; box-sizing:border-box;
			font:16px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,"Noto Sans","Noto Sans Arabic",Tahoma,Arial,sans-serif;
			color:#1f2328; background:#f6f7f9; }
		.error-inline{ max-width:32rem; text-align:center; background:#fff; border:1px solid #d0d7de; border-radius:8px; padding:2rem 1.75rem; }
		.error-inline h1{ margin:0 0 .5rem; font-size:1.75rem; line-height:1.2; }
		.error-inline p{ margin:0 0 1.25rem; color:#424a53; }
		.error-inline a{ display:inline-block; padding:.5rem 1rem; border-radius:6px; background:#0b5cad; color:#fff; text-decoration:none; }
		.error-inline a:hover,.error-inline a:focus{ background:#084a8c; }
		@media (prefers-color-scheme: dark){
			body{ color:#e6edf3; background:#0d1117; }
			.error-inline{ background:#161b22; border-color:#30363d; }
			.error-inline p{ color:#adbac7; }
		}
	</style>
</head>
<body>
	<main class="error-inline" role="alert">
		<h1>{translate key=$customErrorKeys.code}</h1>
		<p>{translate key=$customErrorKeys.body}</p>
		<a href="{$customErrorHome|escape}" target="_top">{translate key=$customErrorKeys.back}</a>
	</main>
</body>
</html>
