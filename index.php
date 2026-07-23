<?php

// Increase session cookie life to 1 week
$sessionLifetime = 604800; 
ini_set('session.gc_maxlifetime', $sessionLifetime);
ini_set('session.cookie_lifetime', $sessionLifetime);

// Initialize session secure check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attempt_chat_login'])) {
    require_once 'db_config.php'; // This now establishes the global $pdo instance
    
    $input_user = trim($_POST['auth_username']);
    $input_pass = trim($_POST['auth_password']);
    
    if (!empty($input_user) && !empty($input_pass)) {
        try {
            // Fetch user data using the $pdo connection established in db_config.php
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$input_user]);
            $user_record = $stmt->fetch();
            
            // Verify password safely matching your application password policy
if ($user_record && password_verify($input_pass, $user_record['password'])) {
    $_SESSION['user_id'] = $user_record['id'];
    $_SESSION['username'] = $user_record['username'];   // <-- CHANGE THIS TO UNIQUE USERNAME
    $_SESSION['full_name'] = $user_record['full_name']; // <-- Store display name separately
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
            } else {
                $login_error = "Invalid credential parameters.";
            }
        } catch (PDOException $e) {
            $login_error = "Database link fault: " . $e->getMessage();
        }
    } else {
        $login_error = "Please fill in all security fields.";
    }
}

$is_logged_in = isset($_SESSION['username']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HRD Lumber & Plywood Monitoring System 2026</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<link rel="stylesheet" href="https://tailwindcss.com/docs/installation">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
        /* Custom scrollbar for database table */
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

    <header class="bg-slate-900 text-white shadow-md px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-emerald-600 rounded-lg text-white">
                <i data-lucide="ship" class="w-6 h-6"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold tracking-tight">HRD Lumber & Plywood</h1>
                <p class="text-xs text-slate-400">Operations & Logistics Monitoring Dashboard (2026)</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="openModal('add')" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg font-medium text-sm flex items-center gap-2 transition-all shadow-lg shadow-emerald-900/20">
                <i data-lucide="plus" class="w-4 h-4"></i> Add New Record
            </button>
            <button onclick="exportToCSV()" class="bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 px-4 py-2 rounded-lg font-medium text-sm flex items-center gap-2 transition-all">
                <i data-lucide="download" class="w-4 h-4"></i> Export CSV
            </button>
        </div>
    </header>

    <main class="flex-1 p-4 md:p-6 space-y-6 max-w-[1600px] mx-auto w-full">

        <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Invoices</span>
                    <h3 class="text-2xl font-bold text-slate-800 mt-1" id="stat-total">0</h3>
                </div>
                <div class="p-3 bg-indigo-50 text-indigo-600 rounded-lg">
                    <i data-lucide="file-text" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">On Time</span>
                    <h3 class="text-2xl font-bold text-emerald-600 mt-1" id="stat-ontime">0</h3>
                </div>
                <div class="p-3 bg-emerald-50 text-emerald-600 rounded-lg">
                    <i data-lucide="check-circle" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Late Shipments</span>
                    <h3 class="text-2xl font-bold text-rose-600 mt-1" id="stat-late">0</h3>
                </div>
                <div class="p-3 bg-rose-50 text-rose-600 rounded-lg">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">With Concern</span>
                    <h3 class="text-2xl font-bold text-amber-600 mt-1" id="stat-concern">0</h3>
                </div>
                <div class="p-3 bg-amber-50 text-amber-600 rounded-lg">
                    <i data-lucide="help-circle" class="w-6 h-6"></i>
                </div>
            </div>

            <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Avg Lead Time</span>
                    <h3 class="text-2xl font-bold text-sky-600 mt-1" id="stat-avg-days">0 Days</h3>
                </div>
                <div class="p-3 bg-sky-50 text-sky-600 rounded-lg">
                    <i data-lucide="calendar-clock" class="w-6 h-6"></i>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm lg:col-span-2">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="font-bold text-slate-800 text-base">Supplier Volume Breakdown</h2>
                    <span class="text-xs text-slate-400">Total active monitoring load</span>
                </div>
                <div class="h-64">
                    <canvas id="supplierChart"></canvas>
                </div>
            </div>

            <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex flex-col justify-between">
                <div>
                    <h2 class="font-bold text-slate-800 text-base mb-4">Data Filters & Controls</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Search Keywords</label>
                            <div class="relative">
                                <i data-lucide="search" class="absolute left-3 top-2.5 w-4 h-4 text-slate-400"></i>
                                <input type="text" id="search-input" oninput="applyFilters()" placeholder="Search Invoice, BL, or Phyto..." class="w-full pl-10 pr-4 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Filter by Supplier</label>
                            <select id="filter-supplier" onchange="applyFilters()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                                <option value="ALL">All Suppliers</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-500 mb-1">Status Reference</label>
                            <select id="filter-status" onchange="applyFilters()" class="w-full px-3 py-2 border border-slate-200 rounded-lg text-sm focus:ring-2 focus:ring-emerald-500 outline-none bg-white">
                                <option value="ALL">All Statuses</option>
                                <option value="COMPLETE">COMPLETE</option>
                                <option value="WITH CONCERN">WITH CONCERN</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100 flex justify-between items-center gap-2 mt-4">
                    <button onclick="resetData()" class="text-xs font-medium text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-1">
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> Reset to Mock Data
                    </button>
                    <span class="text-xs text-slate-400" id="filtered-count-label">Showing all records</span>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="font-bold text-slate-800">Master Shipping Log</h2>
                    <p class="text-xs text-slate-500">Live operational database containing status trackers & timestamps</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium bg-emerald-50 text-emerald-800">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Live Synced
                    </span>
                </div>
            </div>

            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse min-w-[2000px]">
                    <thead>
                        <tr class="bg-slate-100 text-slate-700 text-xs font-semibold uppercase border-b border-slate-200">
                            <th class="px-4 py-3 text-center sticky left-0 bg-slate-100 z-10 w-24">Actions</th>
                            <th class="px-4 py-3">Date Received</th>
                            <th class="px-4 py-3">Supplier</th>
                            <th class="px-4 py-3">Supplier Inv #</th>
                            <th class="px-4 py-3">HRD Inv #</th>
                            <th class="px-4 py-3">BL Number</th>
                            <th class="px-4 py-3">ETD</th>
                            <th class="px-4 py-3">ETA</th>
                            <th class="px-4 py-3 bg-indigo-50/50 text-indigo-900">ETD Proforma</th>
                            <th class="px-4 py-3 bg-indigo-50/50 text-indigo-900">SPS Sent Supplier</th>
                            <th class="px-4 py-3 bg-emerald-50/50 text-emerald-900">SPS Number</th>
                            <th class="px-4 py-3 bg-emerald-50/50 text-emerald-900">SPS Ref #</th>
                            <th class="px-4 py-3 bg-emerald-50/50 text-emerald-900">SPS Issued Date</th>
                            <th class="px-4 py-3 bg-rose-50/50 text-rose-900">Must Ship Out</th>
                            <th class="px-4 py-3 bg-sky-50/50 text-sky-900">Docs Sent Log</th>
                            <th class="px-4 py-3 bg-sky-50/50 text-sky-900">Doc Status</th>
                            <th class="px-4 py-3 bg-slate-50/50">Orig Docs Rec'd</th>
                            <th class="px-4 py-3 bg-slate-50/50">Phyto Number</th>
                            <th class="px-4 py-3 bg-slate-50/50">Orig Docs Transmitted</th>
                            <th class="px-4 py-3 text-center">Row Status</th>
                            <th class="px-4 py-3 text-center">Days to Arrive</th>
                        </tr>
                    </thead>
                    <tbody id="table-body" class="divide-y divide-slate-100 text-sm">
                        </tbody>
                </table>
            </div>

            <div id="empty-state" class="hidden flex flex-col items-center justify-center p-12 text-slate-400">
                <i data-lucide="database-backup" class="w-12 h-12 mb-3 text-slate-300"></i>
                <p class="font-medium text-slate-600">No records match your filters</p>
                <p class="text-xs">Adjust your search parameters or add a new record to get started.</p>
            </div>
        </section>
    </main>

    <div id="form-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex justify-end transition-opacity opacity-0 pointer-events-none duration-300">
        <div class="bg-white w-full max-w-2xl h-full flex flex-col shadow-2xl transform translate-x-full transition-transform duration-300 overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-200 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="font-bold text-slate-800 text-lg" id="modal-title">Add New Tracking Record</h3>
                    <p class="text-xs text-slate-500">Configure logistics, milestone dates, and status codes.</p>
                </div>
                <button onclick="closeModal()" class="p-1 rounded-full hover:bg-slate-200 text-slate-500 transition-colors">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>

            <form id="record-form" onsubmit="handleFormSubmit(event)" class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                <input type="hidden" id="edit-index" value="">

                <div class="space-y-4">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-600 border-b pb-1">1. Shipment Identification</h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Date Received</label>
                            <input type="date" id="form-dateReceived" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Supplier</label>
                            <input type="text" id="form-supplier" required placeholder="e.g. HANWA (JPY-GRN)" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Supplier Invoice Number</label>
                            <input type="text" id="form-supplierInvoice" required placeholder="e.g. EPH849C" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">HRD Invoice Number</label>
                            <input type="text" id="form-hrdInvoice" required placeholder="e.g. HR2601611/HCLW" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-slate-600 mb-1">BL Number</label>
                            <input type="text" id="form-blNumber" placeholder="e.g. OOLU4124729640" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">ETD (Estimated Departure)</label>
                            <input type="date" id="form-etd" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">ETA (Estimated Arrival)</label>
                            <input type="date" id="form-eta" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                    </div>
                </div>
                
                <div class="pt-4 border-t border-slate-200 flex justify-end gap-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium">Save Record</button>
                </div>
            </form>
        </div>
    </div>

	<div id="toast" class="fixed bottom-5 right-5 bg-slate-900 text-white px-4 py-3 rounded-xl shadow-xl flex items-center gap-2 transform translate-y-20 opacity-0 transition-all duration-300 z-50">
        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400" id="toast-icon"></i>
        <span class="text-xs font-medium" id="toast-message">Success!</span>
    </div>

    <div class="fixed bottom-6 right-6 z-40 flex flex-col items-end">
        <div id="chat-box-panel" class="w-80 sm:w-96 h-[480px] bg-white border border-slate-200 rounded-2xl shadow-2xl flex flex-col hidden mb-4 transition-all duration-300 overflow-hidden">
            
            <div class="bg-slate-900 text-white px-4 py-3.5 flex justify-between items-center border-b border-slate-800">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full <?php echo $is_logged_in ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500'; ?>"></div>
                    <div>
                        <h4 class="font-bold text-xs tracking-wide">Group Chat</h4>
                        <p class="text-[10px] text-slate-400">Internal Communication Log</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($is_logged_in): ?>
                        <button onclick="toggleProfileSettings()" title="Edit Display Name" class="text-slate-400 hover:text-slate-200 transition-colors p-1">
                            <i data-lucide="user-cog" class="w-4 h-4"></i>
                        </button>
                        <button onclick="requestSystemNotificationAccess()" id="notify-perm-btn" title="Enable Desktop Alerts" class="text-amber-400 hover:text-amber-200 transition-colors p-1 hidden animate-pulse">
                            <i data-lucide="bell-off" class="w-4 h-4"></i>
                        </button>
                        <button onclick="toggleChatSystem()" class="text-slate-400 hover:text-slate-200 transition-colors p-1">
                            <i data-lucide="minus" class="w-4 h-4"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($is_logged_in): ?>
                <div id="profile-settings-bar" class="bg-slate-800 border-b border-slate-700 px-4 py-2.5 hidden transition-all duration-200">
                    <form onsubmit="updateProfileFullName(event)" class="flex gap-2 items-center">
                        <div class="flex-1">
                            <label class="block text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-0.5">Update Full Name</label>
                            <input type="text" id="profile-fullname-input" placeholder="Enter new full name..." required autocomplete="off" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-2.5 py-1.5 text-[11px] text-white focus:ring-1 focus:ring-emerald-500 outline-none placeholder:text-slate-600">
                        </div>
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white p-2 rounded-xl transition-colors shrink-0 mt-3.5 shadow-md">
                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if (!$is_logged_in): ?>
                <div class="flex-1 flex flex-col justify-center p-6 bg-slate-50 text-xs">
                    <div class="text-center mb-4">
                        <div class="w-10 h-10 bg-slate-900 text-white rounded-xl flex items-center justify-center mx-auto mb-2 shadow-md">
                            <i data-lucide="lock" class="w-5 h-5 text-emerald-400"></i>
                        </div>
                        <h5 class="font-bold text-slate-800 text-sm">Authentication Required</h5>
                        <p class="text-[11px] text-slate-400 mt-0.5">Log in to sync with internal wire channels</p>
                    </div>

                    <?php if (!empty($login_error)): ?>
                        <div class="bg-rose-50 border border-rose-200 text-rose-600 px-3 py-2 rounded-xl mb-3 text-[11px] font-medium flex items-center gap-2">
                            <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
                            <span><?php echo htmlspecialchars($login_error); ?></span>
                        </div>
                    <?php endif; ?>

                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="space-y-3">
                        <input type="hidden" name="attempt_chat_login" value="1">
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">System Username</label>
                            <input type="text" name="auth_username" required autocomplete="off" placeholder="e.g. j.doe" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">Account Password</label>
                            <input type="password" name="auth_password" required placeholder="封封封封" class="w-full border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500 outline-none">
                        </div>
                        <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-2.5 rounded-xl font-bold transition-all shadow-md flex items-center justify-center gap-1.5 mt-2">
                            <span>Access Terminal</span>
                            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div id="chat-messages-container" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50 custom-scrollbar text-xs"></div>

                <div id="attachment-preview-bar" class="bg-slate-100 border-t border-slate-200 px-3 py-2 flex justify-between items-center hidden">
                    <div class="flex items-center gap-2 truncate pr-4">
                        <i data-lucide="file-text" class="w-4 h-4 text-emerald-500 shrink-0" id="preview-file-icon"></i>
                        <span id="preview-file-name" class="text-[10px] text-slate-600 truncate font-medium">file_name.jpg</span>
                    </div>
                    <button onclick="clearSelectedAttachment()" class="text-[10px] text-rose-500 font-semibold hover:underline shrink-0">Remove</button>
                </div>

                <div id="reply-indicator-bar" class="bg-slate-100 border-t border-slate-200 px-3 py-2 flex justify-between items-center hidden">
                    <div class="flex flex-col truncate pr-4 text-left">
                        <span class="text-[10px] font-bold text-slate-600 flex items-center gap-1">
                            <i data-lucide="reply" class="w-3 h-3 text-emerald-500"></i> Replying to <span id="reply-target-user">User</span>
                        </span>
                        <span id="reply-target-text" class="text-[10px] text-slate-400 truncate italic">Message content preview</span>
                    </div>
                    <button onclick="cancelReplyState()" class="text-[10px] text-rose-500 font-semibold hover:underline shrink-0">Cancel</button>
                </div>

                <div id="edit-indicator-bar" class="bg-slate-100 border-t border-slate-200 px-3 py-1.5 flex justify-between items-center hidden">
                    <span class="text-[10px] text-slate-500 flex items-center gap-1">
                        <i data-lucide="pencil" class="w-3 h-3 text-emerald-500"></i> Editing message...
                    </span>
                    <button onclick="cancelEditingState()" class="text-[10px] text-rose-500 font-semibold hover:underline">Cancel</button>
                </div>

<div id="gif-picker-panel" class="bg-white border-t border-slate-200 p-3 hidden">
    <div class="flex justify-between items-center mb-2 pb-1 border-b border-slate-100">
        <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Select a GIF</span>
        <button type="button" onclick="toggleGifPicker()" class="text-slate-400 hover:text-rose-500 text-[10px] font-semibold">Close</button>
    </div>
    <div id="gif-list-grid" class="grid grid-cols-3 gap-2 max-h-32 overflow-y-auto custom-scrollbar">
        </div>
</div>

<form onsubmit="dispatchChatMessage(event)" id="chat-form-element" class="p-3 border-t border-slate-100 flex gap-2 bg-white items-center relative">
    
    <div id="mention-dropdown" class="absolute bottom-full left-3 right-3 bg-white border border-slate-200 rounded-xl shadow-xl max-h-36 overflow-y-auto hidden z-50 mb-1 divide-y divide-slate-50 text-xs">
        </div>

    <div class="flex items-center gap-1 shrink-0">
        <label class="text-slate-400 hover:text-slate-600 transition-colors p-1.5 cursor-pointer hover:bg-slate-50 rounded-xl" title="Attach Document/Media">
            <i data-lucide="paperclip" class="w-4 h-4"></i>
            <input type="file" id="chat-file-input" class="hidden" onchange="handleFileSelectionEvent(this)">
        </label>
        
        <button type="button" onclick="toggleGifPicker()" class="text-slate-400 hover:text-slate-600 transition-colors p-1.5 hover:bg-slate-50 rounded-xl" title="Send a GIF">
            <i data-lucide="image" class="w-4 h-4"></i>
        </button>
    </div>

    <input type="text" id="chat-input-field" placeholder="Write inside wire log..." autocomplete="off" class="flex-1 border border-slate-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-emerald-500 outline-none placeholder:text-slate-400">
    
    <button type="submit" class="bg-slate-900 hover:bg-slate-800 text-white p-2 rounded-xl transition-colors shrink-0">
        <i data-lucide="send" class="w-4 h-4" id="submit-icon"></i>
    </button>
</form>
            <?php endif; ?>
        </div>

        <button onclick="toggleChatSystem()" class="bg-slate-900 hover:bg-slate-800 text-white p-4 rounded-full shadow-2xl relative transition-transform hover:scale-105 active:scale-95 group">
            <i data-lucide="message-square" class="w-6 h-6 group-hover:rotate-12 transition-transform"></i>
            <span id="chat-unread-badge" class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] font-bold h-5 w-5 rounded-full flex items-center justify-center border-2 border-white hidden">0</span>
        </button>
    </div>
    <!-- JavaScript Application Logic -->
    <script>
        // High fidelity mock data representation of the original Excel Sheet (2026 logs)
        const initialMockData = [
            {
                dateReceived: "2026-02-05",
                supplier: "HANWA (JPY-GRN)",
                supplierInvoice: "EPH849C",
                hrdInvoice: "HR2601611/HCLW",
                blNumber: "OOLU4124729640",
                etd: "2026-01-30",
                eta: "2026-02-11",
                proformaEtd: "2026-01-09",
                spsSent: "2025-12-16",
                spsNumber: "W/UKDA251212002",
                spsRef: "ICDABP252344580",
                spsIssued: "2025-12-15",
                mustShipOut: "2026-02-13",
                docsSentLogistics: "2026-02-05",
                docStatus: "OK",
                origDocsReceived: "2026-01-27",
                phyto: "810-91-0003387",
                origDocsTransmitted: "2026-02-06",
                status: "COMPLETE",
                daysBeforeArrival: 6
            },
            {
                dateReceived: "2026-01-13",
                supplier: "INABATA (STENVALLS)",
                supplierInvoice: "X489S13A-23DS",
                hrdInvoice: "HR2601051/INDW",
                blNumber: "MEDU1K755742",
                etd: "2026-01-06",
                eta: "2026-02-26",
                proformaEtd: "2026-01-05",
                spsSent: "2025-12-17",
                spsNumber: "W/UKDA251126002",
                spsRef: "ICDABP25237077",
                spsIssued: "2025-11-26",
                mustShipOut: "2026-01-25",
                docsSentLogistics: "2026-01-14",
                docStatus: "OK",
                origDocsReceived: "2026-02-16",
                phyto: "400958",
                origDocsTransmitted: "2026-02-18",
                status: "COMPLETE",
                daysBeforeArrival: 43
            },
            {
                dateReceived: "2026-02-17",
                supplier: "HANWA (AS TOFTAN)",
                supplierInvoice: "EPH855A",
                hrdInvoice: "HR2602855/HCLW",
                blNumber: "COSU6439639860",
                etd: "2026-02-10",
                eta: "2026-04-25",
                proformaEtd: "2026-01-24",
                spsSent: "2026-01-09",
                spsNumber: "W/UKDA251222007",
                spsRef: "ICDABP252347846",
                spsIssued: "2025-12-26",
                mustShipOut: "2026-02-24",
                docsSentLogistics: "2026-03-19",
                docStatus: "OK",
                origDocsReceived: "2026-04-13",
                phyto: "1214639",
                origDocsTransmitted: "2026-04-24",
                status: "WITH CONCERN",
                daysBeforeArrival: 37
            },
            {
                dateReceived: "2026-02-05",
                supplier: "HANWA (JPY-GRN)",
                supplierInvoice: "EPH858A",
                hrdInvoice: "HR2601610/HCLW",
                blNumber: "OOLU4124715400",
                etd: "2026-01-28",
                eta: "2026-02-09",
                proformaEtd: "2026-01-16",
                spsSent: "2026-01-06",
                spsNumber: "W/UKDA251229008",
                spsRef: "ICDABP252347807",
                spsIssued: "2025-12-30",
                mustShipOut: "2026-02-28",
                docsSentLogistics: "2026-02-05",
                docStatus: "OK",
                origDocsReceived: "2026-01-27",
                phyto: "815-91-0001997/1",
                origDocsTransmitted: "2026-02-06",
                status: "COMPLETE",
                daysBeforeArrival: 4
            },
            {
                dateReceived: "2026-01-29",
                supplier: "HANWA (AS TOFTAN)",
                supplierInvoice: "EPH854A",
                hrdInvoice: "HR2601853/HCLW",
                blNumber: "EGLV501695000242",
                etd: "2026-02-04",
                eta: "2026-04-08",
                proformaEtd: "2026-01-23",
                spsSent: "2026-01-09",
                spsNumber: "W/UKDA251222005",
                spsRef: "ICDABP252347841",
                spsIssued: "2025-12-26",
                mustShipOut: "2026-02-24",
                docsSentLogistics: "2026-02-16",
                docStatus: "OK",
                origDocsReceived: "2026-02-18",
                phyto: "EU EE/2600440",
                origDocsTransmitted: "2026-02-18",
                status: "COMPLETE",
                daysBeforeArrival: 51
            },
            {
                dateReceived: "2026-02-20",
                supplier: "HANWA (STORA ENSO)",
                supplierInvoice: "EPH855B",
                hrdInvoice: "HR2602226/HCLW",
                blNumber: "COSU6416115530",
                etd: "2026-02-18",
                eta: "2026-04-20",
                proformaEtd: "2026-02-07",
                spsSent: "2026-01-12",
                spsNumber: "W/UKDA260109010",
                spsRef: "ICDABP252350531",
                spsIssued: "2026-01-11",
                mustShipOut: "2026-02-26",
                docsSentLogistics: "2026-02-25",
                docStatus: "OK",
                origDocsReceived: "2026-03-09",
                phyto: "1214660",
                origDocsTransmitted: "2026-03-10",
                status: "COMPLETE",
                daysBeforeArrival: 54
            },
            {
                dateReceived: "2026-01-29",
                supplier: "INABATA (HEDLUNDS)",
                supplierInvoice: "X489S14A-23DS",
                hrdInvoice: "HR2601554/INDW",
                blNumber: "MEDU1K721394",
                etd: "2026-01-29",
                eta: "2026-03-23",
                proformaEtd: "2026-01-29",
                spsSent: "2026-01-13",
                spsNumber: "W/UKDA260110004",
                spsRef: "ICDABP252350860",
                spsIssued: "2026-01-13",
                mustShipOut: "2026-03-14",
                docsSentLogistics: "2026-02-04",
                docStatus: "OK",
                origDocsReceived: "2026-03-09",
                phyto: "401290",
                origDocsTransmitted: "2026-03-10",
                status: "COMPLETE",
                daysBeforeArrival: 47
            },
            {
                dateReceived: "2026-02-03",
                supplier: "HANWA (STORA ENSO)",
                supplierInvoice: "EPH855C",
                hrdInvoice: "HR2602092/HCLW",
                blNumber: "COSU6442496660",
                etd: "2026-02-10",
                eta: "2026-04-13",
                proformaEtd: "2026-02-07",
                spsSent: "2026-01-17",
                spsNumber: "W/UKDA260109011",
                spsRef: "ICDABP252350532",
                spsIssued: "2026-01-11",
                mustShipOut: "2026-02-26",
                docsSentLogistics: "2026-02-13",
                docStatus: "OK",
                origDocsReceived: "2026-02-20",
                phyto: "1214672",
                origDocsTransmitted: "2026-02-25",
                status: "COMPLETE",
                daysBeforeArrival: 59
            },
            {
                dateReceived: "2026-02-20",
                supplier: "HANWA (STORA ENSO)",
                supplierInvoice: "EPH856B",
                hrdInvoice: "HR2602227/HCLW",
                blNumber: "COSU6443339420",
                etd: "2026-02-18",
                eta: "2026-04-20",
                proformaEtd: "2026-02-15",
                spsSent: "2026-01-27",
                spsNumber: "W/UKDA260121008",
                spsRef: "ICDABP252355148",
                spsIssued: "2026-01-26",
                mustShipOut: "2026-02-27",
                docsSentLogistics: "2026-02-25",
                docStatus: "OK",
                origDocsReceived: "2026-03-09",
                phyto: "1214680",
                origDocsTransmitted: "2026-03-10",
                status: "COMPLETE",
                daysBeforeArrival: 54
            },
            {
                dateReceived: "2026-02-20",
                supplier: "HANWA (STORA CEZCH REP.)",
                supplierInvoice: "EPH865A-1",
                hrdInvoice: "HR2603128/HCLW",
                blNumber: "COSU6443974700",
                etd: "2026-02-20",
                eta: "2026-04-27",
                proformaEtd: "2026-02-18",
                spsSent: "2026-01-29",
                spsNumber: "W/UKDA260129013",
                spsRef: "ICDABP252356125",
                spsIssued: "2026-01-21",
                mustShipOut: "2026-02-22",
                docsSentLogistics: "2026-03-18",
                docStatus: "OK",
                origDocsReceived: "2026-03-25",
                phyto: "314983",
                origDocsTransmitted: "2026-03-26",
                status: "COMPLETE",
                daysBeforeArrival: 40
            },
            {
                dateReceived: "2026-02-20",
                supplier: "HANWA (STORA CEZCH REP.)",
                supplierInvoice: "EPH865A-2",
                hrdInvoice: "HR2603129/HCLW",
                blNumber: "COSU6444677270",
                etd: "2026-02-20",
                eta: "2026-04-27",
                proformaEtd: "2026-02-18",
                spsSent: "2026-01-30",
                spsNumber: "W/UKDA260129001",
                spsRef: "ICDABP252357135",
                spsIssued: "2026-01-30",
                mustShipOut: "2026-03-31",
                docsSentLogistics: "2026-03-18",
                docStatus: "OK",
                origDocsReceived: "2026-03-25",
                phyto: "314984",
                origDocsTransmitted: "2026-03-26",
                status: "COMPLETE",
                daysBeforeArrival: 40
            },
            {
                dateReceived: "2026-02-20",
                supplier: "HANWA (STORA ENSO)",
                supplierInvoice: "EPH855A",
                hrdInvoice: "HR2602855/HCLW",
                blNumber: "COSU6442837210",
                etd: "2026-02-18",
                eta: "2026-04-25",
                proformaEtd: "2026-02-15",
                spsSent: "2026-01-23",
                spsNumber: "W/UKDA260121007",
                spsRef: "ICDABP252341183",
                spsIssued: "2026-01-21",
                mustShipOut: "2026-02-22",
                docsSentLogistics: "2026-03-27",
                docStatus: "OK",
                origDocsReceived: "2026-10-10",
                phyto: "1214704",
                origDocsTransmitted: "2026-04-24",
                status: "WITH CONCERN",
                daysBeforeArrival: 57
            }
        ];

        // Global State variables
        let trackingData = [];
        let activeChart = null;

        // Initialize App on DOM Load
        window.onload = function() {
            lucide.createIcons();
            loadLocalStorageData();
            populateSupplierFilter();
            renderDashboard();
        }

        // Handle State Persistence gracefully using state representation
        function loadLocalStorageData() {
            const saved = localStorage.getItem('hrd_tracking_data');
            if (saved) {
                trackingData = JSON.parse(saved);
            } else {
                trackingData = [...initialMockData];
                saveToLocalStorage();
            }
        }

        function saveToLocalStorage() {
            localStorage.setItem('hrd_tracking_data', JSON.stringify(trackingData));
        }

        function resetData() {
            trackingData = [...initialMockData];
            saveToLocalStorage();
            populateSupplierFilter();
            renderDashboard();
            showToast("System successfully reset to default mock records.");
        }

        // Date Parser to beautifully format dates on screens
        function formatDate(dateString) {
            if (!dateString) return "-";
            const options = { day: '2-digit', month: 'short', year: 'numeric' };
            const date = new Date(dateString);
            return isNaN(date.getTime()) ? dateString : date.toLocaleDateString('en-GB', options).replace(/ /g, '-');
        }

        // Generate / Update Global Dashboard Indicators & Live Table Rendering
        function renderDashboard() {
            const tbody = document.getElementById("table-body");
            tbody.innerHTML = "";

            // Get filter values
            const searchQuery = document.getElementById("search-input").value.toLowerCase();
            const selectedSupplier = document.getElementById("filter-supplier").value;
            const selectedStatus = document.getElementById("filter-status").value;

            let onTimeCount = 0;
            let lateCount = 0;
            let concernCount = 0;
            let totalDays = 0;
            let calculatedCount = 0;

            const filteredData = trackingData.filter(row => {
                // Global search matches key elements
                const matchesSearch = 
                    row.supplier.toLowerCase().includes(searchQuery) ||
                    row.supplierInvoice.toLowerCase().includes(searchQuery) ||
                    row.hrdInvoice.toLowerCase().includes(searchQuery) ||
                    (row.blNumber && row.blNumber.toLowerCase().includes(searchQuery)) ||
                    (row.phyto && row.phyto.toLowerCase().includes(searchQuery));

                const matchesSupplier = (selectedSupplier === "ALL") || (row.supplier === selectedSupplier);
                const matchesStatus = (selectedStatus === "ALL") || (row.status === selectedStatus);

                return matchesSearch && matchesSupplier && matchesStatus;
            });

            // Iterate over complete list to compile metrics (independent of filtered view, for high-level management context)
            trackingData.forEach(row => {
                // Calculate Days to Arrive dynamically if missing
                let days = row.daysBeforeArrival;
                if (!days && row.dateReceived && row.eta) {
                    const start = new Date(row.dateReceived);
                    const end = new Date(row.eta);
                    const diffTime = Math.abs(end - start);
                    days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                }

                if (row.status === "WITH CONCERN") concernCount++;
                
                // On-Time vs Late Logistics performance math logic
                if (row.docStatus === "OK") {
                    onTimeCount++;
                } else if (row.docStatus === "LATE") {
                    lateCount++;
                }

                if (days) {
                    totalDays += Number(days);
                    calculatedCount++;
                }
            });

            // Update UI Statistics
            document.getElementById("stat-total").innerText = trackingData.length;
            document.getElementById("stat-ontime").innerText = onTimeCount;
            document.getElementById("stat-late").innerText = lateCount;
            document.getElementById("stat-concern").innerText = concernCount;
            document.getElementById("stat-avg-days").innerText = calculatedCount > 0 ? Math.round(totalDays / calculatedCount) + " Days" : "N/A";

            // Populate Table Rows
            if (filteredData.length === 0) {
                document.getElementById("empty-state").classList.remove("hidden");
            } else {
                document.getElementById("empty-state").classList.add("hidden");
                
                filteredData.forEach((row, index) => {
                    const realIndex = trackingData.indexOf(row);
                    const tr = document.createElement("tr");
                    
                    // Style row based on "WITH CONCERN" overall flag
                    const rowBgClass = row.status === "WITH CONCERN" 
                        ? "bg-rose-50/70 hover:bg-rose-100/70 transition-colors" 
                        : "hover:bg-slate-50 transition-colors";

                    // Determine dynamic ETA vs Today progress or specified
                    let days = row.daysBeforeArrival;
                    if (!days && row.dateReceived && row.eta) {
                        const start = new Date(row.dateReceived);
                        const end = new Date(row.eta);
                        const diffTime = Math.abs(end - start);
                        days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                    }

                    tr.className = `${rowBgClass} border-b border-slate-100 text-slate-700`;
                    tr.innerHTML = `
                        <!-- Sticky Action Control Column -->
                        <td class="px-4 py-3 text-center sticky left-0 ${row.status === 'WITH CONCERN' ? 'bg-rose-100/90' : 'bg-white'} z-10 shadow-[2px_0_5px_rgba(0,0,0,0.05)]">
                            <div class="flex justify-center items-center gap-2">
                                <button onclick="openModal('edit', ${realIndex})" class="p-1 text-slate-500 hover:text-emerald-600 rounded hover:bg-slate-100 transition-colors" title="Edit row">
                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                </button>
                                <button onclick="deleteRecord(${realIndex})" class="p-1 text-slate-500 hover:text-rose-600 rounded hover:bg-slate-100 transition-colors" title="Delete row">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap font-medium">${formatDate(row.dateReceived)}</td>
                        <td class="px-4 py-3 whitespace-nowrap">${row.supplier}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-600">${row.supplierInvoice}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-600">${row.hrdInvoice}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-500">${row.blNumber || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap">${formatDate(row.etd)}</td>
                        <td class="px-4 py-3 whitespace-nowrap">${formatDate(row.eta)}</td>
                        
                        <!-- Proforma Invoice Segment -->
                        <td class="px-4 py-3 whitespace-nowrap bg-indigo-50/20 text-slate-600">${formatDate(row.proformaEtd)}</td>
                        <td class="px-4 py-3 whitespace-nowrap bg-indigo-50/20 text-slate-600">${formatDate(row.spsSent)}</td>
                        
                        <!-- SPS Details Segment -->
                        <td class="px-4 py-3 whitespace-nowrap bg-emerald-50/20 font-mono text-xs text-slate-600">${row.spsNumber || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap bg-emerald-50/20 font-mono text-xs text-slate-600">${row.spsRef || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap bg-emerald-50/20 text-slate-600">${formatDate(row.spsIssued)}</td>
                        <td class="px-4 py-3 whitespace-nowrap bg-rose-50/20 text-rose-600 font-semibold">${formatDate(row.mustShipOut)}</td>
                        
                        <!-- Logistics Reference -->
                        <td class="px-4 py-3 whitespace-nowrap bg-sky-50/20 text-slate-600">${formatDate(row.docsSentLogistics)}</td>
                        <td class="px-4 py-3 whitespace-nowrap bg-sky-50/20">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold ${row.docStatus === 'OK' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                                ${row.docStatus || 'OK'}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-500">${formatDate(row.origDocsReceived)}</td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-slate-600">${row.phyto || '-'}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-indigo-600 hover:underline cursor-pointer font-medium">${formatDate(row.origDocsTransmitted)}</td>
                        
                        <!-- Overall Row Status Badge -->
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold ${row.status === 'COMPLETE' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'}">
                                ${row.status}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap font-semibold text-slate-800">${days || '-'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            }

            document.getElementById("filtered-count-label").innerText = `Showing ${filteredData.length} of ${trackingData.length} records`;
            
            // Reinitialize Dynamic Icons injected into the DOM
            lucide.createIcons();

            // Rerender analytics Chart.js component
            renderSupplierChart();
        }

        // Dynamically pull Suppliers list for operational Filter consistency
        function populateSupplierFilter() {
            const filter = document.getElementById("filter-supplier");
            filter.innerHTML = '<option value="ALL">All Suppliers</option>';
            
            const suppliers = [...new Set(trackingData.map(r => r.supplier))].sort();
            suppliers.forEach(supp => {
                const opt = document.createElement("option");
                opt.value = supp;
                opt.innerText = supp;
                filter.appendChild(opt);
            });
        }

        // Search inputs / Filter events handling
        function applyFilters() {
            renderDashboard();
        }

        // Beautiful Interactive Charts Powered by Chart.js (CDN)
        function renderSupplierChart() {
            const ctx = document.getElementById('supplierChart').getContext('2d');
            
            // Compile metric distributions
            const supplierCounts = {};
            trackingData.forEach(row => {
                supplierCounts[row.supplier] = (supplierCounts[row.supplier] || 0) + 1;
            });

            const labels = Object.keys(supplierCounts);
            const counts = Object.values(supplierCounts);

            // Standardize responsive canvas destruction to avoid layering collisions
            if (activeChart) {
                activeChart.destroy();
            }

            activeChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Active Logged Shipments',
                        data: counts,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)', // Emerald
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        barPercentage: 0.6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                stepSize: 1,
                                color: '#64748b'
                            },
                            grid: {
                                color: '#f1f5f9'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#64748b',
                                font: {
                                    size: 10
                                }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        // Trigger Modals
        function openModal(mode, index = null) {
            const modal = document.getElementById("form-modal");
            const form = document.getElementById("record-form");
            const title = document.getElementById("modal-title");
            form.reset();

            if (mode === 'add') {
                title.innerText = "Add New Tracking Record";
                document.getElementById("edit-index").value = "";
            } else if (mode === 'edit' && index !== null) {
                title.innerText = "Modify Tracking Record";
                document.getElementById("edit-index").value = index;

                // Pre-fill fields
                const data = trackingData[index];
                document.getElementById("form-dateReceived").value = data.dateReceived || "";
                document.getElementById("form-supplier").value = data.supplier || "";
                document.getElementById("form-supplierInvoice").value = data.supplierInvoice || "";
                document.getElementById("form-hrdInvoice").value = data.hrdInvoice || "";
                document.getElementById("form-blNumber").value = data.blNumber || "";
                document.getElementById("form-etd").value = data.etd || "";
                document.getElementById("form-eta").value = data.eta || "";
                document.getElementById("form-proformaEtd").value = data.proformaEtd || "";
                document.getElementById("form-spsSent").value = data.spsSent || "";
                document.getElementById("form-spsNumber").value = data.spsNumber || "";
                document.getElementById("form-spsRef").value = data.spsRef || "";
                document.getElementById("form-spsIssued").value = data.spsIssued || "";
                document.getElementById("form-mustShipOut").value = data.mustShipOut || "";
                document.getElementById("form-docsSentLogistics").value = data.docsSentLogistics || "";
                document.getElementById("form-docStatus").value = data.docStatus || "OK";
                document.getElementById("form-origDocsReceived").value = data.origDocsReceived || "";
                document.getElementById("form-phyto").value = data.phyto || "";
                document.getElementById("form-origDocsTransmitted").value = data.origDocsTransmitted || "";
                document.getElementById("form-status").value = data.status || "COMPLETE";
                document.getElementById("form-daysBeforeArrival").value = data.daysBeforeArrival || "";
            }

            modal.classList.remove("pointer-events-none", "opacity-0");
            modal.querySelector('.transform').classList.remove("translate-x-full");
        }

        function closeModal() {
            const modal = document.getElementById("form-modal");
            modal.classList.add("pointer-events-none", "opacity-0");
            modal.querySelector('.transform').classList.add("translate-x-full");
        }

        // Submit form data
        function handleFormSubmit(event) {
            event.preventDefault();
            
            const editIndexVal = document.getElementById("edit-index").value;
            const newRecord = {
                dateReceived: document.getElementById("form-dateReceived").value,
                supplier: document.getElementById("form-supplier").value,
                supplierInvoice: document.getElementById("form-supplierInvoice").value,
                hrdInvoice: document.getElementById("form-hrdInvoice").value,
                blNumber: document.getElementById("form-blNumber").value,
                etd: document.getElementById("form-etd").value,
                eta: document.getElementById("form-eta").value,
                proformaEtd: document.getElementById("form-proformaEtd").value,
                spsSent: document.getElementById("form-spsSent").value,
                spsNumber: document.getElementById("form-spsNumber").value,
                spsRef: document.getElementById("form-spsRef").value,
                spsIssued: document.getElementById("form-spsIssued").value,
                mustShipOut: document.getElementById("form-mustShipOut").value,
                docsSentLogistics: document.getElementById("form-docsSentLogistics").value,
                docStatus: document.getElementById("form-docStatus").value,
                origDocsReceived: document.getElementById("form-origDocsReceived").value,
                phyto: document.getElementById("form-phyto").value,
                origDocsTransmitted: document.getElementById("form-origDocsTransmitted").value,
                status: document.getElementById("form-status").value,
                daysBeforeArrival: document.getElementById("form-daysBeforeArrival").value ? Number(document.getElementById("form-daysBeforeArrival").value) : null
            };

            if (editIndexVal === "") {
                // Insert Mode
                trackingData.unshift(newRecord); // Place on top
                showToast("New operational record created successfully!");
            } else {
                // Update Mode
                const index = parseInt(editIndexVal);
                trackingData[index] = newRecord;
                showToast("Shipping record modified successfully.");
            }

            saveToLocalStorage();
            populateSupplierFilter();
            renderDashboard();
            closeModal();
        }

        // Delete Row confirmation Handler
        function deleteRecord(index) {
            if (confirm("Are you sure you want to permanently delete this operational tracking line? This cannot be undone.")) {
                trackingData.splice(index, 1);
                saveToLocalStorage();
                populateSupplierFilter();
                renderDashboard();
                showToast("Record removed from log.");
            }
        }

        // Display beautiful in-app micro toast messages
        function showToast(message) {
            const toast = document.getElementById("toast");
            document.getElementById("toast-message").innerText = message;
            toast.classList.remove("opacity-0", "translate-y-20");
            toast.classList.add("opacity-100", "translate-y-0");

            setTimeout(() => {
                toast.classList.remove("opacity-100", "translate-y-0");
                toast.classList.add("opacity-0", "translate-y-20");
            }, 3500);
        }

        // Export system state to fully compatible Microsoft Excel / Google Sheets CSV Format
        function exportToCSV() {
            const headers = [
                "Date Received", "Supplier", "Supplier Invoice", "HRD Invoice", "BL Number", 
                "ETD", "ETA", "ETD Proforma", "SPS Sent Supplier", "SPS Number", 
                "SPS Ref Number", "SPS Issued Date", "Must Ship Out", "Docs Sent Logistics", 
                "Doc Status", "Orig Docs Received", "Phyto Number", "Orig Docs Transmitted", 
                "Row Status", "Days Before Arrival"
            ];

            let csvContent = "data:text/csv;charset=utf-8," 
                + headers.join(",") + "\n"
                + trackingData.map(row => [
                    row.dateReceived,
                    `"${row.supplier}"`,
                    `"${row.supplierInvoice}"`,
                    `"${row.hrdInvoice}"`,
                    `"${row.blNumber || ''}"`,
                    row.etd,
                    row.eta,
                    row.proformaEtd,
                    row.spsSent,
                    `"${row.spsNumber || ''}"`,
                    `"${row.spsRef || ''}"`,
                    row.spsIssued,
                    row.mustShipOut,
                    row.docsSentLogistics,
                    row.docStatus,
                    row.origDocsReceived,
                    `"${row.phyto || ''}"`,
                    row.origDocsTransmitted,
                    row.status,
                    row.daysBeforeArrival || ""
                ].join(",")).join("\n");

            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `HRD_LUMBER_MONITORING_${new Date().getFullYear()}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            showToast("Log successfully exported to CSV file.");
        }
</script>

<script src="chat-state.js"></script>
<script src="chat-ui.js"></script>
<script src="chat-mention.js"></script>
<script src="chat-api.js"></script>

<script>
    // Live execution initialization
    window.currentUser = <?php echo $is_logged_in ? json_encode($_SESSION['username']) : 'null'; ?>;

    document.addEventListener('DOMContentLoaded', () => {
        lucide.createIcons();
        if (window.currentUser) {
            
            // Replaced setInterval with a smarter recursive polling function
            window.startSmartPolling = async () => {
                await window.syncChatWire(); // Wait for the fetch to finish
                window.chatPollTimer = setTimeout(window.startSmartPolling, 2000); // Only then wait 2s to fire again
            };
            
            // Initiate the loop
            window.startSmartPolling();
            
            window.downloadSystemWorkspaceDirectory();
            window.createAutocompleteContainerPanel();

            const chatInputField = document.getElementById('chat-input-field');
            if (chatInputField) {
                chatInputField.addEventListener('input', window.scanInputFieldForMentions);
                chatInputField.addEventListener('keydown', window.interceptInputKeydowns);
            }
        }
    });
</script>
</body>
</html>
