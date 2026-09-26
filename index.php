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
 */

require_once('CustomErrorPagesPlugin.inc.php');

return new CustomErrorPagesPlugin();
