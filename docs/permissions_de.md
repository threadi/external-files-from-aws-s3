# Berechtigungen des Zugriffsschlüssels

Die Formulare aller Plattformen enthalten die Hilfe „Wo finde ich diese Angaben?“. Sie erklärt, wo du die Zugangsdaten deines Speichers bekommst, welche Berechtigungen sie brauchen und typische Stolperfallen. Du findest sie auch im Schnellstart von External Files in Media Library.

## AWS S3: IAM-Richtlinie

Erstelle einen eigenen IAM-Benutzer für deine Website und füge ihm diese Richtlinie als Inline-Richtlinie hinzu. Ersetze `YOUR-BUCKET` durch den Namen deines Buckets. Die Hilfe im Formular enthält den Namen deines Buckets bereits.

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Sid": "ListBucket",
            "Effect": "Allow",
            "Action": [ "s3:ListBucket" ],
            "Resource": "arn:aws:s3:::YOUR-BUCKET"
        },
        {
            "Sid": "UseFiles",
            "Effect": "Allow",
            "Action": [ "s3:GetObject", "s3:GetObjectAcl", "s3:PutObject", "s3:PutObjectAcl", "s3:DeleteObject" ],
            "Resource": "arn:aws:s3:::YOUR-BUCKET/*"
        }
    ]
}
```

| Berechtigung | Wofür |
|---|---|
| `s3:ListBucket` | Verzeichnisansicht, Import, Synchronisierung |
| `s3:GetObject` | Dateien laden, prüfen ob eine Datei existiert, signierte URLs für private Dateien |
| `s3:GetObjectAcl` | prüfen, ob eine Datei öffentlich ist |
| `s3:PutObject`, `s3:PutObjectAcl` | Export (Dateien werden mit einer ACL hochgeladen) |
| `s3:DeleteObject` | Löschen exportierter Dateien |
| `cloudfront:CreateInvalidation` | nur mit Amazon CloudFront: Cache geänderter Dateien leeren, Ressource `arn:aws:cloudfront::*:distribution/DEINE-DISTRIBUTION-ID` |

Wenn du nur Dateien importierst, reichen `s3:ListBucket`, `s3:GetObject` und `s3:GetObjectAcl`.

## Fehlende Berechtigungen

Nennt der Speicher in seiner Fehlermeldung eine fehlende Berechtigung (wie AWS), zeigen Verbindungstest und Protokoll sie an, z. B. „Dem Zugriffsschlüssel fehlt die Berechtigung s3:PutObject“. Ergänze sie in den Berechtigungen des Zugriffsschlüssels.

## Für Entwickler

Mit dem Hook `efmlawss3_iam_policy` änderst du die Richtlinie, die die Hilfe anzeigt.
