<?php

/**
 * @file plugins/generic/customErrorPages/index.php
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Wrapper for the Custom Error Pages plugin (OJS 3.4 / 3.5).
 *
 * OJS 3.4 and 3.5 load the namespaced plugin class directly and only fall
 * back to this file. OJS 3.3 always loads plugins through this file, so this
 * is where a package installed on the wrong OJS line is stopped: on OJS 3.3
 * the namespaced class cannot load, and a fatal error here would break the
 * whole site, its command-line tools included. There we return an inert
 * placeholder that adds no hooks and tells the administrator in the plugin
 * list which package to install instead.
 */

if (!class_exists('PKP\\plugins\\GenericPlugin')) {
    // OJS 3.3: wrong package.
    import('lib.pkp.classes.plugins.GenericPlugin');
    if (!class_exists('CustomErrorPagesPlugin', false)) {
        class CustomErrorPagesPlugin extends GenericPlugin
        {
            public function register($category, $path, $mainContextId = null)
            {
                parent::register($category, $path, $mainContextId);
                return true; // listed, but adds no hooks: the site is not touched
            }

            public function getDisplayName()
            {
                return 'Custom Error Pages (wrong package)';
            }

            public function getDescription()
            {
                return 'This package is for OJS 3.4 and 3.5 and does nothing on this site. '
                    . 'Delete it and install the OJS 3.3 package (customErrorPages-ojs3.3-<version>.tar.gz).';
            }
        }
    }
    return new CustomErrorPagesPlugin();
}

return new \APP\plugins\generic\customErrorPages\CustomErrorPagesPlugin();
