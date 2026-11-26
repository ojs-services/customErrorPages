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
 *        This package is for OJS 3.3. On OJS 3.4 and later the 3.3 code would
 *        not load, so the plugin stays off instead (OJS 3.4 and 3.5 accept null
 *        from this file) and the site keeps working.
 *
 *        The check asks for a class that OJS 3.4+ has always loaded by now and
 *        that OJS 3.3 does not have, WITHOUT autoloading (second argument
 *        false): on 3.3 an autoload attempt can reach another plugin's broad
 *        autoloader, which loads GenericPlugin a second time ("Cannot declare
 *        class GenericPlugin"). PKP\plugins\GenericPlugin is not a usable test
 *        here: on 3.4/3.5 it is often not loaded yet when this file runs.
 */

if (class_exists('PKP\\core\\PKPApplication', false)) return null;

require_once('CustomErrorPagesPlugin.inc.php');

return new CustomErrorPagesPlugin();
