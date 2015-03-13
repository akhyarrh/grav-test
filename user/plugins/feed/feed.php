<?php
namespace Grav\Plugin;

use Grav\Common\Data;
use Grav\Common\Page\Collection;
use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Page;
use RocketTheme\Toolbox\Event\Event;

class FeedPlugin extends Plugin
{
    /**
     * @var bool
     */
    protected $active = false;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var array
     */
    protected $feed_config;

    /**
     * @var array
     */
    protected $valid_types = array('rss','atom');

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onBlueprintCreated' => ['onBlueprintCreated', 0]
        ];
    }

    /**
     * Activate feed plugin only if feed was requested for the current page.
     *
     * Also disables debugger.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        $this->feed_config = (array) $this->config->get('plugins.feed');

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $this->type = $uri->extension();

        if ($this->type && in_array($this->type, $this->valid_types)) {
            $this->active = true;

            $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0],
                'onCollectionProcessed' => ['onCollectionProcessed', 0],
                'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
                'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
            ]);
        }
    }

    /**
     * Initialize feed configuration.
     */
    public function onPageInitialized()
    {
        /** @var Page $page */
        $page = $this->grav['page'];
        if (isset($page->header()->feed)) {
            $this->feed_config = array_merge($this->feed_config, $page->header()->feed);
        }
    }

    /**
     * Feed consists of all sub-pages.
     *
     * @param Event $event
     */
    public function onCollectionProcessed(Event $event)
    {
        /** @var Collection $collection */
        $collection = $event['collection'];
        $collection->setParams(array_merge($collection->params(), $this->feed_config));
    }

    /**
     * Add current directory to twig lookup paths.
     */
    public function onTwigTemplatePaths()
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Set needed variables to display the feed.
     */
    public function onTwigSiteVariables()
    {
        $twig = $this->grav['twig'];
        $twig->template = 'feed.' . $this->type . '.twig';
    }

    /**
     * Extend page blueprints with feed configuration options.
     *
     * @param Event $event
     */
    public function onBlueprintCreated(Event $event)
    {
        static $inEvent = false;

        /** @var Data\Blueprint $blueprint */
        $blueprint = $event['blueprint'];
        if (!$inEvent && $blueprint->name == 'blog_list') {
            $inEvent = true;
            $blueprints = new Data\Blueprints(__DIR__ . '/blueprints/');
            $extends = $blueprints->get('feed');
            $blueprint->extend($extends, true);
            $inEvent = false;
        }
    }
}
