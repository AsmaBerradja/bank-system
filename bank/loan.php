<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: Login.html");
    exit();
}

include 'db.php';

$message = "";
$error = "";

// جلب رصيد المستخدم ورقم الحساب
$stmt = $pdo->prepare("SELECT balance, account_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user_data['balance'] ?? 0.00;
$account_number = $user_data['account_number'] ?? 'N/A';

// معالجة إرسال طلب القرض الجديد
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_loan'])) {
    $loan_type = trim($_POST['loan_type'] ?? '');
    $loan_amount = floatval($_POST['loan_amount'] ?? 0);
    $deposit_amount = floatval($_POST['deposit_amount'] ?? 0);
    $monthly_income = floatval($_POST['monthly_income'] ?? 0);

    // التحقق من شرط الدخل الشهري (يجب أن يكون أكبر أو يساوي 40000)
    if ($monthly_income < 40000) {
        $error = "Sorry, you cannot apply for a loan because your monthly income is below the minimum required (40,000 DZD).<br>عذراً، لا يمكنك تقديم طلب قرض لأن دخلك الشهري أقل من الحد الأدنى المطلوب (40,000 DZD).";
    } elseif ($loan_amount <= 0) {
        $error = "Please enter a valid loan amount.<br>الرجاء إدخال مبلغ قرض صحيح.";
    } else {
        // معالجة رفع الملف الثبوتي إن وُجد
        $proof_document = '';
        if (isset($_FILES['proof_document']) && $_FILES['proof_document']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['proof_document']['tmp_name'];
            $file_name = time() . '_' . basename($_FILES['proof_document']['name']);
            $upload_dir = 'uploads/';
            
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $destination = $upload_dir . $file_name;
            if (move_uploaded_file($file_tmp, $destination)) {
                $proof_document = $destination;
            }
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO loans (user_id, loan_type, monthly_income, loan_amount, deposit_amount, proof_document, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$_SESSION['user_id'], $loan_type, $monthly_income, $loan_amount, $deposit_amount, $proof_document]);
            $message = "Loan request submitted successfully and is awaiting management approval.<br>تم إرسال طلب القرض بنجاح وبانتظار موافقة الإدارة.";
        } catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage() . "<br>خطأ في قاعدة البيانات: " . $e->getMessage();
        }
    }
}

// جلب قروض المستخدم الحالية
$stmt_loans = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY id DESC");
$stmt_loans->execute([$_SESSION['user_id']]);
$my_loans = $stmt_loans->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Services - Bank of Algeria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .dashboard-section {
            padding: 100px 9%;
            background: #111;
            color: #fff;
            min-height: 100vh;
        }
        .dashboard-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-top: 30px;
        }
        .card {
            background: #222;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .card h3 {
            font-size: 1.8rem;
            margin-bottom: 20px;
            color: #27ae60;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #aaa;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 10px;
            background: #333;
            border: 1px solid #444;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
        }
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { 
            -webkit-appearance: none; 
            appearance: none;
            margin: 0; 
        }
        input[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }
        .submit-btn {
            background: #27ae60;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
        }
        .submit-btn:hover {
            background: #219653;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            text-align: left;
        }
        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #333;
        }
        table th {
            color: #27ae60;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .alert-success {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .alert-error {
            background: rgba(192, 57, 43, 0.2);
            color: #e74c3c;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
    </style>
    <script>
        function calculateDeposit() {
            let income = document.getElementById('monthly_income').value;
            let loanType = document.getElementById('loan_type').value;
            let depositField = document.getElementById('deposit_amount');
            let depositLabel = document.getElementById('deposit_label');

            let rate = 0;
            let rateText = "";

            if (loanType === 'housing_loan') {
                rate = 0.30;
                rateText = " (30% of Income)";
            } else if (loanType === 'car_loan') {
                rate = 0.25;
                rateText = " (25% of Income)";
            } else if (loanType === 'electronics_loan') {
                rate = 0.15;
                rateText = " (15% of Income)";
            }

            depositLabel.innerHTML = "Required Deposit / Fee (DZD)" + rateText;

            if(income && rate > 0) {
                let deposit = income * rate;
                depositField.value = deposit.toFixed(2);
            } else {
                depositField.value = '';
            }
        }
    </script>
</head>
<body onload="calculateDeposit()">

<header class="header">
    <a href="client_dashboard.php" class="logo"> bank </a>
    <nav class="navbar">
        <a href="client_dashboard.php">Dashboard</a>
        <a href="loan.php" class="active">Loans</a>
    </nav>
    <div class="icons">
        <a href="logout.php" class="fas fa-sign-out-alt" title="Logout"></a>
    </div>
</header>

<section class="dashboard-section">
    <h1 class="heading"> Loan <span>Management</span> </h1>

    <?php if (!empty($message)): ?>
        <div class="alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="dashboard-container">
        
        <!-- نموذج طلب قرض جديد -->
        <div class="card">
            <h3><i class="fas fa-hand-holding-usd"></i> Request A New Loan</h3>
            <form action="" method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="loan_type">Loan Type</label>
                    <select name="loan_type" id="loan_type" onchange="calculateDeposit()" required>
                        <option value="housing_loan">Housing Loan (قرض المنزل)</option>
                        <option value="car_loan">Car Loan (قرض السيارة)</option>
                        <option value="electronics_loan">Electronics Loan (قرض الأجهزة الإلكترونية)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="monthly_income">Monthly Income (DZD)</label>
                    <input type="number" step="0.01" name="monthly_income" id="monthly_income" placeholder="Enter your monthly income" oninput="calculateDeposit()" required>
                </div>

                <div class="form-group">
                    <label for="loan_amount">Loan Amount (DZD)</label>
                    <input type="number" step="0.01" name="loan_amount" id="loan_amount" placeholder="Enter Amount" required>
                </div>

                <div class="form-group">
                    <label for="deposit_amount" id="deposit_label">Required Deposit / Fee (DZD)</label>
                    <input type="number" step="0.01" name="deposit_amount" id="deposit_amount" placeholder="Calculated Automatically Based on Income" readonly required>
                </div>

                <div class="form-group">
                    <label for="proof_document">Supporting Document / Proof (PDF Or Image)</label>
                    <input type="file" name="proof_document" id="proof_document">
                </div>

                <button type="submit" name="submit_loan" class="submit-btn">
                    <i class="fas fa-paper-plane"></i> Submit Loan Request
                </button>
            </form>
        </div>

        <!-- جدول متابعة القروض -->
        <div class="card">
            <h3><i class="fas fa-file-invoice-dollar"></i> My Loan Applications Status</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Loan Type</th>
                        <th>Monthly Income</th>
                        <th>Amount</th>
                        <th>Deposit</th>
                        <th>Document</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($my_loans)): ?>
                        <?php foreach ($my_loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['created_at']); ?></td>
                            <td><?php echo str_replace('_', ' ', ucfirst($loan['loan_type'])); ?></td>
                            <td><?php echo number_format($loan['monthly_income'] ?? 0, 2); ?> DZD</td>
                            <td style="font-weight: bold;"><?php echo number_format($loan['loan_amount'], 2); ?> DZD</td>
                            <td><?php echo number_format($loan['deposit_amount'], 2); ?> DZD</td>
                            <td>
                                <?php if (!empty($loan['proof_document'])): ?>
                                    <a href="<?php echo htmlspecialchars($loan['proof_document']); ?>" target="_blank" style="color: #3498db; text-decoration: underline;">View File</a>
                                <?php else: ?>
                                    <span style="color: #777;">None</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                $loan_status = strtolower($loan['status'] ?? 'pending');
                                $l_color = ($loan_status === 'approved') ? '#27ae60' : '#f39c12';
                                $l_bg = ($loan_status === 'approved') ? 'rgba(39, 174, 96, 0.15)' : 'rgba(243, 156, 18, 0.15)';
                                ?>
                                <span class="status-badge" style="color: <?php echo $l_color; ?>; background: <?php echo $l_bg; ?>; border: 1px solid <?php echo $l_color; ?>;">
                                    <?php echo ucfirst($loan_status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #777;">No loan applications found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>
</section>

</body>
</html>