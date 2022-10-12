<?php
require_once('config.php');
require_once('S3Client.php');

$s3client = new S3Client(
    $ACCESS_KEY, $SECRET_KEY, $HOST, $PORT, $BUCKET
);

echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "putData " . ($s3client->putData('/testdata', 'Hello world!') ? "TRUE" : "FALSE") . "\n";
echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "getData " . $s3client->getData('/testdata') . "\n";
echo "deleteData " . ($s3client->deleteData('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";
