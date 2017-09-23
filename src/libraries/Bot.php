<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Common;
use \TeamSpeak3 as TS3;
use \TeamSpeak3_Exception as TS3Exception;

class Bot {

    public static $ts3 = null;

    public static function connect() {
        $uri = Common::$config->connection->uri;

        try {
            Term::stdout('Connecting to TeamSpeak 3 ServerQuery...' . PHP_EOL);
            self::$ts3 = TS3::factory( $uri );
            Term::stdout('Connected to TeamSpeak 3 ServerQuery' . PHP_EOL);
        } catch (TS3Exception $err) {
            self::$ts3 = null;
            Term::stderr(
                'Failed to connect to TeamSpeak 3 ServerQuery' . PHP_EOL
            );
            Term::stderr((string) $err . PHP_EOL);
            return false;
        }

        return true;
    }

    public static function disconnect() {
        if (isset(self::$ts3)) {
            self::$ts3 = null;
        }
    }

}
