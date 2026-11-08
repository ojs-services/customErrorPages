<?php

/**
 * @defgroup plugins_generic_customErrorPages Custom Error Pages
 */

/**
 * @file index.php
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * @ingroup plugins_generic_customErrorPages
 * @brief Wrapper for the Custom Error Pages plugin. OJS's PluginRegistry loads
 *        each generic plugin by including its index.php and using the returned
 *        object, so this thin wrapper is required for the plugin to load and to
 *        appear in the plugin manager list.
 *
 *        This package is for OJS 3.3. OJS 3.4 and later have the namespaced
 *        class below; there the 3.3 code would not load, so the plugin stays
 *        off instead (OJS 3.4 and 3.5 accept null from this file) and the site
 *        keeps working.
 */

if (class_exists('PKP\\plugins\\GenericPlugin')) return null;

require_once('CustomErrorPagesPlugin.inc.php');

return new CustomErrorPagesPlugin();
