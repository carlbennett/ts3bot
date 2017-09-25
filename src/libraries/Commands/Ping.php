<?php

namespace CarlBennett\TS3Bot\Libraries\Commands;

use \CarlBennett\TS3Bot\Libraries\Command;
use \TeamSpeak3_Node_Client as TS3Client;

class Ping extends Command {

    public function invoke(TS3Client $client, $arguments) {
        $client->message('Pong!');
    }

    public function match($command) {
        return ( strtolower($command) == 'ping' );
    }

}
