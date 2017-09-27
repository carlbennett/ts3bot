<?php

namespace CarlBennett\TS3Bot\Events;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Common;
use \CarlBennett\TS3Bot\Libraries\Event;

class ComplainListRefresh extends Event {

    public function __construct() {
        parent::__construct( 'complain_list_refresh', 10.0, true );
    }

    public function invoke() {
        parent::_invoke();

        Term::stdout('Timed Interval' . PHP_EOL);
    }
}
