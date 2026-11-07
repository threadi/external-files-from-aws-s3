# Zugangsdaten per Konstante festlegen

Die Zugangsdaten und Einstellungen jeder Plattform lassen sich als Konstanten festlegen, z. B. in der `wp-config.php`. So landen sie nicht in der Datenbank und nicht in Datenbank-Backups, und jede Umgebung kann eigene Werte haben.

Erfordert _External Files in Media Library_ 5.7.0 oder neuer.

## So funktioniert es

* Eine Konstante ersetzt den Wert aus den Plugin-Einstellungen, dem Benutzerprofil oder einer gespeicherten externen Quelle - in jedem Modus für die Zugangsdaten.
* Per Konstante festgelegte Felder lassen sich im Backend nicht bearbeiten.
* Werte von Zugangsdaten-Feldern (Access Key, Secret Key) werden nie an den Browser übertragen.
* Per Konstante festgelegte Werte werden in neuen externen Quellen nicht gespeichert.

Der Name jeder Konstante setzt sich zusammen aus `EFML_` + Name der Plattform + `_` + Name des Feldes, alles in Großbuchstaben.

## AWS S3

```php
define( 'EFML_AWS_S3_ACCESS_KEY', 'dein-access-key' );
define( 'EFML_AWS_S3_SECRET', 'dein-secret-key' );
define( 'EFML_AWS_S3_BUCKET', 'dein-bucket' );
define( 'EFML_AWS_S3_REGION', 'eu-central-1' );
define( 'EFML_AWS_S3_CLOUDFRONT_DOMAIN', 'cdn.example.com' ); // optional
define( 'EFML_AWS_S3_CLOUDFRONT_DISTRIBUTION_ID', 'E1234567890' ); // optional
```

## Cloudflare R2

```php
define( 'EFML_CLOUDFLARE_R2_ACCOUNT_ID', 'deine-account-id' );
define( 'EFML_CLOUDFLARE_R2_EU', true ); // optional, für EU-Speicher
define( 'EFML_CLOUDFLARE_R2_ACCESS_KEY', 'dein-access-key' );
define( 'EFML_CLOUDFLARE_R2_SECRET', 'dein-secret-key' );
define( 'EFML_CLOUDFLARE_R2_BUCKET', 'dein-bucket' );
define( 'EFML_CLOUDFLARE_R2_PUBLIC_DOMAIN', 'files.example.com' ); // optional
```

## DigitalOcean Spaces

```php
define( 'EFML_DIGITAL_OCEAN_SPACES_ACCESS_KEY', 'dein-access-key' );
define( 'EFML_DIGITAL_OCEAN_SPACES_SECRET', 'dein-secret-key' );
define( 'EFML_DIGITAL_OCEAN_SPACES_BUCKET', 'dein-bucket' );
define( 'EFML_DIGITAL_OCEAN_SPACES_REGION', 'fra1' );
```

## Backblaze S3

```php
define( 'EFML_BACKBLAZE_S3_ACCESS_KEY', 'deine-key-id' );
define( 'EFML_BACKBLAZE_S3_SECRET', 'dein-application-key' );
define( 'EFML_BACKBLAZE_S3_BUCKET', 'dein-bucket' );
define( 'EFML_BACKBLAZE_S3_REGION', 'eu-central-003' );
```

## Hooks

* `efml_service_field_constant_name` - ändert den Namen der Konstante für ein Feld.
* `efml_service_fields` - ändert die Felder samt ihrer Werte, bevor sie verwendet werden.
