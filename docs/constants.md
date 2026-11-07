# Define credentials via constants

You can define the credentials and settings of each platform as constants, e.g. in your `wp-config.php`. This keeps them out of the database and out of database backups, and allows different values per environment.

Requires _External Files in Media Library_ 5.7.0 or newer.

## How it works

* A constant replaces the value from the plugin settings, the user profile or a saved external source - in every mode for the credentials.
* Fields defined via constants could not be edited in the backend.
* Values of credential fields (access key, secret key) are never sent to the browser.
* Values of fields, which are defined via constants, are not saved in new external sources.

The name of each constant is `EFML_` + the name of the platform + `_` + the name of the field, all in uppercase.

## AWS S3

```php
define( 'EFML_AWS_S3_ACCESS_KEY', 'your-access-key' );
define( 'EFML_AWS_S3_SECRET', 'your-secret-key' );
define( 'EFML_AWS_S3_BUCKET', 'your-bucket' );
define( 'EFML_AWS_S3_REGION', 'eu-central-1' );
define( 'EFML_AWS_S3_CLOUDFRONT_DOMAIN', 'cdn.example.com' ); // optional
define( 'EFML_AWS_S3_CLOUDFRONT_DISTRIBUTION_ID', 'E1234567890' ); // optional
```

## Cloudflare R2

```php
define( 'EFML_CLOUDFLARE_R2_ACCOUNT_ID', 'your-account-id' );
define( 'EFML_CLOUDFLARE_R2_EU', true ); // optional, for EU storage
define( 'EFML_CLOUDFLARE_R2_ACCESS_KEY', 'your-access-key' );
define( 'EFML_CLOUDFLARE_R2_SECRET', 'your-secret-key' );
define( 'EFML_CLOUDFLARE_R2_BUCKET', 'your-bucket' );
define( 'EFML_CLOUDFLARE_R2_PUBLIC_DOMAIN', 'files.example.com' ); // optional
```

## DigitalOcean Spaces

```php
define( 'EFML_DIGITAL_OCEAN_SPACES_ACCESS_KEY', 'your-access-key' );
define( 'EFML_DIGITAL_OCEAN_SPACES_SECRET', 'your-secret-key' );
define( 'EFML_DIGITAL_OCEAN_SPACES_BUCKET', 'your-bucket' );
define( 'EFML_DIGITAL_OCEAN_SPACES_REGION', 'fra1' );
```

## Backblaze S3

```php
define( 'EFML_BACKBLAZE_S3_ACCESS_KEY', 'your-key-id' );
define( 'EFML_BACKBLAZE_S3_SECRET', 'your-application-key' );
define( 'EFML_BACKBLAZE_S3_BUCKET', 'your-bucket' );
define( 'EFML_BACKBLAZE_S3_REGION', 'eu-central-003' );
```

## Hooks

* `efml_service_field_constant_name` - change the name of the constant for a field.
* `efml_service_fields` - change the fields incl. their values before they are used.
