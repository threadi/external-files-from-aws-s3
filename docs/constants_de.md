# Zugangsdaten per Konstante festlegen

Die Zugangsdaten und Einstellungen jeder Plattform lassen sich als Konstanten festlegen, z. B. in der `wp-config.php`. So landen sie nicht in der Datenbank und nicht in Datenbank-Backups, und jede Umgebung kann eigene Werte haben.

Erfordert _External Files in Media Library_ 5.7.0 oder neuer. Siehe auch die [Dokumentation des Hauptplugins](https://github.com/threadi/external-files-in-media-library/blob/master/docs/constants_de.md).

## So funktioniert es

* **Alle Pflichtfelder:** Die Konstanten einer Plattform werden nur genutzt, wenn für alle Pflichtfelder eine Konstante gesetzt ist (unten ohne „optional“). Fehlt eine, wird keine Konstante dieser Plattform genutzt und die Einstellungsseite zeigt, welche fehlen.
* Eine Konstante ersetzt den Wert aus den Plugin-Einstellungen, dem Benutzerprofil oder einer gespeicherten externen Quelle.
* Der Speicherort der Zugangsdaten steht dann fest auf „Eines für die Website“. In den Einstellungen erscheinen Hinweise statt der Eingabefelder.
* Per Konstante festgelegte Felder lassen sich im Backend nicht bearbeiten.
* **Nur für den festgelegten Bucket:** Gespeicherte externe Quellen für einen anderen Bucket (bei Cloudflare R2 auch ein anderes Konto, bei DigitalOcean Spaces und Backblaze auch eine andere Region) sind nicht nutzbar, solange die Konstanten gesetzt sind. Öffnen, Import, Synchronisierung und Export sind dann gesperrt, bereits importierte Dateien werden weiter ausgeliefert. Manuelle Importe aus anderen Buckets sind ebenfalls nicht möglich.
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

## S3-kompatibler Speicher (eigener Endpunkt)

Die Konstanten gelten nur für Quellen mit demselben Endpunkt und demselben Bucket.

```php
define( 'EFML_S3_COMPATIBLE_ENDPOINT', 'https://s3.example.com' );
define( 'EFML_S3_COMPATIBLE_REGION', 'us-east-1' );
define( 'EFML_S3_COMPATIBLE_ACCESS_KEY', 'dein-access-key' );
define( 'EFML_S3_COMPATIBLE_SECRET', 'dein-secret-key' );
define( 'EFML_S3_COMPATIBLE_BUCKET', 'dein-bucket' );
define( 'EFML_S3_COMPATIBLE_PATH_STYLE', 1 ); // optional, 1 = Path-Style-URLs (Standard), 0 = Virtual-Hosted-URLs
define( 'EFML_S3_COMPATIBLE_PUBLIC_URL', 'https://cdn.example.com' ); // optional
define( 'EFML_S3_COMPATIBLE_CHECKSUM_WHEN_REQUIRED', 1 ); // optional, nur wenn dein Anbieter es braucht
```

## Hetzner, IONOS, OVHcloud, Scaleway und Wasabi

Diese Voreinstellungen nutzen dieselben Konstanten wie der S3-kompatible Speicher, aber ohne Endpunkt und Path-Style. Die Konstanten gelten nur für Quellen mit derselben Region und demselben Bucket. Die Namen beginnen mit `EFML_HETZNER_OBJECT_STORAGE_`, `EFML_IONOS_OBJECT_STORAGE_`, `EFML_OVHCLOUD_OBJECT_STORAGE_`, `EFML_SCALEWAY_OBJECT_STORAGE_` oder `EFML_WASABI_`.

```php
define( 'EFML_HETZNER_OBJECT_STORAGE_REGION', 'fsn1' );
define( 'EFML_HETZNER_OBJECT_STORAGE_ACCESS_KEY', 'dein-access-key' );
define( 'EFML_HETZNER_OBJECT_STORAGE_SECRET', 'dein-secret-key' );
define( 'EFML_HETZNER_OBJECT_STORAGE_BUCKET', 'dein-bucket' );
define( 'EFML_HETZNER_OBJECT_STORAGE_PUBLIC_URL', 'https://cdn.example.com' ); // optional
define( 'EFML_HETZNER_OBJECT_STORAGE_CHECKSUM_WHEN_REQUIRED', 1 ); // optional
```

Die möglichen Regionen stehen in der [Dokumentation des S3-kompatiblen Speichers](s3-compatible_de.md#voreinstellungen).

## Hooks

* `efml_service_field_constant_name` - ändert den Namen der Konstante für ein Feld.
* `efml_service_fields` - ändert die Felder samt ihrer Werte, bevor sie verwendet werden.
