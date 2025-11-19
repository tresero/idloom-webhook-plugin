# Idloom Webhook Handler for WordPress & CiviCRM

This WordPress plugin handles incoming webhooks from Idloom and creates corresponding activities in CiviCRM. It's designed to process registration data and store it in your CiviCRM instance for further processing and review.

## Features

- Processes incoming webhook data from Idloom
- Validates and sanitizes registration data
- Creates CiviCRM activities for registration review
- Handles consent management through custom fields
- Supports comprehensive error logging
- Processes attendee details including contact and address information

## Prerequisites

- WordPress installation
- CiviCRM plugin installed and configured
- Idloom integration set up with webhook capabilities

## Installation

1. Upload the `idloom-webhook.php` file to your WordPress plugins directory (`wp-content/plugins/idloom-webhook/`)
2. Activate the plugin through the WordPress admin panel
3. Configure your Idloom webhook endpoint to point to: `https://your-site.com/wp-json/idloom/v1/webhook`

## Configuration

The plugin uses several custom fields from Idloom:
- `free_field59`: Used for consent to update information
- `free_field12`: Contains cast year information (for alumni)
- `free_field56`: Permission to post on "who's coming" list

## Webhook Data Processing

The plugin processes the following fields from the webhook payload:
- Basic contact information (email, firstname, lastname, phone)
- Address details (street, street number, city, ZIP code, country)
- Custom fields for consent and alumni information

## Security

The plugin implements several security measures:
- Content-Type validation (requires application/json)
- Data sanitization for all incoming fields
- Error logging for debugging purposes
- Consent validation before processing

## Error Handling

The plugin includes comprehensive error handling and logging:
- JSON parsing validation
- CiviCRM API error catching
- Input validation and sanitization
- Detailed error logging for debugging

## API Response Format

### Success Response
```json
{
    "status": "success",
    "message": "Activity created in CiviCRM successfully."
}
```

### Error Response
```json
{
    "code": "error_code",
    "message": "Error description",
    "status": 400
}
```

## Contributing

Feel free to submit issues and pull requests to improve the plugin.

## Author

Jon Griffin

## License

This plugin is licensed under the GPL v2 or later.

## Support

For support questions or bug reports, please open an issue on the GitHub repository.
