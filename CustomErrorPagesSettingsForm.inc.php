<?php
/**
 * @file plugins/generic/customErrorPages/CustomErrorPagesSettingsForm.inc.php
 *
 * Copyright (c) 2026 OJS Services. Distributed under the GNU GPL v3.
 * For full terms see the file LICENSE.
 *
 * Settings for Custom Error Pages: the look of the error page, picked from a
 * list of styles (CustomErrorPagesPlugin::STYLES), plus an optional image URL
 * for the "your own image" style.
 */
import('lib.pkp.classes.form.Form');

class CustomErrorPagesSettingsForm extends Form
{
    /** @var CustomErrorPagesPlugin */
    private $_plugin;
    /** @var int */
    private $_contextId;

    public function __construct($plugin, $contextId)
    {
        $this->_plugin = $plugin;
        $this->_contextId = (int) $contextId;
        parent::__construct($plugin->getTemplateResource('settingsForm.tpl'));

        $prefix = 'plugins.generic.customErrorPages.settings.';
        $this->addCheck(new FormValidatorInSet($this, 'style', FORM_VALIDATOR_REQUIRED_VALUE, $prefix . 'style.invalid', array_keys(CustomErrorPagesPlugin::STYLES)));

        // The URL ends up inside style="background-image:url('…')": beyond being
        // a valid URL it must be http(s), fit in 255 bytes and carry none of the
        // characters that could break out of that CSS (see isSafeBackgroundUrl).
        $invalid = $prefix . 'backgroundImageUrl.invalid';
        $this->addCheck(new FormValidatorUrl($this, 'backgroundImageUrl', FORM_VALIDATOR_OPTIONAL_VALUE, $invalid));
        $this->addCheck(new FormValidatorCustom(
            $this, 'backgroundImageUrl', FORM_VALIDATOR_OPTIONAL_VALUE, $invalid,
            array('CustomErrorPagesPlugin', 'isSafeBackgroundUrl')
        ));
        // "Your own image" needs an image.
        $form = $this;
        $this->addCheck(new FormValidatorCustom(
            $this, 'style', FORM_VALIDATOR_REQUIRED_VALUE, $prefix . 'backgroundImageUrl.required',
            function ($style) use ($form) {
                return $style !== 'custom' || trim((string) $form->getData('backgroundImageUrl')) !== '';
            }
        ));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    public function initData()
    {
        $this->setData('style', $this->_plugin->getStyleId($this->_contextId));
        $this->setData('backgroundImageUrl', $this->_plugin->getSetting($this->_contextId, 'backgroundImageUrl'));
    }

    public function readInputData()
    {
        $this->readUserVars(array('style', 'backgroundImageUrl'));
        $this->setData('backgroundImageUrl', trim((string) $this->getData('backgroundImageUrl')));
    }

    public function fetch($request, $template = null, $display = false)
    {
        // One entry per style, in order, with what the settings list shows:
        // number, name, short description and a small preview swatch.
        $shipped = $this->_plugin->shippedImageUrl($request);
        $styles = array();
        $n = 0;
        foreach (CustomErrorPagesPlugin::STYLES as $id => $def) {
            $swatch = '';
            if (!empty($def['background'])) {
                $swatch = 'background:' . $def['background'];
            } elseif (!empty($def['image']) && empty($def['custom']) && $shipped !== '') {
                $swatch = "background-image:url('" . $shipped . "')";
            }
            $styles[$id] = array(
                'number'  => ++$n,
                'classes' => $def['classes'],
                'swatch'  => $swatch,
                'nameKey' => 'plugins.generic.customErrorPages.style.' . $id . '.name',
                'descKey' => 'plugins.generic.customErrorPages.style.' . $id . '.desc',
            );
        }
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign(array(
            'pluginName' => $this->_plugin->getName(),
            'styles'     => $styles,
        ));
        return parent::fetch($request, $template, $display);
    }

    public function execute(...$functionArgs)
    {
        $style = (string) $this->getData('style');
        if (!array_key_exists($style, CustomErrorPagesPlugin::STYLES)) $style = 'theme';
        $url = trim((string) $this->getData('backgroundImageUrl'));
        // validate() has already rejected an unsafe value; never store one anyway.
        if ($url !== '' && !CustomErrorPagesPlugin::isSafeBackgroundUrl($url)) return false;
        $this->_plugin->updateSetting($this->_contextId, 'style', $style, 'string');
        $this->_plugin->updateSetting($this->_contextId, 'backgroundImageUrl', $url, 'string');
        return parent::execute(...$functionArgs);
    }
}
