# holidaygogogo-codeigniter

1. Copy the environment file
	cp .env.example .env

2. Update your credentials in .env
   Database host, name, username, password
   Any API keys or custom config

3. Run DB patches
	- Migration: `php index.php run_sql_patches`

4. GoHighLevel sync
	- Users: full sync on every run via `php index.php Cron syncGhlUsers`
	- Contacts recent 3 days sync: `php index.php Cron syncGhlContacts` | Sync full `php index.php Cron syncGhlContacts --full`
	- Conversations recent 3 days sync: `php index.php Cron syncGhlConversations` | Sync full `php index.php Cron syncGhlConversations --full`
