<?php
/**
 * Carl's TeamSpeak 3 Bot (TS3Bot)
 * MIT License; see LICENSE file at root of project directory
 * https://github.com/carlbennett/ts3bot
 */

namespace CarlBennett\TS3Bot;

use \CarlBennett\TS3Bot\Libraries\Common;
use \CarlBennett\MVC\Libraries\Term;

function main($argc, $argv) {

  if (php_sapi_name() !== "cli") {
    http_response_code(500);
    exit(
      "This application is only designed for the php-cli package." . PHP_EOL
    );
  }

  if (!file_exists(__DIR__ . "/../lib/autoload.php")) {
    exit(
      "Application misconfigured. Please run `composer install`." . PHP_EOL
    );
  }
  require(__DIR__ . "/../lib/autoload.php");

  Common::$exitCode = 0;

  Term::stdout(sprintf(
    '%s-%s' . PHP_EOL,
    strtolower(Common::getProjectName()),
    Common::getVersionString()
  ));

  // TODO Connect the bot

  while (Common::$exitCode === 0) {
    usleep(1000);
  }

}

exit(main($argc, $argv));
