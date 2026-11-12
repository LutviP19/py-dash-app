<?php
// Prevent unauthorized browser visits directly navigating to this endpoint routing rule
if (!isset($_SERVER['HTTP_HX_REQUEST'])) {
    http_response_code(403);
    exit('Access Denied: Terminal frame proxy structural call omitted.');
}

$secret    = getenv('APP_SHARED_SECRET');
$userId    = urlencode($_GET['user_id'] ?? '');
$dateRange = urlencode($_GET['date_range'] ?? '');
$expires   = time() + 300; // Token expires in 5 minutes to prevent replay attacks

// Create a cryptographic signature of the payload
$payload   = "{$userId}|{$dateRange}|{$expires}";
$signature = hash_hmac('sha256', $payload, $secret);

$query = http_build_query([
    'user_id'    => $userId,
    'date_range' => $dateRange,
    'expires'    => $expires,
    'sig'        => $signature
]);

// Pass downstream data safely to our independent microservice port tracking configuration
$dashTargetUrl = "http://localhost:8050/?{$query}";
?>
<iframe src="<?php echo $dashTargetUrl; ?>" 
        class="w-full min-h-[680px] border-none bg-transparent"
        loading="lazy">
</iframe>
