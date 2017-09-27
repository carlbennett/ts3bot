<?php

namespace CarlBennett\TS3Bot\Commands;

use \CarlBennett\MVC\Libraries\Term;
use \CarlBennett\TS3Bot\Libraries\Command;
use \TeamSpeak3_Node_Client as TS3Client;

class RestartMusicBot extends Command {

    public function invoke(TS3Client $client, $arguments) {
        Term::stderr('Restarting Music Bot...' . PHP_EOL);
        $client->message('Restarting Music Bot...');

        $shell =
          'docker stop sinusbot && ' .
          'docker rm sinusbot && ' .
          '/data/sinusbot/_start.sh';

        Term::stderr( $shell . PHP_EOL );
        Term::stderr( shell_exec( $shell ) );

        Term::stderr('done!' . PHP_EOL);
        $client->message('done!');
    }

    public function match($command) {
        return ( strtolower($command) == 'restartmusicbot' );
    }

}
