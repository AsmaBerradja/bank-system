<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'client') {
    header("Location: Login.html");
    exit();
}

include 'db.php';

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_transaction'])) {
    $service_type = $_POST['service_type'];
    $amount = floatval($_POST['amount'] ?? 0);
    $phone_number = trim($_POST['phone_number'] ?? '');
    $recipient_account = trim($_POST['recipient_account'] ?? '');
    $term_period = $_POST['term_period'] ?? '1_year';
    $custom_months = intval($_POST['custom_months'] ?? 0);

    if (empty($phone_number)) {
        $error = "الرجاء إدخال رقم الهاتف.";
    } elseif ($amount <= 0) {
        $error = "الرجاء إدخال مبلغ صحيح أكبر من الصفر (أرقام موجبة فقط).";
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT balance FROM users WHERE id = ? FOR UPDATE");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_balance = $user['balance'];

            $db_type = '';
            $details_text = '';
            $duration_text = '';

            switch ($service_type) {
                case 'transfer':
                    $db_type = 'transfer';
                    if (empty($recipient_account)) {
                        throw new Exception("الرجاء إدخال رقم بطاقة أو حساب المستلم.");
                    }
                    
                    $rec_stmt = $pdo->prepare("SELECT id FROM users WHERE account_number = ? AND role = 'client'");
                    $rec_stmt->execute([$recipient_account]);
                    $recipient = $rec_stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$recipient) {
                        throw new Exception("حساب المستلم غير موجود.");
                    }
                    if ($recipient['id'] == $_SESSION['user_id']) {
                        throw new Exception("لا يمكنك التحويل إلى حسابك الشخصي.");
                    }
                    if ($current_balance < $amount) {
                        throw new Exception("رصيدك غير كافٍ لإتمام التحويل.");
                    }
                    $new_balance = $current_balance - $amount;
                    
                    $upd_rec = $pdo->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
                    $upd_rec->execute([$amount, $recipient['id']]);
                    
                    $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $update_stmt->execute([$new_balance, $_SESSION['user_id']]);

                    $details_text = "Recipient Account: " . $recipient_account . ", Amount: " . $amount . " DZD";
                    
                    $trans_stmt = $pdo->prepare("INSERT INTO account_requests (user_id, service_type, amount, details, status) VALUES (?, ?, ?, ?, 'approved')");
                    $trans_stmt->execute([$_SESSION['user_id'], $db_type, $amount, $details_text]);
                    break;

                case 'savings':
                    $db_type = 'fixed_savings';
                    $duration_text = ($term_period === 'custom') ? "$custom_months Months" : str_replace('_', ' ', $term_period);
                    
                    if ($amount < 2000) {
                        throw new Exception("الحد الأدنى لفتح حساب التوفير الثابت هو 2000 دينار.");
                    }
                    if ($current_balance < $amount) {
                        throw new Exception("رصيدك غير كافٍ لفتح حساب التوفير الثابت.");
                    }
                    $new_balance = $current_balance - $amount;

                    $update_stmt = $pdo->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $update_stmt->execute([$new_balance, $_SESSION['user_id']]);

                    $details_text = "Account Type: Personal Fixed Savings, Term: " . $duration_text . ", Initial Deposit: " . $amount . " DZD, Interest Rate: 4.0%";

                    $trans_stmt = $pdo->prepare("INSERT INTO account_requests (user_id, service_type, amount, details, status) VALUES (?, ?, ?, ?, 'pending')");
                    $trans_stmt->execute([$_SESSION['user_id'], $db_type, $amount, $details_text]);
                    break;

                default:
                    throw new Exception("نوع الخدمة المختارة غير صالح.");
            }

            $pdo->commit();

            if ($service_type === 'savings') {
                $message = "<strong>تم إرسال طلب حساب التوفير الشخصي الثابت بنجاح!</strong><br><br>"
                         . "<div style='text-align: left; direction: ltr; background: #1a1a1a; padding: 15px; border-radius: 5px; border: 1px solid #27ae60; display: inline-block; width: 100%;'>"
                         . "Account Type &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Personal Savings (Fixed/Term)<br>"
                         . "Term Period &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; " . htmlspecialchars($duration_text) . "<br>"
                         . "Currency &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; DZD<br>"
                         . "Initial Deposit &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; " . number_format($amount, 2) . " DZD<br>"
                         . "Interest Rate &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; 4.0%"
                         . "</div>";
            } elseif ($service_type === 'transfer') {
                $message = "<strong>تمت عملية التحويل بنجاح!</strong><br><br>"
                         . "<div style='text-align: left; direction: ltr; background: #1a1a1a; padding: 15px; border-radius: 5px; border: 1px solid #27ae60; display: inline-block; width: 100%;'>"
                         . "Operation &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; Account Transfer<br>"
                         . "Recipient Account &nbsp; " . htmlspecialchars($recipient_account) . "<br>"
                         . "Amount &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; " . number_format($amount, 2) . " DZD<br>"
                         . "Currency &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; DZD"
                         . "</div>";
            }

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "خطأ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Services - Bank of Algeria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .trans-section {
            padding: 120px 9%;
            background: #111;
            color: #fff;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .trans-form-container {
            background: #222;
            padding: 30px;
            border-radius: 10px;
            width: 100%;
            max-width: 550px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
        }
        .trans-form-container h2 {
            margin-bottom: 20px;
            color: #27ae60;
            text-align: center;
            font-size: 2.2rem;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            background: #333;
            border: 1px solid #444;
            color: #fff;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus {
            border-color: #27ae60;
            outline: none;
        }
        .submit-btn {
            width: 100%;
            padding: 12px;
            background: #27ae60;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 1.1rem;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
        }
        .submit-btn:hover {
            background: #219653;
        }
        .alert-success {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.1rem;
            border: 1px solid #27ae60;
        }
        .alert-error {
            background: rgba(192, 57, 43, 0.2);
            color: #e74c3c;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
        }
        .back-btn {
            display: inline-block;
            width: 100%;
            text-align: center;
            padding: 12px;
            background: #333;
            color: #fff;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: bold;
            transition: 0.3s;
        }
        .back-btn:hover {
            background: #444;
            color: #27ae60;
        }
        .back-link {
            display: block;
            text-align: center;
            margin-top: 15px;
            color: #aaa;
            text-decoration: none;
        }
        .back-link:hover {
            color: #fff;
        }
    </style>
</head>
<body>

<header class="header">
    <a href="#" class="logo"> bank </a>
    <nav class="navbar">
        <a href="client_dashboard.php">Dashboard</a>
        <a href="#" class="active">Bank Services</a>
    </nav>
    <div class="icons">
        <a href="logout.php" class="fas fa-sign-out-alt" title="Logout"></a>
    </div>
</header>

<section class="trans-section">
    <div class="trans-form-container">
        <h2>Select Bank Service</h2>

        <?php if (!empty($message)): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                <?php echo $message; ?>
            </div>
            <a href="client_dashboard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back To Dashboard</a>

        <?php else: ?>

            <?php if (!empty($error)): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form action="transactions.php" method="POST" id="serviceForm">
                <div class="form-group">
                    <label for="service_type">Choose Service / Operation</label>
                    <select name="service_type" id="service_type" required onchange="toggleFields()">
                        <option value="savings">Open Savings Account (فتح حساب التوفير الشخصي)</option>
                        <option value="transfer">Account / Card Transfer (تحويل إلى بطاقة أو حساب آخر)</option>
                    </select>
                </div>

                <div class="form-group" id="fixedTermGroup">
                    <label for="term_period">Select Term Period (مدة التوفير)</label>
                    <select name="term_period" id="term_period" onchange="toggleCustomMonths()">
                        <option value="1_year">1 Year (سنة واحدة)</option>
                        <option value="2_years">2 Years (سنتان)</option>
                        <option value="custom">Custom Duration (مدة مخصصة)</option>
                    </select>
                </div>

                <div class="form-group" id="customMonthsGroup" style="display: none;">
                    <label for="custom_months">Enter Duration in Months (حدد المدة بالأشهر)</label>
                    <input type="text" inputmode="numeric" name="custom_months" id="custom_months" placeholder="E.G., 18" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                </div>

                <div class="form-group" id="recipientGroup" style="display: none;">
                    <label for="recipient_account">Recipient Card Number / Account ID (رقم بطاقة أو حساب المستلم)</label>
                    <input type="text" name="recipient_account" id="recipient_account" placeholder="Enter recipient's card number or ID">
                </div>

                <div class="form-group" id="amountGroup">
                    <label for="amount">Amount (DZD) (كمية الأموال)</label>
                    <input type="text" inputmode="decimal" name="amount" id="amount" placeholder="0.00" oninput="this.value = this.value.replace(/[^0-9.]/g, '')" required>
                </div>

                <div class="form-group">
                    <label for="phone_number">Phone Number (رقم الهاتف)</label>
                    <input type="text" name="phone_number" id="phone_number" placeholder="e.g., 0550000000" required>
                </div>

                <div class="form-group">
                    <label for="otp_code">SMS Verification Code (رمز التأكيد)</label>
                    <input type="text" name="otp_code" id="otp_code" placeholder="Enter verification code">
                </div>

                <button type="submit" name="confirm_transaction" class="submit-btn"><i class="fas fa-check-circle"></i> Confirm & Proceed</button>
            </form>

            <a href="client_dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back To Dashboard</a>

        <?php endif; ?>
    </div>
</section>

<script>
function toggleFields() {
    const serviceType = document.getElementById('service_type');
    if (!serviceType) return;

    const fixedTermGroup = document.getElementById('fixedTermGroup');
    const recipientGroup = document.getElementById('recipientGroup');
    const recipientInput = document.getElementById('recipient_account');

    if (serviceType.value === 'savings') {
        fixedTermGroup.style.display = 'block';
        toggleCustomMonths();
    } else {
        fixedTermGroup.style.display = 'none';
        document.getElementById('customMonthsGroup').style.display = 'none';
    }

    if (serviceType.value === 'transfer') {
        recipientGroup.style.display = 'block';
        recipientInput.required = true;
    } else {
        recipientGroup.style.display = 'none';
        recipientInput.required = false;
    }
}

function toggleCustomMonths() {
    const termPeriod = document.getElementById('term_period');
    const customGroup = document.getElementById('customMonthsGroup');
    if (termPeriod && termPeriod.value === 'custom') {
        customGroup.style.display = 'block';
    } else {
        customGroup.style.display = 'none';
    }
}

window.onload = function() {
    toggleFields();
};
</script>

</body>
</html>