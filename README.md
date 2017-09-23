# Carl's TS3Bot
## Summary

This is an MIT-licensed TeamSpeak 3 bot written in php-cli. It acts as a service
daemon on the Linux platform, primarily aimed for CentOS/Fedora distributions.

## What It Does

- Maintains a persistent connection to the TeamSpeak 3 ServerQuery service.
- Actively listens for commands and calculates an appropriate response.
- Proactively monitors the server for a variety of conditions.

## What It Does Not Do

- Record or transmit audio or VoIP communication sent and received in channels.

## Installation

**Prerequisite:** You must have `php` available on the command-line. The php-cli
package should be version 5.6 or better.

1. Download a copy of this repository to a CentOS 7.x or Fedora 25+ server.
2. Copy sample files and replace `.sample` in the filename under `/etc/`.
3. Modify `/etc/` as desired, importantly the connection string.
4. Run `/ts3bot`.

## Service Daemon
### Systemd
Symbolic link the `/etc/ts3bot.service` under `/etc/systemd/system/` and
reload the systemd daemon. Enable and start the service as desired.

## Disclaimer
This bot is licensed under the MIT license. There is no warranty given. This
application is designed with morality, but bugs may cause undefined behavior.
