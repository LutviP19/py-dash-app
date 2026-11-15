<?php
session_start();

// $sessionUserId = (int) urlencode($_GET['user_id']) ?? (int) $_POST['user_id'] ?? $_SESSION['user_id'];

// Properly check $_SESSION first, falling back to POST or GET without premature (int) casting
$rawUserId = $_POST['user_id'] ?? $_GET['user_id'] ??  $_SESSION['user_id'] ?? null;

if (empty($rawUserId)) {
    http_response_code(401);
    exit('Unauthorized: No active user session.');
}

$sessionUserId = (int) $rawUserId;
$_SESSION['user_id'] = $sessionUserId;

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Unauthorized: No active user session.');
}

// Validate CSRF Token on POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (empty($sessionToken) || !hash_equals($sessionToken, $requestToken)) {
        http_response_code(403);
        exit('Forbidden: Invalid or missing CSRF token.');
    }
}

$transactionId = (int) ($_POST['transaction_id'] ?? 0);


$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('POSTGRES_DB') ?: 'my_dash_db';
$dbUser = getenv('POSTGRES_USER') ?: 'postgres';
$dbPass = getenv('POSTGRES_PASSWORD') ?: 'password123';

$dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
$pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$action   = $_REQUEST['action'] ?? 'list';
$triggers = [];

// 1. Handle Create, Update, Delete with Toast Feedback
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($action === 'create') {
            $stmt = $pdo->prepare("
                INSERT INTO user_transactions (user_id, item_name, amount, purchase_date) 
                VALUES (:uid, :item, :amt, :pdate)
            ");
            $stmt->execute([
                ':uid'   => $sessionUserId,
                ':item'  => trim($_POST['item_name']),
                ':amt'   => (float)$_POST['amount'],
                ':pdate' => $_POST['purchase_date'] ?: date('Y-m-d H:i:s')
            ]);
            $triggers['refreshDash']    = true;
            $triggers['showModalToast'] = ['type' => 'success', 'message' => 'New transaction added successfully!'];

        } elseif ($action === 'update') {
            $stmt = $pdo->prepare("
                UPDATE user_transactions 
                SET item_name = :item, amount = :amt, purchase_date = :pdate 
                WHERE transaction_id = :tid AND user_id = :uid
            ");
            $stmt->execute([
                ':item'  => trim($_POST['item_name']),
                ':amt'   => (float)$_POST['amount'],
                ':pdate' => $_POST['purchase_date'],
                ':tid'   => (int)$_POST['transaction_id'],
                ':uid'   => $sessionUserId
            ]);
            $triggers['refreshDash']    = true;
            $triggers['showModalToast'] = ['type' => 'success', 'message' => 'Transaction updated successfully!'];

        } elseif ($action === 'delete') {
            $stmt = $pdo->prepare("
                DELETE FROM user_transactions 
                WHERE transaction_id = :tid AND user_id = :uid
            ");
            $stmt->execute([
                ':tid' => (int)$_POST['transaction_id'],
                ':uid' => $sessionUserId
            ]);
            $triggers['refreshDash']    = true;
            $triggers['showModalToast'] = ['type' => 'success', 'message' => 'Transaction deleted.'];
        }
    } catch (PDOException $e) {
        $triggers['showModalToast'] = ['type' => 'error', 'message' => 'Database error: Could not complete action.'];
    }
}

// Emit JSON triggers to htmx
if (!empty($triggers)) {
    header('HX-Trigger: ' . json_encode($triggers));
}

// 2. Count Total Rows for the Current Session User
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM user_transactions WHERE user_id = :uid");
$countStmt->execute([':uid' => $sessionUserId]);
$totalRows = (int) $countStmt->fetchColumn();

// 3. Fetch Limited Recent Records (e.g., 10 rows)
$limit = 10;
$stmt = $pdo->prepare("
    SELECT transaction_id, 
           item_name, 
           amount, 
           TO_CHAR(purchase_date, 'YYYY-MM-DD\"T\"HH24:MI') AS fmt_date, 
           TO_CHAR(purchase_date, 'YYYY-MM-DD HH24:MI') AS display_date 
    FROM user_transactions 
    WHERE user_id = :uid 
    ORDER BY purchase_date DESC
    LIMIT :limit
");
$stmt->bindValue(':uid', $sessionUserId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$shownCount = count($rows);
?>

<!-- Row Summary Info Bar -->
<div class="flex items-center justify-between mb-3 px-1">
    <span class="text-xs font-medium text-slate-500">
        Showing <strong class="text-slate-800"><?php echo $shownCount; ?></strong> of 
        <strong class="text-indigo-600"><?php echo $totalRows; ?></strong> total records for 
        <strong class="text-slate-800">User #<?php echo $sessionUserId; ?></strong>
    </span>
    <?php if ($totalRows > $limit): ?>
        <span class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200/70">
            Latest <?php echo $limit; ?> displayed
        </span>
    <?php endif; ?>
</div>

<!-- Return the updated table HTML (same table structure as before) -->
<div class="overflow-x-auto border border-slate-200 rounded-xl">
    <table class="w-full text-left text-sm text-slate-600">
        <thead class="bg-slate-50 text-xs uppercase text-slate-700 border-b border-slate-200">
            <tr>
                <th class="px-4 py-3">Item Name</th>
                <th class="px-4 py-3">Amount ($)</th>
                <th class="px-4 py-3">Purchase Date</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="4" class="px-4 py-8 text-center text-slate-400">
                        No transactions found for Session User #<?php echo $sessionUserId; ?>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800"><?php echo htmlspecialchars($row['item_name']); ?></td>
                        <td class="px-4 py-3 font-mono text-slate-700">$<?php echo number_format((float)$row['amount'], 2); ?></td>
                        <td class="px-4 py-3 text-slate-500"><?php echo htmlspecialchars($row['display_date']); ?></td>
                        <td class="px-4 py-3 text-right space-x-2">
                            <button type="button"
                                    onclick='populateEditForm(<?php echo json_encode($row, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 transition-colors cursor-pointer">
                                Edit
                            </button>
                            <button type="button"
                                    onclick='openDeleteConfirmModal(<?php echo json_encode([
                                        "transaction_id" => (int)$row["transaction_id"],
                                        "item_name"      => $row["item_name"],
                                        "amount"         => $row["amount"],
                                        "source"         => "crud-modal"
                                    ], JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'
                                    class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-semibold bg-rose-50 text-rose-700 hover:bg-rose-100 transition-colors cursor-pointer">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
