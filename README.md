# holidaygogogo-codeigniter

1. Copy the environment file
cp .env.example .env

2. Update your credentials in .env
Database host, name, username, password
Any API keys or custom config

3. Run DB patches
php index.php run_sql_patches

4. GoHighLevel sync
   - Users: `php index.php Cron syncGhlUsers`
