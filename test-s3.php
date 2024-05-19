<?php
require_once('config.php');
require_once('S3Client.php');
require_once('S3AmazonAWS4.php');

/*
$s3client = new S3Client(
    $ACCESS_KEY, $SECRET_KEY, $HOST, $PORT, $BUCKET
);
*/

$s3client = new S3AmazonAWS4(
    $ACCESS_KEY, $SECRET_KEY, $HOST, $PORT, $BUCKET, $REGION
);


#echo "putData " . ($s3client->putData('', '<CreateBucketConfiguration xmlns="http://s3.amazonaws.com/doc/2006-03-01/"><LocationConstraint>Europe</LocationConstraint></CreateBucketConfiguration >') ? "TRUE" : "FALSE") . "\n";
echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "putData " . ($s3client->putData('/testdata', 'Hello world!') ? "TRUE" : "FALSE") . "\n";
echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "getData " . $s3client->getData('/testdata') . "\n";
echo "deleteData " . ($s3client->deleteData('/testdata') ? "TRUE" : "FALSE") . "\n";
echo "exists " . ($s3client->exists('/testdata') ? "TRUE" : "FALSE") . "\n";

echo "putData1 " . ($s3client->putData('/test/work/data1', 'Hello world!') ? "TRUE" : "FALSE") . "\n";
echo "putData2 " . ($s3client->putData('/test/work/data2', 'Hello world!') ? "TRUE" : "FALSE") . "\n";
echo "putData3 " . ($s3client->putData('/test/work/data3', 'Hello world!') ? "TRUE" : "FALSE") . "\n";
echo "putData4 " . ($s3client->putData('/test/work/data4', 'Hello world!') ? "TRUE" : "FALSE") . "\n";

echo "listKeys /test " . print_r($s3client->listKeys('/test'), true) . "\n";

echo "moveWithPrefix /test/work /test/data " . ($s3client->moveWithPrefix('/test/work', '/test/data') ? "TRUE" : "FALSE") . "\n";

echo "listKeys /test " . print_r($s3client->listKeys('/test'), true) . "\n";

echo "deleteWithPrefix /test " . ($s3client->deleteWithPrefix('/test') ? "TRUE" : "FALSE") . "\n";

echo "listKeys /test " . print_r($s3client->listKeys('/test'), true) . "\n";
