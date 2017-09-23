<?php

namespace CarlBennett\TS3Bot\Libraries;

class Common {

    public static $config   = null;
    public static $exitCode = 0;

    private function __construct() {}

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
