<?php
// voucher_cleaner.php
// Upload a CSV/text file and extract only 11-character voucher codes

$vouchers = [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['voucher_file']) || $_FILES['voucher_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload a valid file.';
    } else {

        $tmpFile = $_FILES['voucher_file']['tmp_name'];

        if (!is_uploaded_file($tmpFile)) {
            $error = 'Invalid upload.';
        } else {

            $lines = file($tmpFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                $line = trim($line);

                // Skip comments
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                // Remove quotes
                $value = str_replace('"', '', $line);

                // Keep only 11-character vouchers
                if (strlen($value) === 11) {
                    $vouchers[] = $value;
                }
            }

            // Optional: remove duplicates
            $vouchers = array_values(array_unique($vouchers));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher Cleaner</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            background: #f6f6f6;
        }
        .box {
            background: #fff;
            padding: 20px;
            border-radius: 6px;
            max-width: 600px;
        }
        pre {
            background: #111;
            color: #0f0;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .error {
            color: red;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Upload Voucher CSV</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="voucher_file" accept=".csv,.txt" required>
        <br><br>
        <button type="submit">Upload & Clean</button>
    </form>

    <?php if (!empty($vouchers)): ?>
        <h3>Cleaned Vouchers (<?= count($vouchers) ?>)</h3>
        <pre><?= htmlspecialchars(implode("\n", $vouchers)) ?></pre>
    <?php endif; ?>
</div>

</body>
</html>
