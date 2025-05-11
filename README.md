# Systeme.io Hook Sync

A WordPress plugin that syncs WordPress hooks with Systeme.io CRM contacts and tags.

## Features

- Detects WordPress actions/hooks (user registration, form submissions)
- Sends collected emails to Systeme.io to create/update contacts
- Attaches or removes tags based on triggered hooks
- Admin interface for mapping hooks to tags
- Activity log for debugging
- Supports WPForms, Gravity Forms, Contact Form 7, and user registration

## Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New → Upload Plugin
3. Upload the ZIP file and activate the plugin
4. Go to Settings → Systeme.io Sync to configure

## Requirements

- WordPress 5.0+
- PHP 7.0+
- Systeme.io account with API access

## Usage

1. Enter your Systeme.io API key in the settings
2. Map WordPress hooks to Systeme.io tags
3. The plugin will automatically sync contacts when hooks are triggered

## Supported Hooks

- `user_register` - When a new user registers
- `wpforms_process_complete` - When a WPForms form is submitted
- `gform_after_submission` - When a Gravity Form is submitted
- Custom hooks can be added in the admin interface

## Support

For support, please contact [your support email].# Website-Plugin
