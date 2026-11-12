<?php
// Mock parameters simulating variables extracted from user session contexts
$current_user_id = 42;
$selected_range  = '2026-10-01_to_2026-11-10';
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
        <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200/80 flex items-center justify-between">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">Enterprise Operations Portal</h1>
                <p class="text-sm text-slate-500 mt-1">Live background bridging infrastructure demo</p>
            </div>
            <div class="flex gap-3 text-xs font-semibold">
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-indigo-50 text-indigo-700 ring-1 ring-indigo-700/10">
                    Active Session: Profile #<?php echo $current_user_id; ?>
                </span>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 ring-1 ring-emerald-700/10">
                    Range Window: <?php echo str_replace('_', ' ', $selected_range); ?>
                </span>
            </div>
        </div>

        <!-- HTMX Asynchronous Mounting Interface Target Block -->
        <div id="dash-mount" 
             class="min-h-[500px] w-full bg-white border border-slate-200 rounded-2xl p-6 shadow-sm flex flex-col justify-center items-center transition-all duration-200"
             hx-get="dash-fragment.php" 
             hx-vals='{"user_id": "<?php echo $current_user_id; ?>", "date_range": "<?php echo $selected_range; ?>"}'
             hx-trigger="load"
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

</body>
</html>
