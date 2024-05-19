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

    public function listKeys($prefix, $nextContinuationToken = false) {
        $method = 'GET';
        $content_type = 'text/xml';
    
        $date = date(DATE_RFC2822);
        $acl = 'x-amz-acl:private';
        $storage_type = 'x-amz-storage-class:STANDARD';
        $sendstr = "$method\n\n$content_type\n$date\n$acl\n$storage_type\n/" . $this->bucket;
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
              "Authorization: AWS " . $this->access_key . ":$signature\r\n"
          ]
        ]);
    
        $continuation_token = '';
        if ($nextContinuationToken != false && !empty($nextContinuationToken)) {
          $continuation_token = '&continuation-token=' . $nextContinuationToken;
        }
    
        $xmlString = @file_get_contents(
            "http://" . $this->host . ":" . $this->port . "/" . $this->bucket . 
            '?list-type=2&max-keys=100&prefix=' . substr($prefix, 1) . $continuation_token, 
            false, $context
        );
        if ($xmlString === false) {
          return [false, []];
        }
        $xml = @simplexml_load_string($xmlString);
        if ($xml === false) {
          return [false, []];
        }
    
        $nextContinuationToken = false;
        if ($xml->IsTruncated[0] == 'true') {
          $nextContinuationToken = $xml->NextContinuationToken[0];
        }
    
        $keyList = [];
    
        foreach ($xml->Contents as $content) {
          array_push($keyList, '/' . (string)$content->Key[0]);
        }
    
        $response = [$nextContinuationToken, $keyList];
        return $response;
    }

    private function copyData($fromKey, $toKey) {
        $method = 'PUT';
        $content_type = 'text/xml';
    
        $date = date(DATE_RFC2822);
        $acl = 'x-amz-acl:private';
        $copy_from = 'x-amz-copy-source:' . $this->bucket . $fromKey;
        $storage_type = 'x-amz-storage-class:STANDARD';
        $sendstr = "$method\n\n$content_type\n$date\n$acl\n$copy_from\n$storage_type\n/" . $this->bucket . $toKey;
        $signature = base64_encode(hash_hmac('sha1', $sendstr, $this->secret_key, true));
    
        $context = stream_context_create([
          'http' => [
            'method'  => $method,
            'header'  =>
              "Host: " . $this->host . "\r\n" .
              "Date: $date\r\n" .
              "Content-Type: $content_type\r\n" .
              "$storage_type\r\n" .
              "$copy_from\r\n" .
              "$acl\r\n" .
              "Authorization: AWS " . $this->access_key . ":$signature\r\n"
          ]
        ]);
    
        return @file_get_contents(
            "http://" . $this->host . ":" . $this->port . "/" . $this->bucket . $toKey, false, $context
        );
    }    

    public function moveData($fromKey, $toKey) {
        if ($this->copyData($fromKey, $toKey)) {
          return $this->deleteData($fromKey);
        }
        return false;
    }
    
    public function moveWithPrefix($fromPrefix, $toPrefix) {
        $nextContinuationToken = '';
        while ($nextContinuationToken !== false) {
          $response = $this->listKeys($fromPrefix, $nextContinuationToken);
    
          $nextContinuationToken = $response[0];
          foreach ($response[1] as $fromKey) {
    
            $toKey = $toPrefix . substr($fromKey, strlen($fromPrefix));
    
            if ($this->moveData($fromKey, $toKey) === false) {
              return false;
            }
          }
        }
        return true;
    }    
    
    public function deleteWithPrefix($prefix) {
        $nextContinuationToken = true;
        while ($nextContinuationToken !== false) {
          $response = $this->listKeys($prefix);
    
          $nextContinuationToken = $response[0];
          foreach ($response[1] as $key) {
            if ($this->deleteData($key) === false) {
              return false;
            }
          }
        }
        return true;
    }
}