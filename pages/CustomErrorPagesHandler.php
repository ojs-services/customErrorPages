<?php
/**
 * @file plugins/generic/customErrorPages/pages/CustomErrorPagesHandler.php
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Renders a themed 404 page. Mounted by CustomErrorPagesPlugin's LoadHandler hook
 * in place of OJS' bare handle404(). The template pulls in the ACTIVE theme's
 * frontend header/footer, so the page matches the rest of the site.
 */

namespace APP\plugins\generic\customErrorPages\pages;

use APP\core\Application;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\plugins\PluginRegistry;

class CustomErrorPagesHandler extends Handler
{
    /**
     * The URL's op segment is arbitrary for an unknown page (e.g. /journal/foo
     * → op "index"; /journal/foo/bar → op "bar"). PKPPageRouter validates the
     * op against get_class_methods(HANDLER_CLASS); the LoadHandler hook rewrote
     * it to "notFound", but __call() also funnels any other op to notFound() so
     * the handler is robust regardless of how it was reached.
     */
    public function __call($op, $arguments)
    {
        // Forward exactly the two arguments an op receives; anything else the
        // caller passed is dropped. notFound() returns nothing.
        $args = isset($arguments[0]) && is_array($arguments[0]) ? $arguments[0] : array();
        $request = isset($arguments[1]) ? $arguments[1] : Application::get()->getRequest();
        $this->notFound($args, $request);
    }

    public function notFound($args, $request)
    {
        // NB: we deliberately do NOT call $this->setupTemplate($request). On a
        // frontend error page it only adds backend/role data (userRoles, workflow
        // stages) that this page doesn't use, and it requires the handler to have
        // been through the router's authorize() step — which is NOT the case when
        // the plugin renders this from its shutdown safety net. TemplateManager
        // (+ the active theme's TemplateManager::display hook) supplies everything
        // the theme header/footer need.
        $templateMgr = TemplateManager::getManager($request);

        // Proper 404 status alongside the rendered body, and never cached by a
        // proxy/CDN: the same URL may exist a minute later.
        if (!headers_sent()) {
            header('HTTP/1.1 404 Not Found');
            header('Cache-Control: no-store');
        }
        self::_pinStatus(404);
        // display() re-sends Cache-Control from its own setting; pin it too.
        $templateMgr->setCacheability(TemplateManager::CACHEABILITY_NO_STORE);

        $plugin = PluginRegistry::getPlugin('generic', 'customerrorpagesplugin');

        // "The article/issue exists, its file does not" gets its own wording and
        // a way back to that article/issue; every other 404 is "page not found".
        $missing = $plugin ? $plugin->fileMissingContext($request) : null;
        $prefix = 'plugins.generic.customErrorPages.';
        if ($missing) {
            $keys = array(
                'title' => $prefix . 'file.title',
                'code'  => $prefix . 'file.code',
                'body'  => $prefix . ($missing['type'] === 'issue' ? 'file.issueBody' : 'file.body'),
                'back'  => $prefix . ($missing['type'] === 'issue' ? 'file.backToIssue' : 'file.backToArticle'),
            );
            $backUrl = $missing['backUrl'];
        } else {
            $keys = array(
                'title' => $prefix . '404.title',
                'code'  => $prefix . '404.code',
                'body'  => $prefix . '404.body',
                'back'  => $prefix . '404.home',
            );
            $backUrl = $request->url(null, 'index');
        }

        $templateMgr->assign(array(
            'pageTitle'       => $keys['title'],
            'customErrorKeys' => $keys,
            'customErrorHome' => $backUrl,
            'customErrorStyle' => $plugin ? $plugin->resolveStyle($request) : array('id' => 'theme', 'classes' => '', 'style' => ''),
        ));

        // A missing file requested for an embed (?inline=… as the HTML galley
        // viewer does, or loaded straight into an iframe) gets a bare box: the
        // theme's header/footer would nest a whole site inside the frame.
        $secFetchDest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? $_SERVER['HTTP_SEC_FETCH_DEST'] : '';
        $inline = $missing && ($request->getUserVar('inline') || $secFetchDest === 'iframe');

        if ($plugin && $inline) {
            $locale = Locale::getLocale();
            $meta = Locale::getMetadata($locale);
            $templateMgr->assign(array(
                'customErrorLang' => str_replace('_', '-', $locale),
                'customErrorDir'  => ($meta && $meta->isRightToLeft()) ? 'rtl' : 'ltr',
            ));
            $templateMgr->display($plugin->getTemplateResource('frontend/notFoundInline.tpl'));
        } elseif ($plugin) {
            $templateMgr->display($plugin->getTemplateResource('frontend/notFound.tpl'));
        } else {
            // Extremely defensive: plugin vanished mid-request → fall back.
            \APP\plugins\generic\customErrorPages\CustomErrorPagesPlugin::notFound();
        }
    }

    /**
     * OJS 3.5's TemplateManager::display() sends the session cookie with
     * header('Set-Cookie: …', false, <status of Laravel's response>), and that
     * status is 200 unless someone set it — so a first visit (a new session,
     * which is every crawler) would get this page as a 200. Give Laravel's
     * response the same status. OJS 3.4 registers no such response, so there
     * is nothing to set.
     */
    private static function _pinStatus($code)
    {
        if (!function_exists('app')) return;
        try {
            $app = app();
            if ($app->bound(\Illuminate\Http\Response::class)) {
                $app->get(\Illuminate\Http\Response::class)->setStatusCode($code);
            }
        } catch (\Throwable $e) {
            // Keep the status sent by header() above.
        }
    }
}
