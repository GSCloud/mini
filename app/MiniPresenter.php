<?php
/**
 * GSC Tesseract
 * php version 7.4.0
 *
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

namespace GSC;

use Cake\Cache\Cache;
use Michelf\MarkdownExtra;

/**
 * Mini Presenter
 * 
 * @category Framework
 * @package  Tesseract
 * @author   Fred Brooker <git@gscloud.cz>
 * @license  MIT https://gscloud.cz/LICENSE
 * @link     https://mini.gscloud.cz
 */

class MiniPresenter extends APresenter
{
    /**
     * Main controller
     * 
     * @param mixed $param optional parameter
     * 
     * @return object controller
     */
    public function process($param = null)
    {
        // basic setup
        $data = $this->getData();
        $presenter = $this->getPresenter();
        $view = $this->getView();
        $this->checkRateLimit()->setHeaderHtml()->dataExpander($data);

        // HTML content
        $file = null;
        defined('ROOT') && $file = ROOT . "/README.md";

        if ($file && \file_exists($file) && \is_readable($file)) {
            $data["l"]["readme"] = MarkdownExtra::defaultTransform(
                \file_get_contents($file) ?: ''
            );
        }

        // process template
        $template = 'app';
        if (\is_string($view) && \is_array($presenter)) {
            $template = \array_key_exists("template", $presenter[$view])
                ? $presenter[$view]["template"] : 'app';
        }
        
        // process output
        $output = $this->setData($data)->renderHTML($template);
        StringFilters::trim_html_comment($output);
        return $this->setData("output", $output);
    }
}
