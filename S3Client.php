<?php

class S3Client {
    private $access_key;
    private $secret_key;    
    private $host;
    private $port;
    private $bucket;

    public function __construct($access_key, $secret_key, $host, $port, $bucket) {
        $this->access_key = $access_key;
        $this->secret_key = $secret_key;
        $this->host = $host;
        $this->port = $port;
        $this->bucket = $bucket;
    }

    private function makeCall($method, $remotePath, $postdata = '', $content_type = 'application/octet-stream') {
        $date = date(DATE_RFC2822);
        $acl = 'x-amz-acl:private';
        $storage_type = 'x-amz-storage-class:STANDARD';
        $sendstr = "$method\n\n$content_type\n$date\n$acl\n$storage_type\n/" . $this->bucket . $remotePath;
        $signature = base64_encode(hash_hmac('sha1', $sendstr, $this->secret_key, true));
                
        $context = stream_context_create([
            'http' => [
                'method'  => $method,
                'header'  => 
                    "Host: " . $this->host . "\r\n" . 
                    "Date: $date\r\n" .
                    "Content-Type: $content_type\r\n" . 
                    "$storage_type\r\n" .
                    "$acl\r\n" .
                    "Authorization: AWS " . $this->access_key . ":$signature\r\n",
                'content' => $postdata
            ]
        ]);
        
        return @file_get_contents(
            'http://' . $this->host . ':' . $this->port . '/' . $this->bucket . $remotePath
        , false, $context);
    }

    public function getData($remotePath) {
        return $this->makeCall('GET', $remotePath);
    }
    public function putData($remotePath, $data) {
        $response = $this->makeCall('PUT', $remotePath, $data);
        return $response !== false;
    }
    public function exists($remotePath) {
        $response = $this->makeCall('HEAD', $remotePath);
        return $response !== false;
    }
    public function deleteData($remotePath) {
        $response = $this->makeCall('DELETE', $remotePath);
        return $response !== false;
    }
}