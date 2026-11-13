# Define credentials via constants

You can define the credentials and settings of each platform as constants, e.g. in your `wp-config.php`. This keeps them out of the database and out of database backups, and allows different values per environment.

Requires _External Files in Media Library_ 6.0.0 or newer. See also the [documentation of the main plugin](https://github.com/threadi/external-files-in-media-library/blob/master/docs/constants.md).

## How it works

* **All required fields:** The constants of a platform are only used if a constant is defined for all required fields (below without "optional"). If one is missing, no constant of this platform is used and the settings page shows which are missing.
* A constant replaces the value from the plugin settings, the user profile or a saved external source.
* The location of the credentials is then fixed to "One for this website". The settings show hints instead of input fields.
* Fields defined via constants could not be edited in the backend.
* **Only for the defined bucket:** Saved external sources for another bucket (for Cloudflare R2 also another account, for DigitalOcean Spaces and Backblaze also another region) are not usable as long as the constants are defined. Opening, import, synchronization and export are blocked then, files imported before are still delivered. Manual imports from other buckets are not possible either.
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
define( 'EFML_DIGITAL_OCEAN_SPACES_PUBLIC_DOMAIN', 'files.example.com' ); // optional
```

## Backblaze S3

```php
define( 'EFML_BACKBLAZE_S3_ACCESS_KEY', 'your-key-id' );
define( 'EFML_BACKBLAZE_S3_SECRET', 'your-application-key' );
define( 'EFML_BACKBLAZE_S3_BUCKET', 'your-bucket' );
define( 'EFML_BACKBLAZE_S3_REGION', 'eu-central-003' );
define( 'EFML_BACKBLAZE_S3_PUBLIC_DOMAIN', 'files.example.com' ); // optional
```

## S3-compatible storage (own endpoint)

The constants are only used for sources with the same endpoint and the same bucket.

```php
define( 'EFML_S3_COMPATIBLE_ENDPOINT', 'https://s3.example.com' );
define( 'EFML_S3_COMPATIBLE_REGION', 'us-east-1' );
define( 'EFML_S3_COMPATIBLE_ACCESS_KEY', 'your-access-key' );
define( 'EFML_S3_COMPATIBLE_SECRET', 'your-secret-key' );
define( 'EFML_S3_COMPATIBLE_BUCKET', 'your-bucket' );
define( 'EFML_S3_COMPATIBLE_PATH_STYLE', 1 ); // optional, 1 = path-style URLs (default), 0 = virtual-hosted URLs
define( 'EFML_S3_COMPATIBLE_PUBLIC_URL', 'https://cdn.example.com' ); // optional
define( 'EFML_S3_COMPATIBLE_CHECKSUM_WHEN_REQUIRED', 1 ); // optional, only if your provider needs it
```

## Hetzner, IONOS, OVHcloud, Scaleway and Wasabi

These presets use the same constants as the S3-compatible storage, but without endpoint and path-style. The constants are only used for sources with the same region and the same bucket. The names start with `EFML_HETZNER_OBJECT_STORAGE_`, `EFML_IONOS_OBJECT_STORAGE_`, `EFML_OVHCLOUD_OBJECT_STORAGE_`, `EFML_SCALEWAY_OBJECT_STORAGE_` or `EFML_WASABI_`.

```php
define( 'EFML_HETZNER_OBJECT_STORAGE_REGION', 'fsn1' );
define( 'EFML_HETZNER_OBJECT_STORAGE_ACCESS_KEY', 'your-access-key' );
define( 'EFML_HETZNER_OBJECT_STORAGE_SECRET', 'your-secret-key' );
define( 'EFML_HETZNER_OBJECT_STORAGE_BUCKET', 'your-bucket' );
define( 'EFML_HETZNER_OBJECT_STORAGE_PUBLIC_URL', 'https://cdn.example.com' ); // optional
define( 'EFML_HETZNER_OBJECT_STORAGE_CHECKSUM_WHEN_REQUIRED', 1 ); // optional
```

The possible regions are listed in [the documentation of the S3-compatible storage](s3-compatible.md#presets).

## Several connections

You can define several connections per platform, e.g. for two buckets. List the names of the connections in `EFML_` + name of the platform + `_CONNECTIONS` and add the name of the connection to the names of its constants. If more than one connection is complete, the form for a new external source shows the field "Connection". Without a chosen connection, the connection matching the bucket (and region or endpoint) is used. Details are in the [documentation of the main plugin](https://github.com/threadi/external-files-in-media-library/blob/master/docs/constants.md#several-connections).

```php
define( 'EFML_AWS_S3_CONNECTIONS', 'images,downloads' );

define( 'EFML_AWS_S3_IMAGES_ACCESS_KEY', 'your-access-key' );
define( 'EFML_AWS_S3_IMAGES_SECRET', 'your-secret-key' );
define( 'EFML_AWS_S3_IMAGES_BUCKET', 'your-image-bucket' );
define( 'EFML_AWS_S3_IMAGES_REGION', 'eu-central-1' );

define( 'EFML_AWS_S3_DOWNLOADS_ACCESS_KEY', 'other-access-key' );
define( 'EFML_AWS_S3_DOWNLOADS_SECRET', 'other-secret-key' );
define( 'EFML_AWS_S3_DOWNLOADS_BUCKET', 'your-download-bucket' );
define( 'EFML_AWS_S3_DOWNLOADS_REGION', 'eu-west-1' );
```

## Hooks

* `efml_service_field_constant_name` - change the name of the constant for a field.
* `efml_service_fields` - change the fields incl. their values before they are used.
