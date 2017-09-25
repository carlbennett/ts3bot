<?php

namespace CarlBennett\TS3Bot\Libraries;

use \CarlBennett\TS3Bot\Libraries\Complaints;
use \DateTime;
use \DateTimeZone;
use \JsonSerializable;

class Complaint implements JsonSerializable {

    protected $tcldbid;
    protected $tname;
    protected $fcldbid;
    protected $fname;
    protected $message;
    protected $timestamp;

    public function __construct(&$data) {
        $this->tcldbid   = (int)    $metadata['tcldbid'];
        $this->tname     = (string) $metadata['tname'];
        $this->fcldbid   = (int)    $metadata['fcldbid'];
        $this->fname     = (string) $metadata['fname'];
        $this->message   = (string) $metadata['message'];

        $this->timestamp = new DateTime(
            '@' . (int) $metadata['timestamp']
        );
    }

    public function getTargetClientDatabaseId() {
        return $this->tcldbid;
    }

    public function getTargetClientName() {
        return $this->tname;
    }

    public function getSourceClientDatabaseId() {
        return $this->fcldbid;
    }

    public function getSourceClientName() {
        return $this->fname;
    }

    public function getMessage() {
        return $this->message;
    }

    public function getTimestamp() {
        return $this->timestamp;
    }

    public function jsonSerialize() {
        return array(
            'tcldbid'   => $this->tcldbid,
            'tname'     => $this->tname,
            'fcldbid'   => $this->fcldbid,
            'fname'     => $this->fname,
            'message'   => $this->message,
            'timestamp' => $this->timestamp->getTimestamp(),
        );
    }

}
