<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\MVC\Libraries\Term;
use \StdClass;

class Common {

    public static $config   = null;
    public static $exitCode = 0;

    private function __construct() {}

    public static function getConfig() {
        self::loadConfig('access');
        self::loadConfig('connection');
        self::loadConfig('foul_words');
        self::loadConfig('options');
    }

    private static function loadConfig($node) {
        if (!(self::$config instanceof StdClass)) {
            self::$config = new StdClass();
        }
        $path = getcwd() . '/etc/' . $node . '.json';
        if (!file_exists($path)) {
            self::$config->$node = null;
            Term::stderr(sprintf(
              'Config file does not exist: %s' . PHP_EOL, $path
            ));
        } else {
            self::$config->$node = json_decode(file_get_contents($path));
        }
    }

    public static function getPlatformName() {
        return php_uname('srm');
    }

    public static function getProjectName() {
        return 'TS3Bot';
    }

    public static function getVersionString() {

      if (!file_exists('../.git')) {
        return 'unofficial';
      }

      return trim(shell_exec('git describe --always --tags'), "\r\n");

    }

}
