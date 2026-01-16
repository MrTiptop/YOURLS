# User Role Filter Plugin

Implements role-based access control for YOURLS. Admins can see all links, while regular users can only see links they created.

## Features

- **Role-based filtering**: Admins see all links, regular users see only their own
- **Automatic username tracking**: Links are automatically tagged with the creator's username
- **Database migration**: Automatically adds `username` column to the URL table
- **Backward compatible**: If no admin users are defined, all users are treated as admins

## Installation

1. Copy this plugin directory to `user/plugins/user-role-filter/`
2. Activate the plugin in the YOURLS admin interface

## Configuration

Add the following to your `user/config.php`:

```php
/** Admin users - users with admin role can see all links
 ** Regular users can only see links they created
 ** Define as array of usernames, or leave empty to make all users admins */
$yourls_admin_users = array(
    'admin',
    // Add more admin usernames here, e.g.:
    // 'admin2',
);
```

### Configuration Options

- **`$yourls_admin_users`**: Array of usernames that have admin privileges. If this array is empty or not defined, all users are treated as admins (backward compatibility).

## How It Works

1. **Database Schema**: On activation, the plugin automatically adds a `username` column to the URL table to track link ownership.

2. **Link Creation**: When a link is created, the plugin automatically stores the current user's username with the link.

3. **Link Filtering**: 
   - **Admins**: See all links in the system
   - **Regular Users**: Only see links where `username` matches their own username

4. **Existing Links**: Links created before the plugin was installed will have `username` set to `NULL`. These links will only be visible to admins until they are manually updated.

## Migration of Existing Links

When the plugin is first activated, it automatically:
1. Adds a `username` column to the URL table
2. Updates all existing links (with NULL username) to be assigned to the 'admin' account

If you need to manually update links again (e.g., if new links were created before the plugin was active), you can:

**Option 1: Reset the migration flag (recommended)**
Run this SQL query in your database:
```sql
DELETE FROM yourls_options WHERE option_name = 'user_role_filter_migration_done';
```
Then reload any admin page - the migration will run again.

**Option 2: Manual SQL update**
Run this SQL query to update all NULL usernames to 'admin':
```sql
UPDATE yourls_url SET username = 'admin' WHERE username IS NULL OR username = '';
```

## Notes

- Links created via the API will also be tagged with the authenticated user's username if available
- The plugin uses the `admin_list_where` filter to modify the SQL query for the admin interface
- The username is stored in the database, so filtering is efficient even with large numbers of links
- Existing links are automatically assigned to 'admin' on first plugin activation

## Author

Robbie De Wet
