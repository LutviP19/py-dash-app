<?php 
session_start();

// Prevent unauthorized browser visits directly navigating to this endpoint routing rule
if (!isset($_SERVER['HTTP_HX_REQUEST'])) {
    http_response_code(403);
    exit('Access Denied: Terminal frame proxy structural call omitted.');
}

$secret    = getenv('APP_SHARED_SECRET');
$userId    = urlencode($_GET['user_id'] ?? '');
$startDate = urlencode($_GET['start_date'] ?? null);
$endDate   = urlencode($_GET['end_date'] ?? null);

$_SESSION['user_id'] = (int) $userId;

if($startDate && $endDate) {
    // Combine them back into YYYY-MM-DD_to_YYYY-MM-DD format
    $dateRange = "{$startDate}_to_{$endDate}";
} else {
    $dateRange = urlencode($_GET['date_range'] ?? '');
}

// Create a cryptographic signature of the payload
$expires   = time() + 300; // Token expires in 5 minutes to prevent replay attacks
$payload   = "{$userId}|{$dateRange}|{$expires}";
$signature = hash_hmac('sha256', $payload, $secret);

$query = http_build_query([
    'user_id'    => $userId,
    'date_range' => $dateRange,
    'expires'    => $expires,
    'sig'        => $signature,
    '_t'         => microtime(true) // Prevents browser iframe caching
]);

// Pass downstream data safely to our independent microservice port tracking configuration
$dashTargetUrl = "http://localhost:8050/?{$query}";
?>
<iframe id="dash-iframe"
        src="<?php echo htmlspecialchars($dashTargetUrl, ENT_QUOTES, 'UTF-8'); ?>" 
        class="w-full min-h-[300px] border-none bg-transparent overflow-hidden transition-[height] duration-150"
        scrolling="no"
        loading="lazy">
</iframe>