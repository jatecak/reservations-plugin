<?php

namespace Reservations;

use Reservations\Models;
use Reservations\Utils;
use Reservations\Utils\PluginAccess;
use Reservations\Utils\Wordpress;

class PageRouter
{
    use PluginAccess;

    private $gym;
    private $training;
    private $tgroup;
    private $route;
    private $event;

    public $trainingsPage;
    public $eventsPage;
    public $eventTypePages;

    public function init()
    {
        $this->plugin->addHooks($this);
    }

    protected function getOrCreatePage($optionKey, $postName, $postTitle)
    {
        $page_id = $this->plugin->getOption($optionKey);

        if ($page_id) {
            $page = get_post((int) $page_id);
        }

        if (!$page_id || !$page) {
            $page_id = wp_insert_post([
                "post_type"   => "page",
                "post_name"   => $postName,
                "post_status" => "publish",
                "post_title"  => $postTitle,
                "post_author" => Utils\Wordpress::getAdminId(),
            ]);

            $this->plugin->updateOption($optionKey, $page_id);
            $page = get_post($page_id);
        }

        return $page;
    }

    protected function getTrainingsPage()
    {
        return $this->getOrCreatePage("trainings_page_id", "trainings", __('Trainings', 'reservations'));
    }

    protected function getEventsPage()
    {
        if (!$this->plugin->isFeatureEnabled("unified_events")) {
            return null;
        }

        return $this->getOrCreatePage("events_page_id", "events", __('Events', 'reservations'));
    }

    protected function getEventTypePage($eventType)
    {
        if ($this->plugin->isFeatureEnabled("unified_events")) {
            return null;
        }

        return $this->getOrCreatePage($eventType["slug"] . "_page_id", $eventType["slugPlural"], $eventType["labelPlural"]);
    }

    // protected function getObject($name)
    // {
    //     if (is_numeric($name)) {
    //         return Models\Gym::find((int) $name) ?: Models\Training::find((int) $name);
    //     }

    //     return Models\Gym::where("slug", $name)->first() ?: Models\Training::where("slug", $name)->first();
    // }

    /** @action(save_post_page) */
    public function flushRewriteRules($pageId)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can("edit_post", $pageId)) {
            return;
        }

        // if ($this->trainingsPage->ID === $pageId || collect($this->postTypePages)->contains(function ($p) use ($pageId) {
        //     return $p->ID === $pageId;
        // })) {
        // var_dump("flushing rewrite rules");
        // flush_rewrite_rules();
        // }
    }

    /** @filter(query_vars) */
    public function registerQueryVars($vars)
    {
        $vars[] = "gym";
        $vars[] = "tid";
        $vars[] = "tgroup";
        $vars[] = "route";
        $vars[] = "event";
        return $vars;
    }

    /** @action(parse_query) */
    public function validateQueryVars($wp_query)
    {
        if ($wp_query->get("route")) {
            $this->route = $wp_query->get("route");
        }

        if ($wp_query->get("gym")) {
            $this->gym = Models\Gym::where("slug", $wp_query->get("gym"))->first();

            if (!$this->gym) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        if ($wp_query->get("tgroup")) {
            $this->tgroup = Models\TrainingGroup::where("slug", $wp_query->get("tgroup"))->first();

            if (!$this->tgroup) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        if ($wp_query->get("tid")) {
            $this->training = Models\Training::where("status", "publish")->whereKey((int) $wp_query->get("tid"))->first();

            if (!$this->training) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        if ($wp_query->get("event")) {
            $this->event = Models\Event::where("post_name", $wp_query->get("event"))->first();

            if (!$this->event) {
                $wp_query->set_404();
                status_header(404);
                return;
            }
        }

        if ((($this->route === "thankyou" && !isset($_GET['replacement'])) || $this->route === "payment") && empty($_GET['id'])) {
            wp_redirect(get_permalink($this->trainingsPage));
            exit;
        }
    }

    public function isActive()
    {
        if (is_page($this->trainingsPage->ID)) {
            return true;
        }

        if ($this->plugin->isFeatureEnabled("unified_events") && is_page($this->eventsPage->ID)) {
            return true;
        }

        if (!$this->plugin->isFeatureEnabled("unified_events")) {
            foreach (Models\Local\EventType::all() as $type) {
                $wp_page = $this->eventTypePages[$type["id"]];

                if (is_page($wp_page->ID)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @action(init) */
    public function loadPages()
    {
        if (!$this->trainingsPage) {
            $this->trainingsPage = $this->getTrainingsPage();
        }

        if ($this->plugin->isFeatureEnabled("unified_events") && !$this->eventsPage) {
            $this->eventsPage = $this->getEventsPage();
        }

        if (!$this->plugin->isFeatureEnabled("unified_events") && !$this->eventTypePages) {
            $this->eventTypePages = [];

            foreach (Models\Local\EventType::all() as $type) {
                $this->eventTypePages[$type["id"]] = $this->getEventTypePage($type);
            }
        }
    }

    /** @action(init) */
    public function addRewriteRules()
    {
        $baseQuoted = preg_quote(Wordpress::getPageRelativeUrl($this->trainingsPage));
        $baseUrl    = 'index.php?page_id=' . $this->trainingsPage->ID;

        add_rewrite_rule('^' . $baseQuoted . '/' . _x('ajax', 'url slug', 'reservations') . '/?', $baseUrl . '&route=ajax', 'top');
        add_rewrite_rule('^' . $baseQuoted . '/' . _x('thank-you', 'url slug', 'reservations') . '/?', $baseUrl . '&route=thankyou', 'top');
        add_rewrite_rule('^' . $baseQuoted . '/' . _x('payment', 'url slug', 'reservations') . '/?', $baseUrl . '&route=payment', 'top');

        add_rewrite_rule('^' . $baseQuoted . '/([a-z0-9-]+)/([0-9]+)/?', $baseUrl . '&route=training&gym=$matches[1]&tid=$matches[2]', 'top');
        add_rewrite_rule('^' . $baseQuoted . '/([a-z0-9-]+)/' . _x('subscribe', 'url slug', 'reservations') . '/?', $baseUrl . '&route=subscribe&tgroup=$matches[1]', 'top');
        add_rewrite_rule('^' . $baseQuoted . '/([a-z0-9-]+)/?', $baseUrl . '&route=schedule&gym=$matches[1]', 'top');

        if ($this->plugin->isFeatureEnabled("unified_events")) {
            $baseQuoted = preg_quote(Wordpress::getPageRelativeUrl($this->eventsPage));
            $baseUrl    = 'index.php?page_id=' . $this->eventsPage->ID;

            add_rewrite_rule('^' . $baseQuoted . '/([a-z0-9-]+)/' . _x('subscribe', 'url slug', 'reservations') . '/?', $baseUrl . '&route=subscribe&event=$matches[1]', 'top');
            add_rewrite_rule('^' . $baseQuoted . '/' . _x('ajax', 'url slug', 'reservations') . '/?', $baseUrl . '&route=ajax', 'top');
            add_rewrite_rule('^' . $baseQuoted . '/' . _x('thank-you', 'url slug', 'reservations') . '/?', $baseUrl . '&route=thankyou', 'top');
            add_rewrite_rule('^' . $baseQuoted . '/' . _x('payment', 'url slug', 'reservations') . '/?', $baseUrl . '&route=payment', 'top');
        } else {
            foreach (Models\Local\EventType::all() as $type) {
                $baseQuoted = preg_quote(Wordpress::getPageRelativeUrl($this->eventTypePages[$type["id"]]));
                $baseUrl    = 'index.php?page_id=' . $this->eventTypePages[$type["id"]]->ID;

                add_rewrite_rule('^' . $baseQuoted . '/([a-z0-9-]+)/' . _x('subscribe', 'url slug', 'reservations') . '/?', $baseUrl . '&route=subscribe&event=$matches[1]', 'top');
                add_rewrite_rule('^' . $baseQuoted . '/' . _x('ajax', 'url slug', 'reservations') . '/?', $baseUrl . '&route=ajax', 'top');
                add_rewrite_rule('^' . $baseQuoted . '/' . _x('thank-you', 'url slug', 'reservations') . '/?', $baseUrl . '&route=thankyou', 'top');
                add_rewrite_rule('^' . $baseQuoted . '/' . _x('payment', 'url slug', 'reservations') . '/?', $baseUrl . '&route=payment', 'top');
            }
        }

        if ($this->plugin->activating) {
            flush_rewrite_rules();
        }
    }

    /**
     * @action(wp)
     */
    public function runRouter()
    {
        global $wp_query;

        if (is_page($this->trainingsPage->ID)) {
            switch ($this->route) {
                case "thankyou":
                    $page = new Pages\ThankYou($this->plugin);
                    if (isset($_GET['replacement'])) {
                        $page->setIsReplacement(true);
                    } else {
                        $page->setTransactionId((int) $_GET['id']);
                    }
                    break;

                case "payment":
                    $page = new Pages\Payment($this->plugin);
                    $page->setPaymentHash($_GET['id']);
                    break;

                case "ajax":
                    $page = new Pages\Ajax($this->plugin);
                    break;

                case "training":
                    $page = new Pages\Training($this->plugin);
                    $page->setTraining($this->training);
                    break;

                case "subscribe":
                    $page = new Pages\Subscribe($this->plugin);
                    $page->setTrainingGroup($this->tgroup);
                    break;

                case "schedule":
                    $page = new Pages\Schedule($this->plugin);
                    $page->setGym($this->gym);
                    break;

                default:
                    $page = new Pages\Trainings($this->plugin);
            }

            $page->setParentId($this->trainingsPage->ID);
            $page->display();

            return;
        }

        if ($this->plugin->isFeatureEnabled("unified_events") && is_page($this->eventsPage->ID)) {
            switch ($this->route) {
                case "thankyou":
                    $page = new Pages\Events\ThankYou($this->plugin);
                    if (isset($_GET['replacement'])) {
                        $page->setIsReplacement(true);
                    } else {
                        $page->setTransactionId((int) $_GET['id']);
                    }
                    break;

                case "payment":
                    $page = new Pages\Events\Payment($this->plugin);
                    $page->setPaymentHash($_GET['id']);
                    break;

                case "ajax":
                    $page = new Pages\Events\Ajax($this->plugin);
                    break;

                case "subscribe":
                    $page = new Pages\Events\Subscribe($this->plugin);
                    $page->setEvent($this->event);
                    break;

                default:
                    $page = new Pages\Events\Events($this->plugin);
            }

            $page->display();
        } else if (!$this->plugin->isFeatureEnabled("unified_events")) {
            foreach (Models\Local\EventType::all() as $type) {
                $wp_page = $this->eventTypePages[$type["id"]];

                if (!is_page($wp_page->ID)) {
                    continue;
                }

                switch ($this->route) {
                    case "thankyou":
                        $page = new Pages\Events\ThankYou($this->plugin);
                        if (isset($_GET['replacement'])) {
                            $page->setIsReplacement(true);
                        } else {
                            $page->setTransactionId((int) $_GET['id']);
                        }
                        break;

                    case "payment":
                        $page = new Pages\Events\Payment($this->plugin);
                        $page->setPaymentHash($_GET['id']);
                        break;

                    case "ajax":
                        $page = new Pages\Events\Ajax($this->plugin);
                        break;

                    case "subscribe":
                        $page = new Pages\Events\Subscribe($this->plugin);
                        $page->setEvent($this->event);
                        break;

                    default:
                        $page = new Pages\Events\Events($this->plugin);
                }

                $page->setEventType($type);
                $page->display();
            }
        }
    }
}
