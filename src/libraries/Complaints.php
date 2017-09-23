<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Bot;
use \TeamSpeak3_Exception as TS3Exception;

class Complaints {

    public static function get() {
        try {
            $complaintList = Bot::$ts3->complaintList();
            Term::stdout('Getting list of complaints...' . PHP_EOL);
            Term::stdout(sprintf(
              'Complaints received: %d' . PHP_EOL, count($complaintList)
            ));
        } catch (TS3Exception $err) {
            if ($err->getMessage() === 'database empty result set') {
                $complaintList = array();
                Term::stdout(sprintf(
                  'Complaints received: %d' . PHP_EOL, count($complaintList)
                ));
            } else {
                $complaintList = null;
                Term::stderr('Failed to receive complaints list' . PHP_EOL);
                Term::stderr((string) $err . PHP_EOL);
            }
        }
        return $complaintList;
    }

}
