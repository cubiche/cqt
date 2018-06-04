<?php

/**
 * This file is part of the Cubiche application.
 *
 * Copyright (c) Cubiche
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Cubiche\Tools;

use Symfony\Component\Yaml\Yaml;

/**
 * ConfigUtils class.
 *
 * @author Ivannis Suárez Jérez <ivannis.suarez@gmail.com>
 */
class ConfigUtils
{
    const CONFIG_FILE = 'quality.yml';

    /**
     * @var array
     */
    protected static $config = null;

    /**
     * @param string $task
     * @param array  $defaults
     *
     * @return array|mixed
     */
    public static function getConfig($task, array $defaults = array())
    {
        if (self::$config === null) {
            self::$config = file_exists(self::CONFIG_FILE) ? Yaml::parse(file_get_contents(self::CONFIG_FILE)) : [];
        }

        return isset(self::$config[$task]) ? self::$config[$task] : $defaults;
    }
}
