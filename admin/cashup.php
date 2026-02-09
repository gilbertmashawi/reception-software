<?php
// cashup.php - Admin View for Cash-up Submissions with Expandable Rows
session_start();

// Load configuration
require_once 'config.php';

$db = getDB();

// Get filter parameters
$date = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM cashup_submissions WHERE 1=1";
$params = [];
$types = '';

if ($date) {
    $sql .= " AND submission_date = ?";
    $params[] = $date;
    $types .= 's';
}

if ($search) {
    $sql .= " AND (submitted_by_name LIKE ? OR notes LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $types .= 'ss';
}

$sql .= " ORDER BY submission_date DESC, submitted_at DESC LIMIT 100";

$stmt = $db->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$records = $result->fetch_all(MYSQLI_ASSOC);

// Get totals
$totalRevenue = 0;
$totalCash = 0;
$totalTransactions = 0;
foreach ($records as $record) {
    $totalRevenue += $record['grand_total_revenue'];
    $totalCash += $record['grand_total_cash_in'];
    $totalTransactions += $record['daily_vouchers_transactions'] + 
                         $record['daily_linkspot_transactions'] + 
                         $record['daily_mall_transactions'];
}

// Get unique dates for filter
$dateStmt = $db->prepare("SELECT DISTINCT submission_date FROM cashup_submissions ORDER BY submission_date DESC LIMIT 30");
$dateStmt->execute();
$dateResult = $dateStmt->get_result();
$availableDates = $dateResult->fetch_all(MYSQLI_ASSOC);

// Function to get voucher breakdown for a specific submission
function getVoucherBreakdown($db, $submissionDate, $submittedByName) {
    $breakdown = [
        'vouchers' => [],
        'linkspot' => [],
        'mall' => []
    ];
    
    // Get voucher breakdown
    $vStmt = $db->prepare("
        SELECT 
            vt.name as voucher_type,
            COUNT(*) as quantity,
            SUM(vsi.unit_price) as total_amount
        FROM voucher_sales vs
        JOIN voucher_sale_items vsi ON vs.id = vsi.sale_id
        JOIN voucher_types vt ON vsi.voucher_type_id = vt.id
        WHERE vs.sale_date = ?
        GROUP BY vt.id, vt.name
        ORDER BY vt.price
    ");
    $vStmt->bind_param("s", $submissionDate);
    $vStmt->execute();
    $vResult = $vStmt->get_result();
    while ($row = $vResult->fetch_assoc()) {
        $breakdown['vouchers'][] = $row;
    }
    $vStmt->close();
    
    // Get LinkSpot breakdown
    $lStmt = $db->prepare("
        SELECT 
            payer_name as member_name,
            COUNT(*) as quantity,
            SUM(amount) as total_amount
        FROM linkspot_payments
        WHERE payment_date = ?
        GROUP BY payer_name
        ORDER BY total_amount DESC
    ");
    $lStmt->bind_param("s", $submissionDate);
    $lStmt->execute();
    $lResult = $lStmt->get_result();
    while ($row = $lResult->fetch_assoc()) {
        $breakdown['linkspot'][] = $row;
    }
    $lStmt->close();
    
    // Get mall breakdown
    $mStmt = $db->prepare("
        SELECT 
            payer_name as shop_name,
            COUNT(*) as quantity,
            SUM(amount) as total_amount
        FROM mall_payments
        WHERE payment_date = ?
        GROUP BY payer_name
        ORDER BY total_amount DESC
    ");
    $mStmt->bind_param("s", $submissionDate);
    $mStmt->execute();
    $mResult = $mStmt->get_result();
    while ($row = $mResult->fetch_assoc()) {
        $breakdown['mall'][] = $row;
    }
    $mStmt->close();
    
    return $breakdown;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Cash-up Admin View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: <?php echo SITE_THEME_COLOR; ?>;
            --secondary: <?php echo SITE_SECONDARY_COLOR; ?>;
            --accent: #e74c3c;
            --light: #f8f9fa;
            --dark: #343a40;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --info: #17a2b8;
            --sidebar-width: 260px;
            --header-height: 70px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .dashboard-page {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--secondary);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            background: rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .sidebar-header img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .sidebar-header h3 {
            font-size: 18px;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .nav-item {
            list-style: none;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-link.active {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        
        .nav-dropdown .nav-link {
            position: relative;
        }
        
        .nav-dropdown .nav-link:after {
            content: '\f107';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            margin-left: auto;
            transition: transform 0.3s;
        }
        
        .nav-dropdown.active .nav-link:after {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            padding-left: 20px;
            background: rgba(0,0,0,0.2);
            display: none;
        }
        
        .nav-dropdown.active .dropdown-menu {
            display: block;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 20px;
        }
        
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .page-title h1 {
            font-size: 24px;
            color: var(--secondary);
            margin: 0;
        }
        
        .page-title p {
            color: #6c757d;
            margin: 5px 0 0 0;
            font-size: 14px;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            border-left: 4px solid var(--primary);
            transition: transform 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.danger {
            border-left-color: var(--danger);
        }
        
        .stat-card.warning {
            border-left-color: var(--warning);
        }
        
        .stat-card.info {
            border-left-color: var(--info);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: rgba(39, 174, 96, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .stat-card.danger .stat-icon {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger);
        }
        
        .stat-card.warning .stat-icon {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }
        
        .stat-card.info .stat-icon {
            background: rgba(23, 162, 184, 0.1);
            color: var(--info);
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 600;
            color: var(--secondary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        /* Filter Section */
        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: #219653;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }
        
        /* Table Styles */
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 12px 15px;
            text-align: left;
            font-weight: 500;
            color: var(--secondary);
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
        }
        
        .table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .money {
            text-align: right;
            font-weight: 600;
        }
        
        .status-new {
            background: #fff3cd;
            font-weight: bold;
        }
        
        /* Expandable Row */
        .expand-btn {
            background: none;
            border: none;
            color: var(--primary);
            cursor: pointer;
            font-size: 16px;
            padding: 5px;
        }
        
        .expand-btn:hover {
            color: #219653;
        }
        
        .details-row {
            background: #f8f9fa;
            display: none;
        }
        
        .details-content {
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .details-section {
            margin-bottom: 20px;
            padding: 15px;
            background: white;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .details-section h4 {
            color: var(--secondary);
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .item-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .item-row:last-child {
            border-bottom: none;
        }
        
        .item-row.total {
            font-weight: 600;
            border-top: 2px solid #dee2e6;
            margin-top: 10px;
            padding-top: 15px;
            color: var(--primary);
            font-size: 16px;
        }
        
        /* Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        /* Notes */
        .notes-cell {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notes-cell:hover {
            white-space: normal;
            overflow: visible;
            background: white;
            position: relative;
            z-index: 1;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        /* No Data */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Print Styles */
        @media print {
            .sidebar, .filter-card, .btn, .top-bar p, .expand-btn {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .details-row {
                display: table-row !important;
            }
            
            .table {
                font-size: 12px;
            }
            
            .table th, .table td {
                padding: 6px 8px;
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .filter-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .table th, .table td {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .details-content {
                padding: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-page">
        <?php include 'header.php'; ?>
        
        <div class="main-content">
            <div class="top-bar">
                <div class="page-title">
                    <h1><i class="fas fa-chart-line"></i> Cash-up Reports - Admin View</h1>
                    <p>View all cash-up submissions from staff. <?php echo date('l, d F Y H:i:s'); ?></p>
                </div>
                <div class="user-menu">
                    <div class="user-info">
                        <div class="user-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div style="font-weight: 500;">Admin User</div>
                            <div style="font-size: 12px; color: #6c757d;">Administrator</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="stat-value"><?= count($records) ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                
                <div class="stat-card info">
                    <div class="stat-icon">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($totalRevenue, 2) ?></div>
                    <div class="stat-label">Total Revenue</div>
                </div>
                
                <div class="stat-card danger">
                    <div class="stat-icon">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="stat-value">$<?= number_format($totalCash, 2) ?></div>
                    <div class="stat-label">Total Cash In</div>
                </div>
                
                <div class="stat-card warning">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <div class="stat-value"><?= $totalTransactions ?></div>
                    <div class="stat-label">Total Transactions</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-card">
                <form method="GET" action="">
                    <div class="filter-row">
                        <div class="form-group">
                            <label class="form-label">Filter by Date</label>
                            <select name="date" class="form-control">
                                <option value="">All Dates</option>
                                <?php foreach ($availableDates as $dateOption): ?>
                                <option value="<?= $dateOption['submission_date'] ?>" <?= ($date == $dateOption['submission_date']) ? 'selected' : '' ?>>
                                    <?= date('d M Y', strtotime($dateOption['submission_date'])) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Search (Name/Notes)</label>
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                            <a href="cashup.php" class="btn btn-secondary">
                                <i class="fas fa-redo"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Table Section -->
            <div class="table-card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: var(--secondary); margin: 0;">Cash-up Submissions</h3>
                    <div>
                        <button onclick="expandAllRows()" class="btn btn-sm btn-secondary" style="margin-right: 10px;">
                            <i class="fas fa-expand"></i> Expand All
                        </button>
                        <button onclick="collapseAllRows()" class="btn btn-sm btn-secondary" style="margin-right: 10px;">
                            <i class="fas fa-compress"></i> Collapse All
                        </button>
                        <button onclick="window.print()" class="btn btn-secondary">
                            <i class="fas fa-print"></i> Print Report
                        </button>
                    </div>
                </div>
                
                <?php if (empty($records)): ?>
                    <div class="no-data">
                        <i class="fas fa-inbox fa-3x" style="color: #dee2e6; margin-bottom: 15px;"></i>
                        <h3>No cash-up reports found</h3>
                        <p>No submissions match your criteria.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th width="30"></th>
                                    <th>Date</th>
                                    <th>Submitted By</th>
                                    <th>Vouchers</th>
                                    <th>LinkSpot</th>
                                    <th>Mall</th>
                                    <th>Total Revenue</th>
                                    <th>Cash In</th>
                                    <th>Change</th>
                                    <th>Notes</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $index => $record): 
                                    $isNew = isset($record['status']) && $record['status'] === 'new';
                                    $breakdown = getVoucherBreakdown($db, $record['submission_date'], $record['submitted_by_name']);
                                ?>
                                <tr class="<?= $isNew ? 'status-new' : '' ?>" id="row-<?= $index ?>">
                                    <td>
                                        <button class="expand-btn" onclick="toggleRow(<?= $index ?>)">
                                            <i class="fas fa-chevron-right" id="icon-<?= $index ?>"></i>
                                        </button>
                                    </td>
                                    <td><strong><?= date('d M Y', strtotime($record['submission_date'])) ?></strong></td>
                                    <td><?= htmlspecialchars($record['submitted_by_name']) ?></td>
                                    <td class="money">
                                        $<?= number_format($record['daily_vouchers_revenue'], 2) ?>
                                        <div style="font-size: 11px; color: #6c757d;">
                                            <?= $record['daily_vouchers_transactions'] ?> trans
                                        </div>
                                    </td>
                                    <td class="money">
                                        $<?= number_format($record['daily_linkspot_revenue'], 2) ?>
                                        <div style="font-size: 11px; color: #6c757d;">
                                            <?= $record['daily_linkspot_transactions'] ?> trans
                                        </div>
                                    </td>
                                    <td class="money">
                                        $<?= number_format($record['daily_mall_revenue'], 2) ?>
                                        <div style="font-size: 11px; color: #6c757d;">
                                            <?= $record['daily_mall_transactions'] ?> trans
                                        </div>
                                    </td>
                                    <td class="money" style="background: #f8f9fa;">
                                        <strong>$<?= number_format($record['grand_total_revenue'], 2) ?></strong>
                                    </td>
                                    <td class="money" style="background: #f0f8ff;">
                                        <strong>$<?= number_format($record['grand_total_cash_in'], 2) ?></strong>
                                    </td>
                                    <td class="money">
                                        $<?= number_format($record['daily_vouchers_change_given'], 2) ?>
                                    </td>
                                    <td class="notes-cell" title="<?= htmlspecialchars($record['notes']) ?>">
                                        <?= htmlspecialchars($record['notes'] ?: '—') ?>
                                    </td>
                                    <td>
                                        <?= date('H:i', strtotime($record['submitted_at'])) ?>
                                        <?php if ($isNew): ?>
                                            <span class="badge badge-warning">NEW</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <!-- Details Row -->
                                <tr class="details-row" id="details-<?= $index ?>">
                                    <td colspan="11">
                                        <div class="details-content">
                                            <!-- Vouchers Section -->
                                            <div class="details-section">
                                                <h4><i class="fas fa-wifi"></i> Internet Vouchers Breakdown</h4>
                                                <?php if (!empty($breakdown['vouchers'])): ?>
                                                    <?php foreach ($breakdown['vouchers'] as $voucher): ?>
                                                    <div class="item-row">
                                                        <div><?= htmlspecialchars($voucher['voucher_type']) ?> x <?= $voucher['quantity'] ?></div>
                                                        <div><strong>$<?= number_format($voucher['total_amount'], 2) ?></strong></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="item-row">
                                                        <div>No voucher sales</div>
                                                        <div><strong>$0.00</strong></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="item-row total">
                                                    <div>Total Vouchers Revenue</div>
                                                    <div><strong>$<?= number_format($record['daily_vouchers_revenue'], 2) ?></strong></div>
                                                </div>
                                            </div>
                                            
                                            <!-- LinkSpot Section -->
                                            <div class="details-section">
                                                <h4><i class="fas fa-desktop"></i> LinkSpot Spaces Breakdown</h4>
                                                <?php if (!empty($breakdown['linkspot'])): ?>
                                                    <?php foreach ($breakdown['linkspot'] as $member): ?>
                                                    <div class="item-row">
                                                        <div><?= htmlspecialchars($member['member_name']) ?> x <?= $member['quantity'] ?></div>
                                                        <div><strong>$<?= number_format($member['total_amount'], 2) ?></strong></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="item-row">
                                                        <div>No LinkSpot payments</div>
                                                        <div><strong>$0.00</strong></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="item-row total">
                                                    <div>Total LinkSpot Revenue</div>
                                                    <div><strong>$<?= number_format($record['daily_linkspot_revenue'], 2) ?></strong></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Mall Section -->
                                            <div class="details-section">
                                                <h4><i class="fas fa-store"></i> Summarcity Mall Breakdown</h4>
                                                <?php if (!empty($breakdown['mall'])): ?>
                                                    <?php foreach ($breakdown['mall'] as $shop): ?>
                                                    <div class="item-row">
                                                        <div><?= htmlspecialchars($shop['shop_name']) ?> x <?= $shop['quantity'] ?></div>
                                                        <div><strong>$<?= number_format($shop['total_amount'], 2) ?></strong></div>
                                                    </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <div class="item-row">
                                                        <div>No mall payments</div>
                                                        <div><strong>$0.00</strong></div>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="item-row total">
                                                    <div>Total Mall Revenue</div>
                                                    <div><strong>$<?= number_format($record['daily_mall_revenue'], 2) ?></strong></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Summary Section -->
                                            <div class="details-section" style="background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%); color: white;">
                                                <h4 style="color: white; border-bottom-color: rgba(255,255,255,0.3);">
                                                    <i class="fas fa-chart-line"></i> Daily Summary
                                                </h4>
                                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; text-align: center;">
                                                    <div>
                                                        <div style="font-size: 12px; opacity: 0.9;">Total Revenue</div>
                                                        <div style="font-size: 20px; font-weight: bold;">$<?= number_format($record['grand_total_revenue'], 2) ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 12px; opacity: 0.9;">Cash In</div>
                                                        <div style="font-size: 20px; font-weight: bold;">$<?= number_format($record['grand_total_cash_in'], 2) ?></div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 12px; opacity: 0.9;">Transactions</div>
                                                        <div style="font-size: 20px; font-weight: bold;">
                                                            <?= $record['daily_vouchers_transactions'] + $record['daily_linkspot_transactions'] + $record['daily_mall_transactions'] ?>
                                                        </div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 12px; opacity: 0.9;">Change Given</div>
                                                        <div style="font-size: 20px; font-weight: bold;">$<?= number_format($record['daily_vouchers_change_given'], 2) ?></div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: bold;">
                                    <td colspan="6" style="text-align: right;">Totals:</td>
                                    <td class="money">$<?= number_format($totalRevenue, 2) ?></td>
                                    <td class="money">$<?= number_format($totalCash, 2) ?></td>
                                    <td colspan="4"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div style="margin-top: 15px; text-align: center; color: #6c757d; font-size: 14px;">
                        Showing <?= count($records) ?> cash-up reports
                        <?php if ($date): ?>
                            for <?= date('d M Y', strtotime($date)) ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="text-align: center; margin-top: 30px; color: #6c757d; font-size: 13px;">
                <p><i class="fas fa-info-circle"></i> Click the arrow icon to expand/collapse detailed breakdowns.</p>
                <p>Last updated: <?= date('H:i:s'); ?></p>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh page every 5 minutes
        setTimeout(function() {
            window.location.reload();
        }, 300000); // 5 minutes
        
        // Add click to expand notes
        document.querySelectorAll('.notes-cell').forEach(cell => {
            cell.addEventListener('click', function() {
                if (this.style.whiteSpace === 'normal') {
                    this.style.whiteSpace = 'nowrap';
                    this.style.overflow = 'hidden';
                } else {
                    this.style.whiteSpace = 'normal';
                    this.style.overflow = 'visible';
                }
            });
        });
        
        // Toggle row expansion
        function toggleRow(index) {
            const detailsRow = document.getElementById('details-' + index);
            const icon = document.getElementById('icon-' + index);
            
            if (detailsRow.style.display === 'table-row') {
                detailsRow.style.display = 'none';
                icon.className = 'fas fa-chevron-right';
            } else {
                detailsRow.style.display = 'table-row';
                icon.className = 'fas fa-chevron-down';
            }
        }
        
        // Expand all rows
        function expandAllRows() {
            const totalRows = <?= count($records) ?>;
            for (let i = 0; i < totalRows; i++) {
                const detailsRow = document.getElementById('details-' + i);
                const icon = document.getElementById('icon-' + i);
                if (detailsRow) {
                    detailsRow.style.display = 'table-row';
                    icon.className = 'fas fa-chevron-down';
                }
            }
        }
        
        // Collapse all rows
        function collapseAllRows() {
            const totalRows = <?= count($records) ?>;
            for (let i = 0; i < totalRows; i++) {
                const detailsRow = document.getElementById('details-' + i);
                const icon = document.getElementById('icon-' + i);
                if (detailsRow) {
                    detailsRow.style.display = 'none';
                    icon.className = 'fas fa-chevron-right';
                }
            }
        }
        
        // Auto-expand rows marked as NEW
        document.addEventListener('DOMContentLoaded', function() {
            const newRows = document.querySelectorAll('.status-new');
            newRows.forEach(row => {
                const index = row.id.split('-')[1];
                const detailsRow = document.getElementById('details-' + index);
                const icon = document.getElementById('icon-' + index);
                if (detailsRow && icon) {
                    detailsRow.style.display = 'table-row';
                    icon.className = 'fas fa-chevron-down';
                }
            });
        });
    </script>
</body>
</html>