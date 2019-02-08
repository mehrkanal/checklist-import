# Checklist Import Helper

## How to run?

1. Copy the config file and Update your credentials in `config/jira.local.php`.
2. Update your URLs in `src/Jira/JiraClient.php`. I know it should be a config value, but the project was growing and not intended to be released to public.
3. Install dependencies 
4. Copy your `.xml` backup to the root folder. Unfortunately I can't provide more details of the backup process, just got the xml files provided.

:warning: We had two different xml Backups (don't know why). I  added the `compare.php` to check where the difference is.


```bash
cp config/jira.global.php config/jira.local.php
composer install
php import-checklist.php
```

