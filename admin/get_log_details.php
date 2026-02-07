<?php
require_once 'config.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    exit('Access denied');
}

$logId = intval($_GET['id']);
$db = getDB();

$stmt = $db->prepare("SELECT al.*, u.username, u.full_name FROM activity_log al 
                     LEFT JOIN users u ON al.user_id = u.id 
                     WHERE al.id = ?");
$stmt->bind_param("i", $logId);
$stmt->execute();
$result = $stmt->get_result();
$log = $result->fetch_assoc();

if ($log) {
    echo '
    <div class="details-grid">
        <div class="detail-item">
            <div class="detail-label">Date</div>
            <div class="detail-value">' . date('F j, Y', strtotime($log['activity_date'])) . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Time</div>
            <div class="detail-value">' . $log['activity_time'] . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Activity Type</div>
            <div class="detail-value">' . $log['activity_type'] . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Description</div>
            <div class="detail-value">' . htmlspecialchars($log['description']) . '</div>
        </div>';
    
    if ($log['details']) {
        echo '
        <div class="detail-item" style="grid-column: span 2;">
            <div class="detail-label">Details</div>
            <div class="detail-value" style="white-space: pre-wrap; font-family: monospace; font-size: 12px; background: #f8f9fa; padding: 10px; border-radius: 4px;">' . htmlspecialchars($log['details']) . '</div>
        </div>';
    }
    
    echo '
        <div class="detail-item">
            <div class="detail-label">Performed By</div>
            <div class="detail-value">' . ($log['full_name'] ? $log['full_name'] . ' (@' . $log['username'] . ')' : 'System') . '</div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Recorded At</div>
            <div class="detail-value">' . date('Y-m-d H:i:s', strtotime($log['created_at'])) . '</div>
        </div>
    </div>';
} else {
    echo '<p style="color: #dc3545; text-align: center;">Log not found</p>';
}
?>