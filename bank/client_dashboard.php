<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: Login.html");
    exit();
}

include 'db.php';

// جلب رصيد المستخدم ورقم الحساب
$stmt = $pdo->prepare("SELECT balance, account_number FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch(PDO::FETCH_ASSOC);
$balance = $user_data['balance'] ?? 0.00;
$account_number = $user_data['account_number'] ?? 'N/A';

// جلب آخر العمليات والطلبات الخاصة بالمستخدم من جدول account_requests
$stmt_trans = $pdo->prepare("SELECT * FROM account_requests WHERE user_id = ? ORDER BY id DESC LIMIT 5");
$stmt_trans->execute([$_SESSION['user_id']]);
$transactions = $stmt_trans->fetchAll(PDO::FETCH_ASSOC);

// جلب قروض المستخدم من جدول loans
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
    <title>Client Dashboard - Bank of Algeria</title>
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
        .card-header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 15px;
            gap: 15px;
        }
        .card h3 {
            font-size: 2rem;
            margin: 0;
            color: #27ae60;
        }
        .account-badge {
            background: rgba(39, 174, 96, 0.15);
            color: #27ae60;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 1.2rem;
            font-weight: bold;
            letter-spacing: 1.5px;
            border: 1px solid rgba(39, 174, 96, 0.4);
        }
        .card p.balance {
            font-size: 3rem;
            font-weight: bold;
            color: #fff;
            margin-bottom: 10px;
        }
        .deposit-note {
            color: #aaa;
            font-size: 0.95rem;
            margin-bottom: 20px;
            line-height: 1.5;
            background: rgba(255, 255, 255, 0.03);
            padding: 10px 15px;
            border-left: 3px solid #27ae60;
            border-radius: 4px;
        }
        .deposit-note i {
            color: #27ae60;
            margin-right: 8px;
        }
        .trans-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #27ae60;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: background 0.3s ease;
        }
        .trans-btn:hover {
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
    </style>
</head>
<body>

<header class="header">
    <a href="#" class="logo"> bank </a>
    <nav class="navbar">
        <a href="index.html">home</a>
        <a href="#" class="active">dashboard</a>
    </nav>
    <div class="icons">
        <a href="logout.php" class="fas fa-sign-out-alt" title="Logout"></a>
    </div>
</header>

<section class="dashboard-section">
    <h1 class="heading"> Welcome, <span><?php echo htmlspecialchars($_SESSION['username']); ?></span> </h1>

    <div class="dashboard-container">
        <!-- بطاقة الرصيد -->
        <div class="card">
            <div class="card-header-flex">
                <h3>Total Balance</h3>
                <div class="account-badge">
                    <i class="fas fa-wallet"></i> A/C: <?php echo htmlspecialchars($account_number); ?>
                </div>
            </div>
            
            <p class="balance"><?php echo number_format($balance, 2); ?> DZD</p>
            
            <!-- الملاحظة الإرشادية لعملية الإيداع -->
            <div class="deposit-note">
                <i class="fas fa-info-circle"></i>
                To deposit funds into your account, please share your account number with the bank branch to process your cash deposit. Once approved by management, your balance will be updated.
            </div>

            <a href="transactions.php" class="trans-btn">
                <i class="fas fa-plus-circle"></i> New Transaction
            </a>
            <a href="loan.php" class="trans-btn" style="background: #2980b9;">
                 <i class="fas fa-hand-holding-usd"></i> Loans
            </a>
        </div>

        <!-- بطاقة آخر العمليات والطلبات -->
        <div class="card">
            <h3>Recent Requests & Transactions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Service Type</th>
                        <th>Duration / Details</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['created_at'] ?? $row['date'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['service_type']); ?></td>
                            <td>
                                <?php 
                                $details = $row['details'] ?? '';
                                $display_info = 'N/A';

                                if (strpos($details, 'Term:') !== false) {
                                    $parts = explode('Term:', $details);
                                    $duration = trim(rtrim(end($parts), '.'));
                                    $display_info = "Term: " . str_replace('_', ' ', $duration);
                                } elseif (strpos($details, 'Child Name:') !== false) {
                                    $parts = explode('Child Name:', $details);
                                    $display_info = "Child: " . trim(rtrim(end($parts), '.'));
                                } else {
                                    $display_info = '-';
                                }

                                echo htmlspecialchars($display_info);
                                ?>
                            </td>
                            <td style="color: #27ae60; font-weight: bold;">
                                <?php echo number_format($row['amount'], 2); ?> DZD
                            </td>
                            <td>
                                <?php 
                                $status = strtolower($row['status'] ?? 'pending');
                                $status_color = ($status === 'approved') ? '#27ae60' : '#f39c12';
                                $bg_color = ($status === 'approved') ? 'rgba(39, 174, 96, 0.15)' : 'rgba(243, 156, 18, 0.15)';
                                ?>
                                <span class="status-badge" style="color: <?php echo $status_color; ?>; background: <?php echo $bg_color; ?>; border: 1px solid <?php echo $status_color; ?>;">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #777;">No recent requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- بطاقة متابعة طلبات القروض -->
        <div class="card">
            <h3><i class="fas fa-file-invoice-dollar"></i> My Loan Applications</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Loan Type</th>
                        <th>Amount</th>
                        <th>Deposit (8%)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($my_loans)): ?>
                        <?php foreach ($my_loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['created_at']); ?></td>
                            <td><?php echo str_replace('_', ' ', ucfirst($loan['loan_type'])); ?></td>
                            <td style="font-weight: bold;"><?php echo number_format($loan['loan_amount'], 2); ?> DZD</td>
                            <td><?php echo number_format($loan['deposit_amount'], 2); ?> DZD</td>
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
                            <td colspan="5" style="text-align: center; color: #777;">No loan applications found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

</body>
</html>