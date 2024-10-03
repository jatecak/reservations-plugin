<?php

namespace Reservations\Base;

use Reservations;
use Reservations\Utils;

abstract class Page extends Service
{
    protected $styles  = [];
    protected $scripts = [];

    protected $title    = null;
    protected $parentId = null;

    protected $permalink;
    protected $parentPermalink;

    public function prepare()
    {

    }

    public function assets()
    {
    }

    public function render()
    {

    }

    /** @action(wp_enqueue_scripts) */
    public function actionEnqueueScripts()
    {
        $this->assets();
    }

    // /** @filter(template_include) */
    // public function filterTemplateInclude($template)
    // {
    //     return locate_template(["page.php"]);
    // }

    public function isCurrent()
    {
        return true;
    }

    public function getRouter()
    {
        return $this->plugin->pageRouter;
    }

    public function setParentId($id)
    {
        $this->parentId = $id;
    }

    public function setParentPageId($id)
    {
        $this->setParentId($id);
    }

    /** @filter(the_title) */
    public function filterTheTitle($title, $id)
    {
        if ($this->parentId !== null && $this->parentId !== $id) {
            return $title;
        }

        return $this->title !== null ? $this->title : $title;
    }

    /** @filter(the_content) */
    public function filterTheContent($content)
    {
        if (!in_the_loop() || !is_main_query()) {
            return $content;
        }

        if ($this->parentId !== null && $this->parentId !== get_the_ID()) {
            return $content;
        }

        ob_start();
        $rendered = $this->render();
        $rendered .= ob_get_clean();

        if (strpos($content, "[reservations]") !== false) {
            return str_replace("[reservations]", $rendered, $content);
        }

        if (strpos($content, "JTVCcmVzZXJ2YXRpb25zJTVE") !== false) {
            return str_replace("JTVCcmVzZXJ2YXRpb25zJTVE", base64_encode(rawurlencode($rendered)), $content);
        }

        return $rendered;
    }

    public function handleShortcode($atts) {
        ob_start();
        $rendered = $this->render();
        $rendered .= ob_get_clean();

        return $rendered;
    }

    public function display()
    {
        $this->plugin->addHooks($this);
        $this->loadPermalink();
        add_shortcode($this->plugin->slug(), [$this, "handleShortcode"]);
        $this->prepare();
    }

    protected function loadPermalink()
    {
        if (!$this->parentPermalink) {
            $this->parentPermalink = get_permalink((int) $this->parentId);

            if (!$this->permalink) {
                $this->permalink = $this->parentPermalink;
            }
        }
    }

    protected function link($relativePart, $insertTrailingSlash = false, $useParentPermalink = false)
    {
        if (is_array($relativePart)) {
            $relativePart = call_user_func_array([Utils::class, "joinPaths"], $relativePart);
        }

        if ($insertTrailingSlash) {
            $relativePart = rtrim($relativePart, "/") . "/";
        }

        return Utils::joinPaths($useParentPermalink ? $this->parentPermalink : $this->permalink, $relativePart);
    }

    protected function parentLink($relativePart, $insertTrailingSlash = false)
    {
        return $this->link($relativePart, $insertTrailingSlash, true);
    }

    protected function redirect($url)
    {
        wp_redirect($url);
        exit;
    }

    protected function sendResponse($body, $statusCode = 200)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
        }

        echo $body;
        exit;
    }

    protected function enqueueScript($name, $relPath, $deps = [], $inFooter = false, $data = null)
    {
        if ((is_array($inFooter) || is_object($inFooter)) && $data === null) {
            $data     = $inFooter;
            $inFooter = false;
        }

        if (Utils::isAbsoluteUrl($relPath)) {
            wp_enqueue_script($name, $relPath, $deps, false, $inFooter);
        } else {
            wp_enqueue_script($name, $this->plugin->url($relPath), $deps, Utils::getFileVersion($this->plugin->path($relPath)), $inFooter);
        }

        if ($data) {
            wp_localize_script($name, $name . "_data", [
                "data" => $data,
            ]);
        }
    }

    protected function enqueueStyle($name, $relPath, $deps = [])
    {
        if (Utils::isAbsoluteUrl($relPath)) {
            wp_enqueue_style($name, $relPath, $deps, false);
        } else {
            wp_enqueue_style($name, $this->plugin->url($relPath), $deps, Utils::getFileVersion($this->plugin->path($relPath)));
        }
    }

    protected function enqueueGlobalStyle($deps = [])
    {
        $this->enqueueStyle(Reservations::SLUG, "public/style.css", $deps);

        $modeFile = "public/style-" . Reservations::MODE . ".css";
        if (file_exists($this->plugin->path($modeFile))) {
            $this->enqueueStyle(Reservations::SLUG . "-" . Reservations::MODE, $modeFile, [Reservations::SLUG]);
        }
    }
}
