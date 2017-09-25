<?php
/**
 * Carl's TeamSpeak 3 Bot (TS3Bot)
 * MIT License; see LICENSE file at root of project directory
 * https://github.com/carlbennett/ts3bot
 */

namespace CarlBennett\TS3Bot;

use \CarlBennett\TS3Bot\Libraries\Bot;
use \CarlBennett\TS3Bot\Libraries\Common;
use \CarlBennett\MVC\Libraries\Term;

function main($argc, $argv) {

  if (php_sapi_name() !== 'cli') {
    http_response_code(500);
    exit(
      'This application is only designed for the php-cli package.' . PHP_EOL
    );
  }

  if (!file_exists(__DIR__ . '/../lib/autoload.php')) {
    exit(
      'Application misconfigured. Please run `composer install`.' . PHP_EOL
    );
  }
  require(__DIR__ . '/../lib/autoload.php');

  Term::stdout(sprintf(
    '%s-%s (%s)' . PHP_EOL,
    strtolower(Common::getProjectName()),
    Common::getVersionString(),
    Common::getPlatformName()
  ));

  Common::$exitCode = 0;
  Common::getConfig();

  if (!Bot::connect()) {
    return 1;
  }

  Bot::setNickname( Common::$config->options->bot_nickname );
  Bot::registerCommands();
  Bot::registerEvents();

  while (Common::$exitCode === 0) {
    Bot::waitForEvents();
    usleep(1000);
  }

  return Common::$exitCode;

}

exit(main($argc, $argv));
