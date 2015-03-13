<?php
namespace Grav\Common;

use Grav\Common\Config\Config;
use Grav\Common\Data\Blueprints;
use Grav\Common\Data\Data;
use Grav\Common\File\CompiledYamlFile;
use RocketTheme\Toolbox\Event\EventDispatcher;
use RocketTheme\Toolbox\Event\EventSubscriberInterface;

/**
 * The Plugins object holds an array of all the plugin objects that
 * Grav knows about
 *
 * @author RocketTheme
 * @license MIT
 */
class Plugins extends Iterator
{
    protected $grav;

    public function __construct(Grav $grav)
    {
        $this->grav = $grav;
    }

    /**
     * Recurses through the plugins directory creating Plugin objects for each plugin it finds.
     *
     * @return array|Plugin[] array of Plugin objects
     * @throws \RuntimeException
     */
    public function init()
    {
        /** @var Config $config */
        $config = $this->grav['config'];
        $plugins = (array) $config->get('plugins');

        /** @var EventDispatcher $events */
        $events = $this->grav['events'];

        foreach ($plugins as $plugin => $data) {
            if (empty($data['enabled'])) {
                // Only load enabled plugins.
                continue;
            }

            $filePath = $this->grav['locator']('plugins://' . $plugin . DS . $plugin . PLUGIN_EXT);
            if (!is_file($filePath)) {
                $this->grav['log']->addWarning(sprintf("Plugin '%s' enabled but not found! Try clearing cache with `bin/grav clear-cache`", $plugin));
                continue;
            }

            require_once $filePath;

            $pluginClassFormat = [
                'Grav\\Plugin\\'.ucfirst($plugin).'Plugin',
                'Grav\\Plugin\\'.Inflector::camelize($plugin).'Plugin'
            ];
            $pluginClassName = false;

            foreach ($pluginClassFormat as $pluginClass) {
                if (class_exists($pluginClass)) {
                    $pluginClassName = $pluginClass;
                    break;
                }
            }

            if (false === $pluginClassName) {
                throw new \RuntimeException(sprintf("Plugin '%s' class not found! Try reinstalling this plugin.", $plugin));
            }

            $instance = new $pluginClassName($plugin, $this->grav, $config);
            if ($instance instanceof EventSubscriberInterface) {
                $events->addSubscriber($instance);
            }
        }

        return $this->items;
    }

    public function add($plugin)
    {
        if (is_object($plugin)) {
            $this->items[get_class($plugin)] = $plugin;
        }
    }

    /**
     * Return list of all plugin data with their blueprints.
     *
     * @return array
     */
    public static function all()
    {
        $list = array();
        $locator = Grav::instance()['locator'];
        $iterator = new \DirectoryIterator($locator->findResource('plugins://', false));

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

    public static function get($name)
    {
        $blueprints = new Blueprints("plugins://{$name}");
        $blueprint = $blueprints->get('blueprints');
        $blueprint->name = $name;

        // Load default configuration.
        $file = CompiledYamlFile::instance("plugins://{$name}/{$name}.yaml");
        $obj = new Data($file->content(), $blueprint);

        // Override with user configuration.
        $file = CompiledYamlFile::instance("user://config/plugins/{$name}.yaml");
        $obj->merge($file->content());

        // Save configuration always to user/config.
        $obj->file($file);

        return $obj;
    }

}
