<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\File\CompiledYamlFile;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;
use RocketTheme\Toolbox\ResourceLocator\UniformResourceLocator;

/**
 * The Themes object holds an array of all the theme objects that Grav knows about.
 *
 * @author RocketTheme
 * @license MIT
 */
class Themes extends Iterator
{
    /** @var Grav */
    protected $grav;

    /** @var Config */
    protected $config;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
        $this->config = $grav['config'];
    }

    public function init()
    {
        /** @var EventDispatcher $events */
        $events = $this->grav['events'];

        /** @var Themes $themes */
        $themes = $this->grav['themes'];
        $themes->configure();

        try {
            $instance = $themes->load();
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException($this->current(). ' theme could not be found');
        }

        if ($instance instanceof EventSubscriberInterface) {
            $events->addSubscriber($instance);
        }

        $this->grav['theme'] = $instance;
    }

    /**
     * Return list of all theme data with their blueprints.
     *
     * @return array
     */
    public function all()
    {
        $list = array();
        $locator = Grav::instance()['locator'];
        $iterator = new \DirectoryIterator($locator->findResource('themes://', false));

        /** @var \DirectoryIterator $directory */
        foreach ($iterator as $directory) {
            if (!$directory->isDir() || $directory->isDot()) {
                continue;
            }

            $type = $directory->getBasename();
            $list[$type] = self::get($type);
        }

        ksort($list);

        return $list;
    }

    /**
     * Get theme configuration or throw exception if it cannot be found.
     *
     * @param  string            $name
     * @return Data
     * @throws \RuntimeException
     */
    public function get($name)
    {
        if (!$name) {
            throw new \RuntimeException('Theme name not provided.');
        }

        $blueprints = new Blueprints("themes://{$name}");
        $blueprint = $blueprints->get('blueprints');
        $blueprint->name = $name;

        // Find thumbnail.
        $thumb = "themes://{$name}/thumbnail.jpg";

        if (file_exists($thumb)) {
            $blueprint->set('thumbnail', $this->grav['base_url'] . "/user/themes/{$name}/thumbnail.jpg");
        }

        // Load default configuration.
        $file = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT);
        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = CompiledYamlFile::instance("user://config/themes/{$name}" . YAML_EXT);
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }

    /**
     * Return name of the current theme.
     *
     * @return string
     */
    public function current()
    {
        return (string) $this->config->get('system.pages.theme');
    }

    /**
     * Load current theme.
     *
     * @return Theme|object
     */
    public function load()
    {
        // NOTE: ALL THE LOCAL VARIABLES ARE USED INSIDE INCLUDED FILE, DO NOT REMOVE THEM!
        $grav = $this->grav;
        $config = $this->config;
        $name = $this->current();

        /** @var UniformResourceLocator $locator */
        $locator = $grav['locator'];
        $file = $locator('theme://theme.php') ?: $locator("theme://{$name}.php");

        if ($file) {
            // Local variables available in the file: $grav, $config, $name, $file
            $class = include $file;

            if (!is_object($class)) {

                $themeClassFormat = [
                    'Grav\\Theme\\'.ucfirst($name),
                    'Grav\\Theme\\'.Inflector::camelize($name)
                ];
                $themeClassName = false;

                foreach ($themeClassFormat as $themeClass) {
                    if (class_exists($themeClass)) {
                        $themeClassName = $themeClass;
                        $class = new $themeClassName($grav, $config, $name);
                        break;
                    }
                }
            }
        } elseif (!$locator('theme://') && !defined('GRAV_CLI')) {
            exit("Theme '$name' does not exist, unable to display page.");
        }

        if (empty($class)) {
            $class = new Theme($grav, $config, $name);
        }

        return $class;
    }

    /**
     * Configure and prepare streams for current template.
     *
     * @throws \InvalidArgumentException
     */
    public function configure()
    {
        $name = $this->current();
        $config = $this->config;

        $this->loadConfiguration($name, $config);

        /** @var UniformResourceLocator $locator */
        $locator = $this->grav['locator'];

        // TODO: move
        $registered = stream_get_wrappers();
        $schemes = $config->get(
            "themes.{$name}.streams.schemes",
            ['theme' => ['paths' => $locator->findResources("themes://{$name}", false)]]
        );

        foreach ($schemes as $scheme => $config) {
            if (isset($config['paths'])) {
                $locator->addPath($scheme, '', $config['paths']);
            }
            if (isset($config['prefixes'])) {
                foreach ($config['prefixes'] as $prefix => $paths) {
                    $locator->addPath($scheme, $prefix, $paths);
                }
            }

            if (in_array($scheme, $registered)) {
                stream_wrapper_unregister($scheme);
            }
            $type = !empty($config['type']) ? $config['type'] : 'ReadOnlyStream';
            if ($type[0] != '\\') {
                $type = '\\RocketTheme\\Toolbox\\StreamWrapper\\' . $type;
            }

            if (!stream_wrapper_register($scheme, $type)) {
                throw new \InvalidArgumentException("Stream '{$type}' could not be initialized.");
            }
        }
    }

    protected function loadConfiguration($name, Config $config)
    {
        $themeConfig = CompiledYamlFile::instance("themes://{$name}/{$name}" . YAML_EXT)->content();

        $config->joinDefaults("themes.{$name}", $themeConfig);
    }
}
