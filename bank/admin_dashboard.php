<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.html");
    exit();
}

include 'db.php';

$message = "";
$error = "";

// 1. Approve pending account requests from 'account_requests' table
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_request'])) {
    $request_id = intval($_POST['request_id']);
    try {
        $stmt = $pdo->prepare("UPDATE account_requests SET status = 'approved' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$request_id]);
        if ($stmt->rowCount() > 0) {
            $message = "تمت الموافقة على طلب حساب التوفير بنجاح!";
        } else {
            throw new Exception("الطلب غير موجود أو تم اعتماده مسبقاً.");
        }
    } catch (Exception $e) {
        $error = "خطأ: " . $e->getMessage();
    }
}

// معالجة الموافقة على القرض من جدول loans
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_loan'])) {
    $loan_id = intval($_POST['loan_id']);
    try {
        $stmt = $pdo->prepare("UPDATE loans SET status = 'approved' WHERE id = ? AND status = 'pending'");
        $stmt->execute([$loan_id]);
        if ($stmt->rowCount() > 0) {
            $message = "تمت الموافقة على طلب القرض بنجاح!";
        } else {
            throw new Exception("طلب القرض غير موجود أو تم اعتماده مسبقاً.");
        }
    } catch (Exception $e) {
        $error = "خطأ: " . $e->getMessage();
    }
}

// 2. Process admin cash deposit to client account by account number
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_deposit'])) {
    $account_number = trim($_POST['account_number'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);

    if (empty($account_number)) {
        $error = "Error: Please enter a valid account number.";
    } elseif ($amount <= 0) {
        $error = "Error: Please enter a valid amount greater than zero.";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT id, balance, username, account_number FROM users WHERE account_number = ? AND role = 'client' FOR UPDATE");
            $stmt->execute([$account_number]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                throw new Exception("Account number not found in the system.");
            }

            $client_id = $client['id'];
            $new_balance = $client['balance'] + $amount;
            
            $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
            $update_stmt->execute([$new_balance, $client_id]);

            $trans_stmt = $pdo->prepare("INSERT INTO transactions (user_id, amount, status, type, service_type) VALUES (?, ?, 'approved', 'deposit', 'deposit')");
            $trans_stmt->execute([$client_id, $amount]);

            $pdo->commit();
            $message = "Successfully deposited $" . number_format($amount, 2) . " into account number: " . htmlspecialchars($account_number) . " (" . htmlspecialchars($client['username'] ?? '') . ")";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Fetch pending account requests from 'account_requests' table
$requests_stmt = $pdo->query("SELECT ar.*, u.username, u.account_number 
                               FROM account_requests ar 
                               JOIN users u ON ar.user_id = u.id 
                               WHERE ar.status = 'pending' 
                               ORDER BY ar.created_at DESC");
$pending_requests = $requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch pending loan requests from 'loans' table
$loans_stmt = $pdo->query("SELECT l.*, u.username, u.account_number 
                           FROM loans l 
                           JOIN users u ON l.user_id = u.id 
                           WHERE l.status = 'pending' 
                           ORDER BY l.created_at DESC");
$pending_loans = $loans_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch clients list
$clients_stmt = $pdo->query("SELECT id, username, email, balance, account_number FROM users WHERE role = 'client' ORDER BY id DESC");
$clients = $clients_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch system recent transactions
$trans_stmt = $pdo->query("SELECT t.*, u.username, u.account_number FROM transactions t JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC LIMIT 10");
$transactions = $trans_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bank of Algeria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .admin-section {
            padding: 100px 9%;
            background: #111;
            color: #fff;
            min-height: 100vh;
        }
        .admin-container {
            display: flex;
            flex-direction: column;
            gap: 30px;
            margin-top: 20px;
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
        .form-group input {
            width: 100%;
            padding: 10px;
            background: #333;
            border: 1px solid #444;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group input:focus {
            border-color: #27ae60;
            outline: none;
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
            text-decoration: none;
            display: inline-block;
        }
        .submit-btn:hover {
            background: #219653;
        }
        .approve-btn {
            background: #2980b9;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
        }
        .approve-btn:hover {
            background: #2471a3;
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
        .quick-nav {
            margin-bottom: 15px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="#" class="logo"> bank - admin </a>
    <nav class="navbar">
        <a href="#" class="active">Admin Dashboard</a>
    </nav>
    <div class="icons">
        <a href="logout.php" class="fas fa-sign-out-alt" title="Logout"></a>
    </div>
</header>

<section class="admin-section">
    <h1 class="heading"> Admin Control Panel, <span><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></span> </h1>

    <?php if (!empty($message)): ?>
        <div class="alert-success"><?php echo $message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="quick-nav">
        <a href="#requests-section" class="submit-btn">
            <i class="fas fa-arrow-down"></i> Account Requests
        </a>
        <a href="#loans-section" class="submit-btn" style="background: #2980b9;">
            <i class="fas fa-arrow-down"></i> Loan Applications
        </a>
    </div>

    <div class="admin-container">
        
        <!-- جدول الطلبات المعلقة لفتح الحسابات -->
        <div class="card" id="requests-section">
            <h3><i class="fas fa-clock"></i> Savings Account & Service Requests (Pending)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Account Number</th>
                        <th>Service Type</th>
                        <th>Duration</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pending_requests)): ?>
                        <?php foreach ($pending_requests as $req): 
                            $r_date = $req['created_at'] ?? '';
                            $r_user = $req['username'] ?? '';
                            $r_acc  = $req['account_number'] ?? 'N/A';
                            $r_serv = $req['service_type'] ?? 'N/A';
                            $r_amt  = $req['amount'] ?? 0;
                            
                            $r_det  = $req['details'] ?? '';
                            $duration = 'N/A';
                            if (strpos($r_det, 'Term:') !== false) {
                                $parts = explode('Term:', $r_det);
                                $duration = trim(rtrim(end($parts), '.'));
                            } else {
                                $duration = 'Flexible / No Term';
                            }
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r_date); ?></td>
                            <td><?php echo htmlspecialchars($r_user); ?></td>
                            <td style="font-weight: bold; color: #27ae60;"><?php echo htmlspecialchars($r_acc); ?></td>
                            <td><?php echo htmlspecialchars($r_serv); ?></td>
                            <td><?php echo htmlspecialchars($duration); ?></td>
                            <td style="font-weight: bold;">$<?php echo number_format($r_amt, 2); ?></td>
                            <td>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                    <button type="submit" name="approve_request" class="approve-btn">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #777;">No pending requests found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- جدول طلبات القروض الجديدة -->
        <div class="card" id="loans-section">
            <h3><i class="fas fa-file-invoice-dollar"></i> New Loan Applications (Pending)</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Account Number</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Deposit (8%)</th>
                        <th>Document</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pending_loans)): ?>
                        <?php foreach ($pending_loans as $loan): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($loan['created_at']); ?></td>
                            <td><?php echo htmlspecialchars($loan['username']); ?></td>
                            <td style="font-weight: bold; color: #27ae60;"><?php echo htmlspecialchars($loan['account_number']); ?></td>
                            <td><?php echo str_replace('_', ' ', ucfirst($loan['loan_type'])); ?></td>
                            <td style="font-weight: bold;"><?php echo number_format($loan['loan_amount'], 2); ?> DZD</td>
                            <td><?php echo number_format($loan['deposit_amount'], 2); ?> DZD</td>
                            <td>
                                <?php if (!empty($loan['proof_document'])): ?>
                                    <a href="<?php echo htmlspecialchars($loan['proof_document']); ?>" target="_blank" class="approve-btn" style="background:#f39c12; padding: 5px 10px; font-size: 0.9rem;">
                                        <i class="fas fa-eye"></i> View Doc
                                    </a>
                                <?php else: ?>
                                    <span style="color: #777;">No Doc</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form action="" method="POST" style="display:inline;">
                                    <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                    <button type="submit" name="approve_loan" class="approve-btn">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; color: #777;">No pending loan requests.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Deposit Money Form -->
        <div class="card">
            <h3><i class="fas fa-hand-holding-usd"></i> Process Cash Deposit for Client</h3>
            <form action="" method="POST">
                <div class="form-group">
                    <label for="account_number">Client Account Number (10 Digits)</label>
                    <input type="text" name="account_number" id="account_number" placeholder="Enter 10-digit account number" maxlength="10" required>
                </div>

                <div class="form-group">
                    <label for="amount">Deposit Amount ($)</label>
                    <input type="text" name="amount" id="amount" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '')" required>
                </div>

                <button type="submit" name="admin_deposit" class="submit-btn">
                    <i class="fas fa-check-circle"></i> Confirm & Add to Balance
                </button>
            </form>
        </div>

        <!-- Registered Clients Table -->
        <div class="card">
            <h3><i class="fas fa-users"></i> Registered Clients</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Account Number</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Current Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($clients)): ?>
                        <?php foreach ($clients as $c): 
                            $c_id  = $c['id'] ?? '';
                            $c_acc = $c['account_number'] ?? 'N/A';
                            $c_usr = $c['username'] ?? '';
                            $c_eml = $c['email'] ?? '';
                            $c_bal = $c['balance'] ?? 0;
                        ?>
                        <tr>
                            <td><?php echo $c_id; ?></td>
                            <td style="font-weight: bold; color: #27ae60;"><?php echo htmlspecialchars($c_acc); ?></td>
                            <td><?php echo htmlspecialchars($c_usr); ?></td>
                            <td><?php echo htmlspecialchars($c_eml); ?></td>
                            <td style="color: #27ae60; font-weight: bold;">$<?php echo number_format($c_bal, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: #777;">No clients found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- System Recent Transactions Table -->
        <div class="card">
            <h3><i class="fas fa-history"></i> System Recent Transactions</h3>
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Client</th>
                        <th>Account Number</th>
                        <th>Type / Service</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($transactions)): ?>
                        <?php foreach ($transactions as $t): 
                            $t_date = $t['created_at'] ?? '';
                            $t_user = $t['username'] ?? '';
                            $t_acc  = $t['account_number'] ?? 'N/A';
                            $t_serv = $t['service_type'] ?? $t['type'] ?? 'Deposit';
                            $t_amt  = $t['amount'] ?? 0;
                            $t_stat = $t['status'] ?? '';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t_date); ?></td>
                            <td><?php echo htmlspecialchars($t_user); ?></td>
                            <td><?php echo htmlspecialchars($t_acc); ?></td>
                            <td><?php echo htmlspecialchars($t_serv); ?></td>
                            <td style="font-weight: bold;">$<?php echo number_format($t_amt, 2); ?></td>
                            <td>
                                <span style="color: <?php echo ($t_stat == 'approved') ? '#27ae60' : (($t_stat == 'pending') ? '#f39c12' : '#c0392b'); ?>; font-weight: bold;">
                                    <?php echo htmlspecialchars($t_stat); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #777;">No transactions recorded yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

</body>
</html>