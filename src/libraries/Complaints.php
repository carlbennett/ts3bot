<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Bot;
use \CarlBennett\TS3Bot\Libraries\Complaint;
use \TeamSpeak3_Exception as TS3Exception;
use \TeamSpeak3_Helper_String as TS3String;

class Complaints {

  protected static function normalize(&$complaints) {
        $normalized = array();
        foreach ($complaints as $item) {
            $normalized[] = new Complaint($item);
        }
        return $normalized;
    }

    public static function refresh() {
        try {
            $complaintList = Bot::$ts3->complaintList();
            Term::stdout('Getting list of complaints...' . PHP_EOL);
        } catch (TS3Exception $err) {
            if ($err->getMessage() === 'database empty result set') {
                $complaintList = array();
            } else {
                $complaintList = null;
                Term::stderr('Failed to receive complaints list' . PHP_EOL);
                Term::stderr((string) $err . PHP_EOL);
            }
        }
        if ($complaintList !== null) {
            Term::stdout(sprintf(
                'Complaints received: %d' . PHP_EOL, count($complaintList)
            ));
        }
        self::normalize($complaintList);
        return $complaintList;
    }

}
