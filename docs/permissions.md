# Permissions of the access key

The forms of all platforms contain the help "Where do I find these details?". It explains, where you get the access data of your storage, which permissions they need and typical pitfalls. You find it also in the quickstart of External Files in Media Library.

## AWS S3: IAM policy

Create an own IAM user for your website and add this policy as inline policy. Replace `YOUR-BUCKET` with the name of your bucket. The help in the form already contains the name of your bucket.

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

| Permission | Used for |
|---|---|
| `s3:ListBucket` | directory listing, import, synchronization |
| `s3:GetObject` | loading files, checking whether a file exists, signed URLs for private files |
| `s3:GetObjectAcl` | checking whether a file is public |
| `s3:PutObject`, `s3:PutObjectAcl` | export (files are uploaded with an ACL) |
| `s3:DeleteObject` | deleting exported files |
| `cloudfront:CreateInvalidation` | only with Amazon CloudFront: clearing the cache of changed files, resource `arn:aws:cloudfront::*:distribution/YOUR-DISTRIBUTION-ID` |

If you only import files, `s3:ListBucket`, `s3:GetObject` and `s3:GetObjectAcl` are sufficient.

## Missing permissions

If the storage names a missing permission in its error message (like AWS does), the connection test and the log name it, e.g. "The access key is missing the permission s3:PutObject". Add it to the permissions of the access key.

## For developers

Change the policy shown in the help via the hook `efmlawss3_iam_policy`.
