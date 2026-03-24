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
	- Contacts recent sync: `php index.php Cron syncGhlContacts` - pulls by page and stops when the last record on a page is older than `GHL_CONTACTS_SYNC_DAYS` (default `3`).
	- Contacts full sync: `php index.php Cron syncGhlContacts --full` - pulls all pages until the end.
