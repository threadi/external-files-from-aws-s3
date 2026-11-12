# S3-kompatibler Speicher

Neben AWS S3, Backblaze B2, Cloudflare R2 und DigitalOcean Spaces unterstützt dieses Plugin jeden Speicher, der die S3-API anbietet:

* **S3-kompatibler Speicher (eigener Endpunkt):** für jeden Anbieter oder selbst betriebenen Speicher, z. B. MinIO, Garage, Ceph, SeaweedFS, Synology oder TrueNAS.
* **Voreinstellungen** für Anbieter mit festen Endpunkten: Hetzner Object Storage, IONOS Object Storage, OVHcloud Object Storage, Scaleway Object Storage und Wasabi. Eine Voreinstellung ist der S3-kompatible Speicher mit einer Liste der Regionen des Anbieters, sodass der Endpunkt nicht eingegeben werden muss.

Erfordert _External Files in Media Library_ 6.0.0 oder neuer.

## Einstellungen

| Einstellung | Pflicht | Beschreibung |
|---|---|---|
| Endpunkt-URL | ja (nicht bei Voreinstellungen) | Die URL der S3-API, z. B. `https://s3.example.com` oder `http://192.168.1.10:9000`. Ohne Schema wird `https://` verwendet. |
| Region | ja | Die Region des Buckets. `us-east-1` verwenden, wenn der Anbieter keine Regionen kennt (z. B. MinIO). Bei Voreinstellungen erscheint eine Liste der Regionen. |
| Access Key, Secret Key | ja | Die Zugangsdaten für die S3-API. |
| Bucket | ja | Der Name des Buckets. |
| Path-Style-URLs verwenden | nein (nicht bei Voreinstellungen) | Aktiviert (Standard): `https://s3.example.com/bucket/datei.jpg`. Deaktiviert: `https://bucket.s3.example.com/datei.jpg`. Die meisten selbst betriebenen Speicher brauchen Path-Style-URLs. Voreinstellungen nutzen immer die zweite Form. |
| Öffentliche URL | nein | Die URL, unter der die Dateien öffentlich erreichbar sind, z. B. eine eigene Domain oder ein CDN. Leer bedeutet: Die URL des Buckets wird verwendet. |
| Prüfsummen nur senden, wenn erforderlich | nein | Siehe [Prüfsummen](#prüfsummen). |

Wie bei den anderen Plattformen lassen sich die Zugangsdaten für die Website, pro Benutzer oder bei jeder Verbindung angeben und [per Konstante festlegen](constants_de.md).

## Öffentlicher Zugriff für Exporte

Exportierte Dateien müssen öffentlich lesbar sein, weil sie über ihre URL in der Website verwendet werden. Der Export lädt jede Datei wie bei den anderen Plattformen mit der ACL `private` hoch. Der öffentliche Zugriff muss deshalb für den Bucket erlaubt sein, z. B. per Bucket-Richtlinie oder über die Einstellung „öffentlich“ des Anbieters. Ohne öffentlichen Zugriff lassen sich Dateien importieren, aber der Export scheitert.

Beispiel einer Bucket-Richtlinie für öffentlichen Lesezugriff:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": "*",
      "Action": [ "s3:GetObject" ],
      "Resource": [ "arn:aws:s3:::dein-bucket/*" ]
    }
  ]
}
```

## Verbindungstest

In der Verzeichnisansicht des Speichers gibt es die Aktion „Vollständigen Verbindungstest ausführen“. Er durchläuft mit den aktuellen Einstellungen diese Schritte:

1. verbinden und den Bucket auflisten,
2. eine kleine Testdatei `efml-connection-test-….txt` so hochladen, wie es der Export tut,
3. prüfen, ob die Testdatei öffentlich erreichbar ist,
4. die Testdatei löschen.

Jeder Schritt wird mit seinem Ergebnis angezeigt und ins Log geschrieben. Der Test schreibt in den Bucket und läuft deshalb nur, wenn man ihn startet.

Verbindungstest und Log gibt es für alle Plattformen dieses Plugins, also auch für AWS S3, Cloudflare R2, Backblaze B2 und DigitalOcean Spaces. Ist die Testdatei nicht öffentlich erreichbar, lassen sich Dateien trotzdem mit der Option „Dateien privat speichern“ der externen Quelle exportieren.

## Log

Jede fehlgeschlagene Anfrage an den Speicher wird protokolliert mit:

* der Anfrage (z. B. `PutObject`), dem HTTP-Status, dem S3-Fehlercode und der Fehlermeldung,
* der Request-ID und der erweiterten Request-ID des Anbieters (`x-amz-id-2`), mit denen der Anbieter die Anfrage in seinen Logs findet,
* Plattform, Endpunkt, Bucket, Region, den Einstellungen für Path-Style-URLs und Prüfsummen sowie der öffentlichen URL (bei AWS S3, Cloudflare R2, Backblaze B2 und DigitalOcean Spaces: Plattform und Bucket).

Zugangsdaten werden nie protokolliert. Scheitert die Prüfung der öffentlichen Erreichbarkeit bei einem Export oder beim Verbindungstest, werden auch die geprüfte URL und der HTTP-Status protokolliert.

Bei Supportanfragen bitte diese Log-Einträge mitschicken. Sie enthalten alles, was nötig ist, um das Problem zusammen mit dem Anbieter zu analysieren.

## Prüfsummen

Seit Version 3.337 sendet das AWS SDK standardmäßig bei jedem Upload eine CRC32-Prüfsumme mit. Die meisten S3-kompatiblen Anbieter unterstützen das, manche lehnen solche Uploads aber ab, z. B. mit den Fehlercodes `InvalidArgument`, `NotImplemented`, `BadDigest` oder `XAmzContentSHA256Mismatch`.

Nur in diesem Fall „Prüfsummen nur senden, wenn erforderlich“ aktivieren. Das SDK sendet dann nur noch bei Anfragen Prüfsummen, die sie verlangen (`request_checksum_calculation` und `response_checksum_validation` stehen auf `when_required`). Ohne diese Einstellung ändert sich nichts.

So lässt sich prüfen, ob die Einstellung nötig ist:

1. den Verbindungstest mit deaktivierter Einstellung ausführen,
2. scheitert der Schritt „Testdatei hochladen“ mit einem der Fehlercodes oben, die Einstellung aktivieren und den Test wiederholen,
3. klappt der Upload jetzt, die Einstellung beibehalten. Wenn nicht, hat das Problem eine andere Ursache: Einstellung wieder deaktivieren und die Log-Einträge an den Anbieter schicken.

## Voreinstellungen

| Plattform | Region | Endpunkt |
|---|---|---|
| Hetzner Object Storage | `fsn1` (Falkenstein), `nbg1` (Nürnberg), `hel1` (Helsinki) | `https://<region>.your-objectstorage.com` |
| IONOS Object Storage, Contract-owned Buckets | `eu-central-4` (Frankfurt), `eu-central-3` (Berlin), `us-central-1` (Lenexa) | `https://s3.<region>.ionoscloud.com` |
| IONOS Object Storage, User-owned Buckets | `de` (Frankfurt), `eu-central-2` (Berlin), `eu-south-2` (Logroño) | `https://s3.eu-central-1.ionoscloud.com` für `de`, sonst `https://s3.<region>.ionoscloud.com` |
| OVHcloud Object Storage | `gra`, `rbx`, `sbg`, `eu-west-par`, `eu-south-mil`, `de`, `uk`, `waw`, `bhs`, `ca-east-tor`, `sgp`, `ap-southeast-syd`, `ap-south-mum` | `https://s3.<region>.io.cloud.ovh.net` |
| Scaleway Object Storage | `fr-par`, `nl-ams`, `pl-waw`, `it-mil` | `https://s3.<region>.scw.cloud` |
| Wasabi | `us-east-1`, `us-east-2`, `us-central-1`, `us-west-1`, `us-west-2`, `ca-central-1`, `eu-central-1`, `eu-central-2`, `eu-west-1`, `eu-west-2`, `eu-west-3`, `eu-south-1`, `ap-northeast-1`, `ap-northeast-2`, `ap-southeast-1`, `ap-southeast-2` | `https://s3.<region>.wasabisys.com` |

Die URL eines Buckets lautet `https://<bucket>.<Host des Endpunkts>/`. Kommt bei einem Anbieter eine neue Region hinzu, lässt sie sich über den Hook `efmlawss3_s3_compatible_preset_regions` ergänzen, oder man nutzt den S3-kompatiblen Speicher mit eigenem Endpunkt.

## Hooks

* `efmlawss3_s3_compatible_client_configuration` - ändert die Konfiguration des S3-Clients, z. B. Timeouts.
* `efmlawss3_s3_compatible_public_url` - ändert die öffentliche URL einer Datei.
* `efmlawss3_s3_compatible_query_params` - ändert die Abfrage für die Dateiliste.
* `efmlawss3_service_s3_compatible_hide_file` - blendet Dateien in der Verzeichnisansicht aus.
* `efmlawss3_s3_compatible_user_settings` - ändert die Einstellungen im Benutzerprofil.
* `efmlawss3_s3_compatible_preset_regions` - ändert die Regionen einer Voreinstellung.

Jeder Hook bekommt als letzten Parameter das Plattform-Objekt, sodass er sich über `$instance->get_name()` auf eine Plattform beschränken lässt.

## Integrationstests

Neben den Unit-Tests gibt es in `tests/Integration/` Integrationstests gegen einen echten S3-Server. Sie werden übersprungen, solange kein Endpunkt gesetzt ist:

```bash
python3 -m pip install "moto[server]"
python3 -m moto.server -H 127.0.0.1 -p 9000 &
EFMLAWSS3_INTEGRATION_ENDPOINT=http://127.0.0.1:9000 EFMLAWSS3_INTEGRATION_PUBLIC_ACL=1 composer test-integration
```

`EFMLAWSS3_INTEGRATION_ACCESS_KEY` und `EFMLAWSS3_INTEGRATION_SECRET` setzen die Zugangsdaten für andere Server. `EFMLAWSS3_INTEGRATION_PUBLIC_ACL=1` ist nur für Server nötig, die die Bucket-Richtlinie bei anonymen Anfragen nicht beachten (wie Moto). Der GitHub-Workflow „Integration tests“ führt sie bei jedem Pull Request automatisch aus.
