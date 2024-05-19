<?php

class S3AmazonAWS4 {
    private $accessKey;
    private $secretKey;    
    private $host;
    private $port;
    private $bucket;
    private $region;

    public function __construct($accessKey, $secretKey, $host, $port, $bucket, $region) {
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->host = $host;
        $this->port = $port;
        $this->bucket = $bucket;
        $this->region = $region;
    }

    private function signRequest($canonicalRequest, $signDate) {
      $currentDate = gmdate('Ymd');
      $scope =  $currentDate . '/' . $this->region . '/s3/aws4_request';
  
      $dateKey = hash_hmac('sha256', $currentDate, 'AWS4' . $this->secretKey, true);
      $dateRegionKey = hash_hmac('sha256', $this->region, $dateKey, true);
      $dateRegionServiceKey = hash_hmac('sha256', 's3', $dateRegionKey, true);
      $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, true);

      $stringToSign = "AWS4-HMAC-SHA256\n$signDate\n$scope\n" . hash('sha256', $canonicalRequest);      

      return hash_hmac('sha256', $stringToSign, $signingKey, false);
    }
  
    private function makeCall($method, $remotePath, $postData = '', $contentType = 'application/octet-stream') {
        $hashedPayload = hash('sha256', $postData);

        $date = gmdate(DATE_RFC2822);
        $signDate = gmdate('Ymd\THis\Z');
        $uri = '/' . $this->bucket . $remotePath;
        $queryString = '';

        $signingHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $hostSign = "host:" . $this->host;
        $contentSign = "x-amz-content-sha256:$hashedPayload";
        $dateSign = "x-amz-date:$signDate";
        $headersSign = "$hostSign\n$contentSign\n$dateSign\n";
        $canonicalRequest = "$method\n$uri\n$queryString\n$headersSign\n$signingHeaders\n$hashedPayload";

        $signature = $this->signRequest($canonicalRequest, $signDate);

        $currentDate = gmdate('Ymd');
        $scope =  $currentDate . '/' . $this->region . '/s3/aws4_request';

        $headers = [
            "Host: $this->host",
            "Date: $date",
            "Content-Type: $contentType",
            "$contentSign",
            "$dateSign",
            "Authorization: AWS4-HMAC-SHA256 " . 
              "Credential=$this->accessKey/$scope, " .
              "SignedHeaders=$signingHeaders, " .
              "Signature=$signature"
        ];

        array_push($headers, "Content-Length: " . strlen($postData));
                  
        $context = stream_context_create([
            'http' => [
                'method'  => $method,
                'header'  => $headers,
                'content' => $postData
            ]
        ]);
        
        return @file_get_contents('http://' . $this->host . '/' . $this->bucket . $remotePath, false, $context);
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
        $contentType = 'text/xml';

        $hashedPayload = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'; // hash('sha256', '');
        
        $date = gmdate(DATE_RFC2822);
        $signDate = gmdate('Ymd\THis\Z');
        $uri = '/' . $this->bucket;
    
        $continuation_token = '';
        if ($nextContinuationToken != false && !empty($nextContinuationToken)) {
          $continuation_token = '&continuation-token=' . $nextContinuationToken;
        }

        $queryString = 'list-type=2&max-keys=100&prefix=' . urlencode(substr($prefix, 1)) . $continuation_token;

        $signingHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $hostSign = "host:" . $this->host;
        $contentSign = "x-amz-content-sha256:$hashedPayload";
        $dateSign = "x-amz-date:$signDate";
        $headersSign = "$hostSign\n$contentSign\n$dateSign\n";
        $canonicalRequest = "$method\n$uri\n$queryString\n$headersSign\n$signingHeaders\n$hashedPayload";

        $signature = $this->signRequest($canonicalRequest, $signDate);

        $currentDate = gmdate('Ymd');
        $scope = $currentDate . '/' . $this->region . '/s3/aws4_request';

        $headers = [
            "Host: $this->host",
            "Date: $date",
            "Content-Type: $contentType",
            "$contentSign",
            "$dateSign",
            "Authorization: AWS4-HMAC-SHA256 " . 
              "Credential=$this->accessKey/$scope, " .
              "SignedHeaders=$signingHeaders, " .
              "Signature=$signature"
        ];
        
        $context = stream_context_create([
          'http' => [
            'method'  => $method,
            'header'  => $headers
          ]
        ]);
       
        $xmlString = file_get_contents(
            "http://" . $this->host . "/" . $this->bucket . '?' .  $queryString, 
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
        $contentType = 'text/xml';

        $hashedPayload = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'; // hash('sha256', '');
        
        $date = gmdate(DATE_RFC2822);
        $signDate = gmdate('Ymd\THis\Z');
        $uri = '/' . $this->bucket . $toKey;

        $queryString = '';

        $signingHeaders = 'host;x-amz-content-sha256;x-amz-copy-source;x-amz-date';
        $hostSign = "host:" . $this->host;
        $contentSign = "x-amz-content-sha256:$hashedPayload";
        $copyFrom = 'x-amz-copy-source:' . $this->bucket . $fromKey;
        $dateSign = "x-amz-date:$signDate";
        $headersSign = "$hostSign\n$contentSign\n$copyFrom\n$dateSign\n";
        $canonicalRequest = "$method\n$uri\n$queryString\n$headersSign\n$signingHeaders\n$hashedPayload";

        $signature = $this->signRequest($canonicalRequest, $signDate);

        $currentDate = gmdate('Ymd');
        $scope = $currentDate . '/' . $this->region . '/s3/aws4_request';

        $headers = [
            "Host: $this->host",
            "Date: $date",
            "Content-Type: $contentType",
            "$contentSign",
            "$copyFrom",
            "$dateSign",
            "Authorization: AWS4-HMAC-SHA256 " . 
              "Credential=$this->accessKey/$scope, " .
              "SignedHeaders=$signingHeaders, " .
              "Signature=$signature"
        ];
   
        $context = stream_context_create([
          'http' => [
            'method'  => $method,
            'header'  => $headers
          ]
        ]);
    
        return @file_get_contents(
            "http://" . $this->host . "/" . $this->bucket . $toKey, false, $context
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