# magento-sql-injection-detector
Be notified in minutes if your Magento store database has been compromised by SQL Injection. 

Light weight, very simple, very useful tool. Checks Magento 2 database for changes to content rendered to user. If different alert the admin! (Includes false positives)

Run this on a 10 minute cron e.g. (change to correct path): 
*/10 * * * * /usr/bin/php /path/to/magento-sql-injection-detector/detector.php
