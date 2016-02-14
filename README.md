# TeamSpeak Automation
## Preface
- Uses TeamSpeak ServerQuery protocol to poll and update.
- Finds open complaints and notifies users who have access to view complaints.
- Can move users to an AFK channel based on their inactivity.
- Promote/demote users to/from a server group based on their activeness level.

## Installation
1. Clone the repository locally.
2. Ensure the execute bit is enabled (i.e. unix permissions 0755)
   for ```/teamspeak-automation.sh``` and that ```/usr/bin/php``` exists.
3. Copy the ```/teamspeak-automation.sample.json```
   to `/teamspeak-automation.json``` and update it to your taste.
4. Run the ```/teamspeak-automation.sh``` using cron or your favorite service
   manager.
