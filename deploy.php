<?php
$secret = "myPrecious33"; // match GitHub webhook secret
$headers = getallheaders();
$payload = file_get_contents('php://input');

if ($headers['X-Hub-Signature-256'] !== 'sha256=' . hash_hmac('sha256', $payload, $secret)) {
    http_response_code(403);
    exit('Invalid signature.');
}

exec('home/codeopdq/repositories/taxshieldPrinter/deploy.sh 2>&1', $output);
echo implode("\n", $output);
