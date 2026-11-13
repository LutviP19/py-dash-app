<?php
session_start();

// 1. Fetch available User IDs from PostgreSQL using PDO
$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbPort = getenv('DB_PORT') ?: '5432';
$dbName = getenv('POSTGRES_DB') ?: 'my_dash_db';
$dbUser = getenv('POSTGRES_USER') ?: 'postgres';
$dbPass = getenv('POSTGRES_PASSWORD') ?: 'password123';

$userIds = [];
try {
    $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbName}";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Fetch distinct user IDs ordered ascending
    $stmt = $pdo->query("SELECT DISTINCT user_id FROM user_transactions ORDER BY user_id ASC");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fallback if DB is temporarily unreachable
    $userIds = null;
}

// Update session if a new user_id was selected via GET/filter, otherwise initialize default
if (isset($_GET['set_session_user'])) {
    $_SESSION['user_id'] = (int) urlencode($_GET['set_session_user']);
    exit; // Used by background htmx session sync
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = !empty($userIds) ? (int) urlencode($_GET['set_session_user'] ?? 42) : 42;
}

// Mock parameters simulating variables extracted from user session contexts
$current_user_id = $_SESSION['user_id'] ?: 42;
$start_date = '2026-10-01';
$end_date = '2026-11-30';
$selected_range  = "{$start_date}_to_{$end_date}";

// Generate a cryptographically secure CSRF token if one doesn't exist yet
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Python Dash App</title>
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.11/dist/htmx.min.js" integrity="sha384-2OatzQy1H+Zd/IIrjr1TcuDGqLXeHhbooAyJY1KdQMKnr4LZ22k31GBLdYKHmVjg" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <style>
        /* Target visibility transitions alongside htmx action flows */
        .htmx-indicator { display: none; }
        .htmx-request .htmx-indicator { display: flex; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 antialiased p-6 md:p-12">

    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Top Parameter Navigation Card -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Enterprise Operations Portal</h1>
                <p class="text-sm text-slate-500 mt-1">Live background bridging infrastructure demo</p>
            </div>
            <div class="flex flex-wrap gap-3 text-xs font-semibold">
                <span id="nav-user-badge" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-indigo-700/10">
                    Active Session: Profile #<?php echo htmlspecialchars($current_user_id); ?>
                </span>
                <span id="nav-range-badge" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-700/10">
                    Range Window: <?php echo htmlspecialchars($start_date . ' to ' . $end_date); ?>
                </span>
            </div>
        </div>


        <!-- CONTROL INPUT FORM UNDER THE IFRAME CONTAINER -->
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80">
            <h2 class="text-base font-bold text-slate-900 mb-4">Dashboard Parameter Filters</h2>
            
            <form id="filter-form"
                  hx-get="dash-fragment.php" 
                  hx-target="#dash-mount" 
                  hx-swap="innerHTML"
                  hx-indicator="#form-spinner" 
                  hx-on::after-request="syncNavigationCard()" 
                  class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                
                <!-- Input User ID -->
                <div>
                    <label for="user_id" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                        User ID
                    </label>
                    <select id="user_id" 
                            name="user_id" 
                            required
                            class="w-full rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                        <?php foreach ($userIds as $uid): ?>
                            <option value="<?php echo htmlspecialchars($uid); ?>" <?php echo ($uid == $current_user_id) ? 'selected' : ''; ?>>
                                User #<?php echo htmlspecialchars($uid); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Input Start Date -->
                <div>
                    <label for="start_date" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                        Start Date
                    </label>
                    <input type="date" 
                           id="start_date" 
                           name="start_date" 
                           value="<?php echo $start_date; ?>" 
                           required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                </div>

                <!-- Input End Date -->
                <div>
                    <label for="end_date" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1.5">
                        End Date
                    </label>
                    <input type="date" 
                           id="end_date" 
                           name="end_date" 
                           value="<?php echo $end_date; ?>" 
                           required
                           class="w-full rounded-lg border border-slate-300 px-3.5 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                </div>

                <!-- Submit button -->
                <div class="flex items-center gap-3">
                    <button type="submit" 
                            class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm px-4 py-2.5 rounded-lg shadow-sm transition-colors cursor-pointer flex justify-center items-center gap-2">
                        <span>Apply Filters</span>
                    </button>
                    <!-- Indikator loading kecil saat submit -->
                    <div id="form-spinner" class="htmx-indicator">
                        <div class="h-5 w-5 animate-spin rounded-full border-2 border-slate-200 border-t-indigo-600"></div>
                    </div>
                </div>

            </form>
        </div>

        <!-- HTMX Asynchronous Mounting Interface Target Block -->
        <div id="dash-mount" 
             class="min-h-[500px] w-full bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center transition-all duration-200"
             hx-get="dash-fragment.php" 
             hx-include="#current-user-id" 
             hx-vals='{"date_range": "<?php echo $selected_range; ?>"}'
             hx-trigger="load, refreshDash" 
             hx-target="#dash-mount" 
             hx-swap="innerHTML"
             hx-indicator="#loading-spinner">
            
            <!-- Tailwind-Styled Interceptor Spinner -->
            <div id="loading-spinner" class="htmx-indicator flex-col items-center gap-3">
                <div class="h-10 w-10 animate-spin rounded-full border-4 border-slate-200 border-t-indigo-600"></div>
                <span class="text-sm font-semibold text-indigo-600 tracking-wide">Connecting Database Pipelines...</span>
            </div>
            
        </div>

    </div>


    <!-- ===================================================================== -->
    <!-- MODAL BOX: INSERT, UPDATE, DELETE FOR $_SESSION['user_id']            -->
    <!-- ===================================================================== -->
    <div id="crud-modal" 
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-xl border border-slate-200 overflow-hidden animate-fadeIn">
            
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">
                        Manage Transactions — 
                        <span id="modal-session-label" class="text-indigo-600">
                            User #<?php echo htmlspecialchars($current_user_id); ?>
                        </span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Scoped to your active PHP session. Dash charts auto-refresh on save.
                    </p>
                </div>
                <button type="button" 
                        onclick="closeCrudModal()" 
                        class="h-8 w-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-600 hover:bg-slate-200/60 text-xl font-bold transition-colors cursor-pointer">
                    &times;
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-6 max-h-[80vh] overflow-y-auto">
                <!-- Toast Alert Banner Inside Modal -->
                <div id="modal-toast" 
                    class="hidden items-center justify-between px-4 py-3 rounded-xl border text-xs font-semibold transition-all duration-200"
                    role="alert">
                    <div class="flex items-center gap-2">
                        <span id="modal-toast-icon"></span>
                        <span id="modal-toast-msg"></span>
                    </div>
                    <button type="button" onclick="hideModalToast()" class="text-current opacity-70 hover:opacity-100 text-base leading-none cursor-pointer">
                        &times;
                    </button>
                </div>
                <!-- Insert / Update Form -->
                <form id="crud-form"
                    hx-post="crud-handler.php"
                    hx-target="#modal-records-list"
                    hx-indicator="#crud-spinner"
                    hx-on::after-request="if(event.detail.successful) resetCrudForm()"
                    class="bg-slate-50 p-4 rounded-xl border border-slate-200 grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    
                    <!-- Hidden State Inputs -->
                    <input type="hidden" id="crud-action" name="action" value="create">
                    <input type="hidden" id="crud-transaction-id" name="transaction_id" value="">
                    <input type="hidden" id="current-user-id" name="user_id" value="<?= $current_user_id; ?>">

                    <!-- Item Name Input -->
                    <div class="md:col-span-5">
                        <label for="crud-item-name" class="block text-xs font-semibold text-slate-600 mb-1">
                            Item Name
                        </label>
                        <input type="text" 
                            id="crud-item-name" 
                            name="item_name" 
                            placeholder="e.g. Cloud GPU Instance" 
                            required
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <!-- Amount Input -->
                    <div class="md:col-span-3">
                        <label for="crud-amount" class="block text-xs font-semibold text-slate-600 mb-1">
                            Amount ($)
                        </label>
                        <input type="number" 
                            step="0.01" 
                            min="0.01"
                            id="crud-amount" 
                            name="amount" 
                            placeholder="49.99" 
                            required
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <!-- Purchase Date Input -->
                    <div class="md:col-span-4">
                        <label for="crud-date" class="block text-xs font-semibold text-slate-600 mb-1">
                            Purchase Date
                        </label>
                        <input type="datetime-local" 
                            id="crud-date" 
                            name="purchase_date" 
                            required
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    </div>

                    <!-- Form Action Buttons -->
                    <div class="md:col-span-12 flex items-center justify-end gap-2 pt-2">
                        <!-- Loading Spinner during Insert/Update -->
                        <div id="crud-spinner" class="htmx-indicator mr-2">
                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600"></div>
                        </div>

                        <button type="button" 
                                id="crud-cancel-btn" 
                                onclick="resetCrudForm()"
                                class="hidden px-3.5 py-2 text-xs font-semibold text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                            Cancel Edit
                        </button>

                        <button type="submit" 
                                id="crud-submit-btn"
                                class="px-4 py-2 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-xs transition-colors cursor-pointer">
                            + Insert Transaction
                        </button>
                    </div>
                </form>

                <!-- Dynamic Table Mount Target (Populated via crud-handler.php) -->
                <div id="modal-records-list">
                    <div class="flex items-center justify-center py-8 text-sm text-slate-400 gap-2">
                        <div class="h-4 w-4 animate-spin rounded-full border-2 border-slate-200 border-t-indigo-600"></div>
                        <span>Loading session records...</span>
                    </div>
                </div>

            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-slate-50 border-t border-slate-200 flex justify-end">
                <button type="button" 
                        onclick="closeCrudModal()" 
                        class="px-4 py-1.5 text-xs font-semibold text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer">
                    Close
                </button>
            </div>

        </div>
    </div>

<script>
// Listen for dynamic height updates sent from inside the Python Dash iframe
    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'DASH_RESIZE') return;

        const iframe = document.getElementById('dash-iframe');
        const mount  = document.getElementById('dash-mount');

        if (iframe && event.data.height > 0) {
            // Add a small 16px buffer to prevent bottom clipping
            const newHeight = Math.ceil(event.data.height) + 16;
            iframe.style.height = `${newHeight}px`;
            
            if (mount) {
                mount.style.minHeight = `${newHeight}px`;
            }
        }
    });

// Sync Header Navigation Card info
    function syncNavigationCard() {
        const uid       = document.getElementById('user_id').value;
        const startDate = document.getElementById('start_date').value;
        const endDate   = document.getElementById('end_date').value;

        // 1. Synchronize the hidden input #current-user-id
        const hiddenUserId = document.getElementById('current-user-id');
        if (hiddenUserId) {
            hiddenUserId.value = uid;
        }

        // 2. Update Navigation Card Badges
        const userBadge  = document.getElementById('nav-user-badge');
        const rangeBadge = document.getElementById('nav-range-badge');

        if (userBadge) {
            userBadge.textContent = `Active Session: Profile #${uid}`;
        }
        if (rangeBadge && startDate && endDate) {
            rangeBadge.textContent = `Range Window: ${startDate} to ${endDate}`;
        }

        // 3. Also keep the CRUD Modal header label in sync if present
        const modalLabel = document.getElementById('modal-session-label');
        if (modalLabel) {
            modalLabel.textContent = `User #${uid}`;
        }
    }

    syncNavigationCard();

    // Sync PHP $_SESSION['user_id'] when User ID dropdown changes
    document.getElementById('user_id').addEventListener('change', function() {
        fetch(`index.php?set_session_user=${encodeURIComponent(this.value)}`);
    });

// Toast CRUD info
    let toastTimeout;

    // Listen for 'showModalToast' triggered by crud-handler.php
    document.body.addEventListener('showModalToast', function(event) {
        const { type, message } = event.detail;
        displayModalToast(type, message);
    });

    // Also catch HTTP errors (e.g., 403 CSRF failure or 500 Server Error)
    document.body.addEventListener('htmx:responseError', function(event) {
        displayModalToast('error', `Request failed (${event.detail.xhr.status}): ${event.detail.xhr.responseText || 'Server error'}`);
    });

    function displayModalToast(type, message) {
        const toast = document.getElementById('modal-toast');
        const icon  = document.getElementById('modal-toast-icon');
        const msg   = document.getElementById('modal-toast-msg');

        // Reset color classes
        toast.classList.remove(
            'hidden',
            'bg-emerald-50', 'text-emerald-800', 'border-emerald-200',
            'bg-rose-50', 'text-rose-800', 'border-rose-200'
        );
        toast.classList.add('flex');

        if (type === 'error') {
            toast.classList.add('bg-rose-50', 'text-rose-800', 'border-rose-200');
            icon.textContent = '⚠️';
        } else {
            toast.classList.add('bg-emerald-50', 'text-emerald-800', 'border-emerald-200');
            icon.textContent = '✅';
        }

        msg.textContent = message;

        // Auto-dismiss after 3.5 seconds
        clearTimeout(toastTimeout);
        toastTimeout = setTimeout(hideModalToast, 3500);
    }

    function hideModalToast() {
        const toast = document.getElementById('modal-toast');
        toast.classList.add('hidden');
        toast.classList.remove('flex');
    }

// Listen for CRUD button clicks coming from inside the Python Dash iframe
    window.addEventListener('message', function(event) {
        if (!event.data || event.data.type !== 'OPEN_CRUD_MODAL') return;

        openCrudModal();

        // If a row's Actions cell was clicked, pre-fill the Edit form automatically
        if (event.data.mode === 'edit' && event.data.row) {
            setTimeout(() => populateEditForm(event.data.row), 150);
        }
    });   

    // 1. Attach Session CSRF Token globally to all htmx requests
    document.body.addEventListener('htmx:configRequest', function(event) {
        event.detail.headers['X-CSRF-Token'] = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
    });

    // 2. Open Modal and load active session transactions
    function openCrudModal() {
        resetCrudForm();
        const modal = document.getElementById('crud-modal');
        const uid = document.getElementById('current-user-id').value;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        htmx.ajax('GET', 'crud-handler.php?action=list&user_id=' + uid, '#modal-records-list');
    }

    // 3. Close Modal
    let hasModalChanges = false;

    // Set flag to true whenever crud-handler.php signals data changed
    document.body.addEventListener('refreshDash', function() {
        hasModalChanges = true;
    });

    function closeCrudModal() {
        const modal = document.getElementById('crud-modal');
        if (!modal.classList.contains('hidden')) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');

            if (hasModalChanges) {
                hasModalChanges = false;
                htmx.trigger('#dash-mount', 'refreshDash');
            }
        }
    }

    // 4. Close Modal on Escape Key press
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeCrudModal();
        }
    });

    // 5. Switch Form to Edit (Update) Mode
    function populateEditForm(row) {
        document.getElementById('crud-action').value = 'update';
        document.getElementById('crud-transaction-id').value = row.transaction_id;
        document.getElementById('crud-item-name').value = row.item_name;
        document.getElementById('crud-amount').value = row.amount;
        document.getElementById('crud-date').value = row.fmt_date;

        const btn = document.getElementById('crud-submit-btn');
        btn.textContent = 'Update Transaction';
        btn.classList.replace('bg-indigo-600', 'bg-emerald-600');
        btn.classList.replace('hover:bg-indigo-700', 'hover:bg-emerald-700');

        document.getElementById('crud-cancel-btn').classList.remove('hidden');
    }

    // 6. Reset Form back to Insert (Create) Mode
    function resetCrudForm() {
        document.getElementById('crud-action').value = 'create';
        document.getElementById('crud-transaction-id').value = '';
        document.getElementById('crud-item-name').value = '';
        document.getElementById('crud-amount').value = '';
        
        // Safely fall back to today's YYYY-MM-DD if start_date is missing or empty
        const startDateInput = document.getElementById('start_date');
        const todayIso = new Date().toISOString().slice(0, 10);
        const defaultDate = (startDateInput && startDateInput.value.trim() !== '') 
            ? startDateInput.value.trim() 
            : todayIso;

        document.getElementById('crud-date').value = `${defaultDate}T12:00`;

        const btn = document.getElementById('crud-submit-btn');
        btn.textContent = '+ Insert Transaction';
        btn.classList.replace('bg-emerald-600', 'bg-indigo-600');
        btn.classList.replace('hover:bg-emerald-700', 'hover:bg-indigo-700');

        document.getElementById('crud-cancel-btn').classList.add('hidden');
    }
</script>
</body>
</html>
