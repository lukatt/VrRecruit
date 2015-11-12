<?php

use Vreasy\Models\Language;
use Vreasy\Utils\Locale;

class Vreasy_Helper_RenderUsingLocale extends Zend_View_Helper_Abstract
{
    public $prefix = 'common/';
    public $user;

    public function renderUsingLocale($fileNameOrPathTpl, $user = null)
    {
        $translate = \Zend_Registry::get('Zend_Translate');
        $oldLocale = $translate->getLocale();
        $user = $user ?: ($this->user ?: $this->view->user);

        $buildPath = function($fileNameOrPathTpl, $locale) {
            $path = sprintf($fileNameOrPathTpl, $locale);
            if ($path == $fileNameOrPathTpl) {
                // When the formatted output string didn't change,
                // then it needs to be built manually
                $path = $this->prefix. $locale. '/'. $fileNameOrPathTpl;
            }
            return $path;
        };

        $render = function($fileNameOrPathTpl, $locale) use($buildPath, $oldLocale, $translate) {
            $translate->setLocale($locale);
            $c = $this->view->render($buildPath($fileNameOrPathTpl, $locale));
            $translate->setLocale($oldLocale);
            return $c;
        };

        try {
            $locale = Locale::getLocaleFrom($user);
            $contents = $render($fileNameOrPathTpl, $locale);
        } catch(\Zend_View_Exception $e) {
            if (false === stripos($e->getMessage(), 'not found in path')) {
                throw $e;
            }
            $locale = (new Language())->locale;
            $contents = $render($fileNameOrPathTpl, $locale);
        }
        return isset($contents) ? $contents : '';
    }
}
