<?php
/**
 * @file plugins/generic/customErrorPages/CustomErrorPagesPlugin.php
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Custom Error Pages — generic plugin for OJS 3.4 and 3.5 (one codebase;
 * where the two differ, the code checks what the running OJS offers).
 *
 * OJS renders "page not found" via Dispatcher::handle404() → fatalError(),
 * which prints a bare "<h1>404 Not Found</h1>" and dies, bypassing the theme
 * entirely. This plugin hooks the router's LoadHandler event (LAST, so every
 * real handler and other plugins get first refusal) and, when a request maps
 * to no handler at all, serves a proper 404 rendered with the ACTIVE theme's
 * header/footer — so the error page matches the rest of the site on any theme.
 */

namespace APP\plugins\generic\customErrorPages;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPPageRouter;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\plugins\GenericPlugin;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;

class CustomErrorPagesPlugin extends GenericPlugin
{
    /** Must equal <release> in version.xml (checked by the release tests). */
    const PLUGIN_VERSION = '3.0.0.2';

    /** OJS' bare 404 body, exactly as fatalError() echoes it (22 bytes). */
    const BARE_404_BODY = '<h1>404 Not Found</h1>';

    /** Our output buffer flushes itself past this size, so it never holds a whole download. */
    const BUFFER_CHUNK_SIZE = 16384;

    /** Longest background image URL that is stored or emitted. */
    const MAX_URL_LENGTH = 255;

    /** @var string|null OJS root cwd, captured while healthy for the shutdown net. */
    private static $_rootDir = null;

    /**
     * @var bool Buffer + shutdown net installed for this request. Static because
     * the plugin list instantiates (and register()s) every plugin a second time.
     */
    private static $_netInstalled = false;

    /** @var int|null ob_get_level() right after OUR ob_start(), null once released. */
    private static $_bufferLevel = null;

    /** @var bool A file download started: the shutdown net must not touch this response. */
    private static $_netDisabled = false;

    /** @var bool The install stamp has been checked in this request. */
    private static $_stampChecked = false;

    /** @var bool This request is an issue galley download whose file is gone. */
    private static $_issueFileMissing = false;

    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        if ($success) $this->_checkInstallStamp();

        $enabled = $success && $this->getEnabled($mainContextId);
        // Site-scope requests (the site home and its pages, /index.php/index/…)
        // carry NO journal context, so getEnabled() looks at the site scope and
        // says "off" even when every journal has us switched on. Fall back to
        // "enabled for at least one journal" when there is no journal context.
        // (A URL whose journal path does not exist never reaches us on OJS 3.4+:
        // finding the enabled generic plugins resolves the journal first, and an
        // unknown path is a 404 right there — before any generic plugin loads.)
        if ($success && !$enabled && !$this->getCurrentContextId()) {
            $enabled = $this->_enabledForAnyContext();
        }

        if ($enabled) {
            // Make our own locale strings available to the 404 template.
            $this->addLocaleData();
            // Run LAST: only claim a request that no real handler — core,
            // app, lib or another plugin — has taken. See loadHandler().
            Hook::add('LoadHandler', array($this, 'loadHandler'), Hook::SEQUENCE_LAST);

            $this->_installNet();
        }
        return $success;
    }

    /**
     * Safety net for 404s that only surface DEEP inside a handler — a valid op
     * with missing data (e.g. /article/view/<deleted-id>, which
     * ArticleHandler::view resolves then rejects with its own handle404()).
     * Those bypass LoadHandler and reach Dispatcher::handle404() → fatalError()'s
     * bare "<h1>404 Not Found</h1>". We buffer the response and, at shutdown,
     * swap that bare body for the themed page. (A shutdown function may render
     * Smarty; an ob_start *callback* may not call ob_start, which Smarty needs —
     * hence a shutdown fn.)
     *
     * Installed at most once per request, and only for PAGE requests: API and
     * component responses (JSON, file downloads) are never ours to rewrite.
     */
    private function _installNet()
    {
        if (self::$_netInstalled || PHP_SAPI === 'cli') return;
        $request = Application::get()->getRequest();
        // The dispatcher picks the router BEFORE it loads generic plugins, so
        // this is already set on every web request.
        if (!$request || !($request->getRouter() instanceof PKPPageRouter)) return;
        self::$_netInstalled = true;

        // Capture the OJS root cwd now (healthy request). PHP resets cwd before
        // shutdown functions run, which breaks the relative file reads the theme
        // header does (XML/menu streams) — we chdir back.
        self::$_rootDir = getcwd();

        // Chunked: a response larger than the chunk flushes itself instead of
        // piling up in memory. The bare 404 body (22 bytes) always stays put.
        ob_start(null, self::BUFFER_CHUNK_SIZE);
        self::$_bufferLevel = ob_get_level();
        register_shutdown_function(array($this, 'catchBareNotFound'));

        // Chunking is not enough for downloads: PKPFileService::download()
        // fpassthru()s the file, which PHP hands to the output layer as ONE
        // write, so any open buffer would swallow the whole file. Both download
        // paths fire a hook before sending their first header — drop out there.
        Hook::add('File::download', array($this, 'releaseBufferForDownload'));
        Hook::add('FileManager::downloadFile', array($this, 'releaseBufferForDownload'));

        // Normal sequence: runs before htmlArticleGalley's LATE callback, which
        // takes the download over before the core's own disk check and throws
        // on a missing file (a 500, not a 404).
        Hook::add('ArticleHandler::download', array($this, 'checkArticleFileOnDisk'));
    }

    /**
     * ArticleHandler::download callback: the same check the core makes right
     * after this hook (ArticleHandler::download(): submissionFile → fs->has()),
     * made early enough that a plugin taking over the download cannot trip on
     * a missing file first. A galley file that is gone → handle404(); in every
     * other case → false, nothing touched.
     * @param $args array [$article, &$galley, &$fileId]
     */
    public function checkArticleFileOnDisk($hookName, $args)
    {
        $fileId = $args[2];
        if (!$fileId) return false;
        $submissionFile = Repo::submissionFile()->get((int) $fileId);
        if (!$submissionFile) return false;
        if (!Services::get('file')->fs->has($submissionFile->getData('path'))) {
            self::notFound();   // does not return
        }
        return false;
    }

    /**
     * File::download / FileManager::downloadFile callback: a file is about to be
     * streamed. Close our buffer if it is still the innermost one (anything
     * opened above it is someone else's, and is left alone), and switch the
     * shutdown net off for this response. Returns false: the core carries on
     * exactly as it would without us.
     */
    public function releaseBufferForDownload($hookName, $args)
    {
        // An issue galley whose file is gone: FileManager::downloadByPath() runs
        // this hook BEFORE its is_readable() check and then just returns false,
        // so the core answers with an empty 200. Answer 404 instead — while our
        // buffer and the shutdown net are still in place to theme it.
        if ($hookName === 'FileManager::downloadFile' && !is_readable($args[0])) {
            $request = Application::get()->getRequest();
            if ($request->getRequestedPage() === 'issue' && $request->getRequestedOp() === 'download') {
                self::$_issueFileMissing = true;
                self::notFound();   // does not return
            }
        }

        self::$_netDisabled = true;
        if (self::$_bufferLevel !== null && ob_get_level() === self::$_bufferLevel) {
            ob_end_flush();
            self::$_bufferLevel = null;
        }
        return false;
    }

    /**
     * One-off repairs after the plugin's files change (copied in by hand, or
     * upgraded). A site-level `schemaStamp` setting remembers the release they
     * were last done for; while it matches PLUGIN_VERSION this costs one cached
     * plugin-setting read and no query.
     */
    private function _checkInstallStamp()
    {
        if (self::$_stampChecked) return;
        self::$_stampChecked = true;
        if (!Config::getVar('general', 'installed')) return;
        $release = self::PLUGIN_VERSION;
        if ($this->getSetting(self::siteId(), 'schemaStamp') === $release) return;
        try {
            $this->_healSitewide();
            if ($this->getSetting(self::siteId(), 'enabledAnywhere') === null) {
                $this->_recomputeEnabledAnywhere();
            }
            $this->updateSetting(self::siteId(), 'schemaStamp', $release, 'string');
        } catch (\Throwable $e) {
            // Never let a repair break a request; the next one retries.
        }
    }

    /**
     * versions.sitewide decides whether OJS loads us on site-scope requests
     * (VersionDAO::getCurrentProducts()), and it is read from the database, not
     * from version.xml. A row written before <sitewide> existed stays at 0 when
     * newer files are copied over it, and the core never revisits an existing
     * row. Flip the flag on that same row — same version number, so
     * insertVersion() takes its UPDATE branch, which writes back the row's own
     * current / class name / lazy_load and leaves date_installed alone. No new
     * version row is written: upgrades stay the plugin manager's business.
     */
    private function _healSitewide()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
        $productType = 'plugins.' . $this->getCategory();
        $product = basename($this->getPluginPath());
        $current = $versionDao->getCurrentVersion($productType, $product);
        if (!$current || $current->getSitewide()) return;
        // insertVersion() compares with the most recently installed row; only
        // when that is this very row does it UPDATE instead of inserting.
        $history = $versionDao->getVersionHistory($productType, $product);
        $latest = array_shift($history);
        if (!$latest || $latest->compare($current) != 0) return;
        $current->setSitewide(true);
        $versionDao->insertVersion($current, true);
    }

    /**
     * Is the plugin switched on for at least one journal? Used only on
     * site-scope requests (see register()), where the per-journal "enabled"
     * setting is invisible. Answered from the site-level `enabledAnywhere`
     * setting, which setEnabled() keeps current; only an install that predates
     * it walks the journals, once.
     */
    private function _enabledForAnyContext()
    {
        $cached = $this->getSetting(self::siteId(), 'enabledAnywhere');
        if ($cached !== null) return (bool) $cached;
        try {
            return $this->_recomputeEnabledAnywhere();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Keep `enabledAnywhere` current whenever a journal switches us on or off.
     * NB: deleting a journal removes its plugin settings without calling this,
     * so the cache may stay "on" until the next toggle — the worst case is a
     * themed 404 on site-scope URLs, never a broken page.
     */
    public function setEnabled($enabled)
    {
        parent::setEnabled($enabled);
        try {
            $this->_recomputeEnabledAnywhere();
        } catch (\Throwable $e) {
            // The toggle itself has been saved; a stale cache is harmless.
        }
    }

    /**
     * Store the result of _scanEnabledContexts() as the site-level
     * `enabledAnywhere` setting.
     * @return bool
     */
    private function _recomputeEnabledAnywhere()
    {
        $any = $this->_scanEnabledContexts();
        $this->updateSetting(self::siteId(), 'enabledAnywhere', $any, 'bool');
        return $any;
    }

    /**
     * Walk the enabled journals and report whether any has the plugin on.
     * @return bool
     */
    private function _scanEnabledContexts()
    {
        try {
            $contextDao = Application::getContextDAO();
            $contexts = $contextDao->getAll(true);   // enabled journals only
            while ($context = $contexts->next()) {
                if ($this->getSetting($context->getId(), 'enabled')) return true;
            }
        } catch (\Throwable $e) {
            // Never let this optional lookup break a request.
        }
        return false;
    }

    /**
     * Add the "Settings" link in the plugin list.
     */
    public function getActions($request, $actionArgs)
    {
        $actions = parent::getActions($request, $actionArgs);
        if (!$this->getEnabled()) return $actions;
        $router = $request->getRouter();
        array_unshift($actions, new LinkAction(
            'settings',
            new AjaxModal(
                $router->url($request, null, null, 'manage', null, array(
                    'verb' => 'settings', 'plugin' => $this->getName(), 'category' => 'generic',
                )),
                $this->getDisplayName()
            ),
            __('manager.plugins.settings'),
            null
        ));
        return $actions;
    }

    public function manage($args, $request)
    {
        if ($request->getUserVar('verb') === 'settings') {
            $context = $request->getContext();
            $contextId = $context ? $context->getId() : self::siteId();
            $form = new CustomErrorPagesSettingsForm($this, $contextId);
            if ($request->getUserVar('save')) {
                $form->readInputData();
                if ($form->validate()) {
                    $form->execute();
                    return new JSONMessage(true);
                }
            } else {
                $form->initData();
            }
            return new JSONMessage(true, $form->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * The looks an admin can pick for the 404 page, in the order the settings
     * form lists them. Each one is a set of CSS classes (defined in
     * templates/frontend/notFound.tpl) plus its background:
     *   'image'      => the photo shipped in images/404-bg.* (or, for 'custom',
     *                   the admin's own URL) — one download of about 100 KB;
     *   'background' => a CSS background (colours/gradients only, no download);
     *   neither      => the theme's own look.
     * The keys are stored as the `style` setting and never change; new looks
     * are added, not renamed.
     */
    const STYLES = array(
        'theme'           => array('classes' => ''),
        'sandstone-card'  => array('classes' => 'cep-image cep-card', 'image' => true),
        'sandstone-dark'  => array('classes' => 'cep-image cep-overlay-dark cep-on-dark', 'image' => true),
        'sandstone-light' => array('classes' => 'cep-image cep-overlay-light cep-on-light', 'image' => true),
        'sandstone-plain' => array('classes' => 'cep-image', 'image' => true),
        'navy'            => array('classes' => 'cep-colour cep-on-dark', 'background' => 'linear-gradient(135deg,#0f2a4a,#1d4e89)'),
        'slate'           => array('classes' => 'cep-colour cep-on-dark', 'background' => 'linear-gradient(135deg,#262f3a,#4a5563)'),
        'forest'          => array('classes' => 'cep-colour cep-on-dark', 'background' => 'linear-gradient(135deg,#123a2c,#2f6b4f)'),
        'burgundy'        => array('classes' => 'cep-colour cep-on-dark', 'background' => 'linear-gradient(135deg,#4a1426,#8a2f45)'),
        'sand'            => array('classes' => 'cep-colour cep-on-light', 'background' => '#f4efe6'),
        'dots'            => array('classes' => 'cep-colour cep-on-light', 'background' => 'radial-gradient(#c9d0da 1px,transparent 1.5px) 0 0/18px 18px,#f6f7f9'),
        'custom'          => array('classes' => 'cep-image cep-card', 'image' => true, 'custom' => true),
    );

    /**
     * The style chosen for a context. Installs that never saved a style (1.4.x
     * and older) keep the look they had: "Background image" becomes the
     * sandstone photo with a card (or their own URL), anything else the theme.
     */
    public function getStyleId($contextId)
    {
        $id = $this->getSetting($contextId, 'style');
        if (is_string($id) && array_key_exists($id, self::STYLES)) return $id;
        if ($this->getSetting($contextId, 'backgroundMode') !== 'image') return 'theme';
        $url = trim((string) $this->getSetting($contextId, 'backgroundImageUrl'));
        return $url !== '' ? 'custom' : 'sandstone-card';
    }

    /**
     * Everything the 404 template needs for the chosen style:
     * array('id' => …, 'classes' => …, 'style' => the section's style attribute value).
     */
    public function resolveStyle($request)
    {
        $context = $request->getContext();
        $contextId = $context ? $context->getId() : self::siteId();
        $id = $this->getStyleId($contextId);
        $def = self::STYLES[$id];
        $css = '';
        if (!empty($def['image'])) {
            if (!empty($def['custom'])) {
                $url = trim((string) $this->getSetting($contextId, 'backgroundImageUrl'));
                // Stored values are re-checked: a row written before 1.4, or by hand,
                // must not reach the style attribute unless it passes the same filter.
                if (!self::isSafeBackgroundUrl($url)) $url = '';
            } else {
                $url = $this->shippedImageUrl($request);
            }
            if ($url !== '') {
                $css = "background-image:url('" . $url . "')";
            } else {
                // No usable image (file removed, or an unsafe URL): fall back to the theme look.
                return array('id' => $id, 'classes' => '', 'style' => '');
            }
        } elseif (!empty($def['background'])) {
            $css = 'background:' . $def['background'];
        }
        return array('id' => $id, 'classes' => $def['classes'], 'style' => $css);
    }

    /**
     * URL of the photo shipped in images/404-bg.{webp,jpg,jpeg,png} ('' if the
     * file was removed), with ?v=<mtime> so a replaced file is fetched again.
     */
    public function shippedImageUrl($request)
    {
        foreach (array('webp', 'jpg', 'jpeg', 'png') as $ext) {
            $rel = 'images/404-bg.' . $ext;
            if (file_exists(dirname(__FILE__) . '/' . $rel)) {
                $mtime = @filemtime(dirname(__FILE__) . '/' . $rel);
                return $request->getBaseUrl() . '/' . $this->getPluginPath() . '/' . $rel
                     . '?v=' . ($mtime ? $mtime : '1');
            }
        }
        return '';
    }

    /**
     * Is this 404 about a missing FILE of something that does exist? Decided
     * from the state of the handler the router already ran (never from the
     * URL alone: /article/download/<no-such-id> is the same op and must stay a
     * plain "page not found").
     *
     * - Article: ArticleHandler, op view|download, the article and publication
     *   resolved and published, and a galley either resolved or requested
     *   (ArticleHandler::initialize() 404s an unknown galley of a known article).
     * - Issue: the issue galley download we turned into a 404 ourselves.
     *
     * @return array|null ['type' => 'article'|'issue', 'backUrl' => string]
     */
    public function fileMissingContext($request)
    {
        try {
            $router = $request->getRouter();
            if (!($router instanceof PKPPageRouter)) return null;
            $handler = $router->getHandler();
            $op = $request->getRequestedOp();

            if (self::$_issueFileMissing && $handler instanceof \APP\pages\issue\IssueHandler) {
                $issue = $handler->getAuthorizedContextObject(Application::ASSOC_TYPE_ISSUE);
                if (!$issue) return null;
                return array(
                    'type' => 'issue',
                    'backUrl' => $request->url(null, 'issue', 'view', array($issue->getBestIssueId())),
                );
            }

            if (!($handler instanceof \APP\pages\article\ArticleHandler) || !in_array($op, array('view', 'download'), true)) return null;
            $article = $handler->article;
            $publication = $handler->publication;
            if (!$article || (int) $article->getData('status') !== PKPSubmission::STATUS_PUBLISHED) return null;
            if (!$publication || (int) $publication->getData('status') !== PKPSubmission::STATUS_PUBLISHED) return null;
            if (!$handler->galley && !$this->_galleyRequested($request->getRequestedArgs())) return null;
            return array(
                'type' => 'article',
                // The resolved article's own id — never the raw URL segment.
                'backUrl' => $request->url(null, 'article', 'view', array($article->getBestId())),
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Does an article URL name a galley? [id, galley, …] or
     * [id, 'version', publicationId, galley, …] (ArticleHandler::initialize()).
     */
    private function _galleyRequested($args)
    {
        $i = (isset($args[1]) && $args[1] === 'version') ? 3 : 1;
        return isset($args[$i]) && (string) $args[$i] !== '' && (string) $args[$i] !== '0';
    }

    /**
     * Is $url safe to store and to emit inside
     * style="background-image:url('…')"? http(s) only, well-formed, at most
     * MAX_URL_LENGTH bytes, and none of the characters that could close the
     * quoted CSS string or the url() token: ' " ( ) \ whitespace, controls.
     * @param $url mixed
     * @return bool
     */
    public static function isSafeBackgroundUrl($url)
    {
        if (!is_string($url) || $url === '' || strlen($url) > self::MAX_URL_LENGTH) return false;
        if (!preg_match('#^https?://#i', $url)) return false;
        if (preg_match('#[\'"()\\\\\s\x00-\x1F\x7F]#', $url)) return false;
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function getDisplayName()
    {
        return __('plugins.generic.customErrorPages.displayName');
    }

    public function getDescription()
    {
        return __('plugins.generic.customErrorPages.description');
    }

    /**
     * LoadHandler callback. $args = array(&$page, &$op, &$sourceFile, &$handler).
     *
     * A request reaches a bare 404 when PKPPageRouter finds no handler for
     * $page/$op. We mirror the router's own check: load the page's index.php
     * exactly as route() will, and bow out (return false, or hand back the
     * handler it produced) whenever a real handler serves this op. Only when
     * nothing would, do we mount our 404 handler.
     *
     * OJS 3.5 page files return the handler object; OJS 3.4 page files mostly
     * still define HANDLER_CLASS (deprecated there, rejected by 3.5). Both are
     * handled; the router accepts a handler object set here in either version.
     */
    public function loadHandler($hookName, $args)
    {
        $page       =& $args[0];
        $op         =& $args[1];
        $sourceFile =& $args[2];
        $handler    =& $args[3];

        // Another LoadHandler (StaticPages, EditorialBoard, …) already claimed
        // this request → bow out.
        if ($handler || defined('HANDLER_CLASS')) return false;
        // Index / empty page → OJS' own default page, never a 404.
        if (empty($page)) return false;
        // OJS 3.5 switches the language in the router itself: after this hook,
        // PKPPageRouter::route() acts on any op named 'setLocale'. It is not a
        // handler method there (it is in OJS 3.4), so it is never an unknown op.
        if ($op === 'setLocale') return false;

        // A real page + op whose target simply DOES NOT EXIST. OJS runs these
        // through an authorization policy instead of a 404, so a bad issue id
        // bounces the visitor to the login page rather than saying "not found".
        // Catch that here, while we can still answer with a proper 404.
        if ($this->_missingObject($page, $op)) {
            return $this->_mountNotFound($op, $handler);
        }

        // Load the page's index.php exactly as PKPPageRouter::route() will (app
        // first, then lib/pkp). Its switch($op) yields a handler only when the
        // op is real. We let the core's own routing decide; we never guess.
        //
        // A page file runs in the scope that loads it and may use the router's
        // variables: OJS 3.5's pages/gateway/index.php builds its handler with
        // $request. It gets the same variable here. If loading a page file
        // fails anyway, we step aside and the core routes the request as it
        // always does: a real page must never break because of this plugin.
        $request = Application::get()->getRequest();
        try {
            if (file_exists($sourceFile)) {
                $result = require('./' . $sourceFile);
            } elseif (file_exists(PKP_LIB_PATH . '/' . $sourceFile)) {
                $result = require('./' . PKP_LIB_PATH . '/' . $sourceFile);
            } else {
                return $this->_mountNotFound($op, $handler);  // no such page at all
            }
        } catch (\Throwable $e) {
            return false;
        }

        // A real handler for this op → hand it to the core untouched.
        if (is_object($result) && in_array($op, get_class_methods($result))) {
            $handler = $result;
            return true;
        }
        if (defined('HANDLER_CLASS') && in_array($op, get_class_methods(HANDLER_CLASS))) {
            return true;                                        // the core instantiates it
        }

        // Nothing resolved: an unknown op on a known page (a mistyped
        // /about/editorailTeam). OJS would bare-404 it — answer with ours.
        return $this->_mountNotFound($op, $handler);
    }

    /**
     * Mount our 404 handler for this request. Nothing is rendered here — the
     * core runs the handler after its own session init (rendering inside the
     * hook would re-enter routing).
     */
    private function _mountNotFound(&$op, &$handler)
    {
        $handler = new \APP\plugins\generic\customErrorPages\pages\CustomErrorPagesHandler();
        $op = 'notFound';
        return true;
    }

    /**
     * Answer "404 Not Found" the way the running OJS does: OJS 3.4 has
     * Dispatcher::handle404(), OJS 3.5 throws NotFoundHttpException (which
     * PKPApplication::execute() turns into the same bare 404 body). Either way
     * the shutdown net below then themes it. Does not return.
     */
    public static function notFound()
    {
        $dispatcher = Application::get()->getRequest()->getDispatcher();
        if (method_exists($dispatcher, 'handle404')) {
            $dispatcher->handle404();
        }
        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
    }

    /** Site-level context id: 0 in OJS 3.4, null in OJS 3.5 (Application::SITE_CONTEXT_ID). */
    public static function siteId()
    {
        return defined(\PKP\core\PKPApplication::class . '::SITE_CONTEXT_ID') ? \PKP\core\PKPApplication::SITE_CONTEXT_ID : 0;
    }

    /**
     * Does this request address an object that does not exist at all?
     *
     * Only the ISSUE pages need this: OjsIssueRequiredPolicy resolves the id
     * with IssueDAO::getByBestId() and, when nothing comes back, returns
     * AUTHORIZATION_DENY — which OJS turns into a login redirect (anonymous) or
     * user/authorizationDenied (logged in), never a 404. We mirror exactly that
     * one lookup, so a genuinely missing issue answers 404 while permissions
     * (e.g. an unpublished issue an editor may preview) stay OJS' business.
     * Articles already self-404 inside their handler, so they need nothing here.
     */
    private function _missingObject($page, $op)
    {
        if ($page !== 'issue') return false;
        if (!in_array($op, array('view', 'download'), true)) return false;

        $request = Application::get()->getRequest();
        if (!$request) return false;
        $context = $request->getContext();
        if (!$context) return false;                      // no journal → not our case

        $args = $request->getRequestedArgs();
        $issueId = isset($args[0]) ? $args[0] : null;
        if ($issueId === null || $issueId === '' || $issueId === 'current') return false;

        try {
            return !Repo::issue()->getByBestId((string) $issueId, (int) $context->getId());
        } catch (\Throwable $e) {
            return false;   // never break routing over this check
        }
    }

    /**
     * Shutdown handler. If the finished response is OJS' bare 404
     * ("<h1>404 Not Found</h1>" with a 404 status) — which is all that
     * Dispatcher::handle404()/fatalError() emit, including when a handler rejects
     * missing data deep in its own code — replace it with the themed 404. Any
     * other response (a real page, a themed 404 we already rendered, a JSON API
     * 404, a redirect) is left exactly as-is.
     */
    public function catchBareNotFound()
    {
        if (self::$_netDisabled) return;                      // a download ran
        if (http_response_code() != 404) return;
        if (headers_sent()) return;                           // too late to replace
        // Only OUR buffer may be rewritten: if it is gone, or someone opened
        // another buffer on top of it, leave the response exactly as it is.
        if (self::$_bufferLevel === null || ob_get_level() !== self::$_bufferLevel) return;
        $body = ob_get_contents();
        if (trim((string) $body) !== self::BARE_404_BODY) return; // only OJS' bare 404
        $request = Application::get()->getRequest();
        if (!$request) return;
        // NB: a journal context is NOT required. Without one (the site home and
        // its pages) the page renders in the SITE's theme, which is exactly what
        // those URLs should look like.
        // PHP resets cwd before shutdown functions; restore the OJS root so the
        // theme header's relative file reads (menus, plugin head XML) resolve.
        if (!empty(self::$_rootDir)) @chdir(self::$_rootDir);
        ob_end_clean();                                       // drop the bare 404 body
        self::$_bufferLevel = null;
        try {
            // Render into its own buffer and emit only once it completed, so a
            // failure half-way through can never leave a truncated page.
            ob_start();
            $handler = new \APP\plugins\generic\customErrorPages\pages\CustomErrorPagesHandler();
            $handler->notFound(array(), $request);            // render the themed page
            $page = ob_get_clean();
            if (trim((string) $page) === '') { echo $body; return; }
            echo $page;
        } catch (\Throwable $e) {
            if (ob_get_level() > 0) ob_end_clean();
            echo $body;   // rendering failed → restore OJS' original bare 404, never blank
        }
    }
}
