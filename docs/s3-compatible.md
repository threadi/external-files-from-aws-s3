# S3-compatible storage

Besides AWS S3, Backblaze B2, Cloudflare R2 and DigitalOcean Spaces this plugin supports any storage, which offers the S3 API:

* **S3-compatible storage (own endpoint):** for any provider or self-hosted storage, e.g. MinIO, Garage, Ceph, SeaweedFS, Synology or TrueNAS.
* **Presets** for providers with fixed endpoints: Hetzner Object Storage, IONOS Object Storage, OVHcloud Object Storage, Scaleway Object Storage and Wasabi. A preset is the S3-compatible storage with a list of the regions of the provider, so you do not have to enter the endpoint.

Requires _External Files in Media Library_ 6.0.0 or newer.

## Settings

| Setting | Required | Description |
|---|---|---|
| Endpoint URL | yes (not for presets) | The URL of the S3 API, e.g. `https://s3.example.com` or `http://192.168.1.10:9000`. Without scheme `https://` is used. |
| Region | yes | The region of your bucket. Use `us-east-1`, if your provider does not use regions (e.g. MinIO). For presets a list of the regions is shown. |
| Access key, Secret key | yes | The credentials for the S3 API. |
| Bucket | yes | The name of your bucket. |
| Use path-style URLs | no (not for presets) | Enabled (default): `https://s3.example.com/bucket/file.jpg`. Disabled: `https://bucket.s3.example.com/file.jpg`. Most self-hosted storages need path-style URLs. Presets always use the second form. |
| Public URL | no | The URL, under which the files are public available, e.g. your own domain or a CDN. If empty, the URL of the bucket is used. |
| Send checksums only if required | no | See [Checksums](#checksums). |

As for the other platforms, the credentials could be saved for the website, per user or entered for each connection, and they could be [defined via constants](constants.md).

## Public access for exports

Exported files must be public readable, as they are used via their URL in your website. The export uploads each file with the ACL `private`, like for the other platforms, so the public access must be allowed for the bucket, e.g. via a bucket policy or the setting "public" of your provider. Without public access, files could be imported, but the export fails.

Example of a bucket policy for public read access:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": "*",
      "Action": [ "s3:GetObject" ],
      "Resource": [ "arn:aws:s3:::your-bucket/*" ]
    }
  ]
}
```

## Connection test

In the directory listing of the storage you will find the action "Run complete connection test". It runs these steps with the actual settings:

1. connect and list the bucket,
2. upload a small test file `efml-connection-test-….txt` the same way the export does it,
3. check whether the test file is public available,
4. delete the test file.

Each step is shown with its result and written to the log. The test writes into your bucket, so it only runs if you start it.

## Log

Each failed request to the storage is logged with:

* the request (e.g. `PutObject`), the HTTP status, the S3 error code and the error message,
* the request ID and the extended request ID of the provider (`x-amz-id-2`), with them your provider could find the request in its logs,
* platform, endpoint, bucket, region, the settings for path-style URLs and checksums and the public URL.

Credentials are never logged. If a check of the public availability fails during an export or the connection test, the checked URL and the HTTP status are logged, too.

If you ask for support, please add these log entries, they contain everything, which is necessary to analyse the problem together with your provider.

## Checksums

Since version 3.337 the AWS SDK sends a CRC32 checksum with each upload by default. Most S3-compatible providers support this, but some reject such uploads, e.g. with the error codes `InvalidArgument`, `NotImplemented`, `BadDigest` or `XAmzContentSHA256Mismatch`.

Only in this case enable "Send checksums only if required". The SDK then sends checksums only for requests, which require them (`request_checksum_calculation` and `response_checksum_validation` are set to `when_required`). Without this setting nothing is changed.

How to check whether you need it:

1. run the connection test with the setting disabled,
2. if the step "Upload a test file" fails with one of the error codes above, enable the setting and run the test again,
3. if the upload works now, keep the setting. If not, the problem has another cause, disable the setting again and send the log entries to your provider.

## Presets

| Platform | Region | Endpoint |
|---|---|---|
| Hetzner Object Storage | `fsn1` (Falkenstein), `nbg1` (Nuremberg), `hel1` (Helsinki) | `https://<region>.your-objectstorage.com` |
| IONOS Object Storage, contract-owned buckets | `eu-central-4` (Frankfurt), `eu-central-3` (Berlin), `us-central-1` (Lenexa) | `https://s3.<region>.ionoscloud.com` |
| IONOS Object Storage, user-owned buckets | `de` (Frankfurt), `eu-central-2` (Berlin), `eu-south-2` (Logroño) | `https://s3.eu-central-1.ionoscloud.com` for `de`, otherwise `https://s3.<region>.ionoscloud.com` |
| OVHcloud Object Storage | `gra`, `rbx`, `sbg`, `eu-west-par`, `eu-south-mil`, `de`, `uk`, `waw`, `bhs`, `ca-east-tor`, `sgp`, `ap-southeast-syd`, `ap-south-mum` | `https://s3.<region>.io.cloud.ovh.net` |
| Scaleway Object Storage | `fr-par`, `nl-ams`, `pl-waw`, `it-mil` | `https://s3.<region>.scw.cloud` |
| Wasabi | `us-east-1`, `us-east-2`, `us-central-1`, `us-west-1`, `us-west-2`, `ca-central-1`, `eu-central-1`, `eu-central-2`, `eu-west-1`, `eu-west-2`, `eu-west-3`, `eu-south-1`, `ap-northeast-1`, `ap-northeast-2`, `ap-southeast-1`, `ap-southeast-2` | `https://s3.<region>.wasabisys.com` |

The URL of a bucket is `https://<bucket>.<host of the endpoint>/`. If your provider adds a new region, add it via the hook `efmlawss3_s3_compatible_preset_regions` or use the S3-compatible storage with your own endpoint.

## Hooks

* `efmlawss3_s3_compatible_client_configuration` - change the configuration of the S3 client, e.g. timeouts.
* `efmlawss3_s3_compatible_public_url` - change the public URL of a file.
* `efmlawss3_s3_compatible_query_params` - change the query for the list of files.
* `efmlawss3_service_s3_compatible_hide_file` - hide files in the directory listing.
* `efmlawss3_s3_compatible_user_settings` - change the settings in the user profile.
* `efmlawss3_s3_compatible_preset_regions` - change the regions of a preset.

Each hook gets the platform object as last parameter, so you could use it only for one platform via `$instance->get_name()`.

## Integration tests

Besides the unit tests, there are integration tests against a real S3 server in `tests/Integration/`. They are skipped unless the endpoint is set:

```bash
python3 -m pip install "moto[server]"
python3 -m moto.server -H 127.0.0.1 -p 9000 &
EFMLAWSS3_INTEGRATION_ENDPOINT=http://127.0.0.1:9000 EFMLAWSS3_INTEGRATION_PUBLIC_ACL=1 composer test-integration
```

`EFMLAWSS3_INTEGRATION_ACCESS_KEY` and `EFMLAWSS3_INTEGRATION_SECRET` set the credentials for other servers. `EFMLAWSS3_INTEGRATION_PUBLIC_ACL=1` is only necessary for servers, which do not use the bucket policy for anonymous requests (like Moto). The GitHub workflow "Integration tests" runs them automatically for each pull request.
