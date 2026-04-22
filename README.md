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
	- Contacts recent x days sync: `php index.php Cron syncGhlContacts` | Sync full `php index.php Cron syncGhlContacts --full`. On default is 3 days, but can be changed in the .env file. 3 days is enough just to conver any missed data. 
	- Conversations recent x days sync: `php index.php Cron syncGhlConversations` | Sync full `php index.php Cron syncGhlConversations --full`. On default is 3 days, but can be changed in the .env file. Currently set as 5 days to check all the updates. 
	- Messages recent x days sync: `php index.php Cron syncGhlMessages` | Sync full `php index.php Cron syncGhlMessages --full`. On default is 3 days, but can be changed in the .env file.  Currently set as 5 days to check all the updates. 

5. Process the GoHighLevel data and create leads 
	- Process leads from synced GHL messages: `php index.php Cron process_ghl_leads`
	- Process leads with a custom batch size: `php index.php Cron process_ghl_leads 250`
	- Re-Process leads: `php index.php Cron process_ghl_leads --rebuild`

6. Process the GoHighLevel leads and check for convertion
	- Process Convertion: `process_ghl_lead_conversions`
	- Process Convertion with a custom batch size: `process_ghl_lead_conversions 200`
	- Re-Process convertion: `process_ghl_lead_conversions --rebuild`

I. Default Commands & order 
	php index.php run_sql_patches
	php index.php Cron syncGhlUsers
	php index.php Cron syncGhlContacts --full
	php index.php Cron syncGhlConversations --full
	php index.php Cron syncGhlMessages --full
	php index.php Cron process_ghl_leads
	php index.php Cron process_ghl_lead_conversions

II. Default Daily & order 
	php index.php Cron syncGhlUsers
	php index.php Cron syncGhlContacts
	php index.php Cron syncGhlConversations
	php index.php Cron syncGhlMessages
	php index.php Cron process_ghl_leads
	php index.php Cron process_ghl_lead_conversions
