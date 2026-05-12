<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Verify Identity</title>
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="icon">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .dashboard-header {
            background: #162447;
            color: white;
            padding: 10px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
        }

        .header-title img {
            max-height: 75px;
        }

        /* Verify Container */
        .verify-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
        }

        .verify-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            text-align: center;
        }

        .verify-icon {
            width: 64px;
            height: 64px;
            background: #e8f0fe;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .verify-icon svg {
            width: 32px;
            height: 32px;
            color: #162447;
        }

        .verify-card h2 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 8px;
            color: #162447;
        }

        .verify-card .customer-name {
            font-size: 15px;
            color: #666;
            margin-bottom: 6px;
        }

        .verify-card .instruction {
            font-size: 14px;
            color: #888;
            margin-bottom: 28px;
        }

        .verify-input {
            width: 100%;
            padding: 14px 16px;
            font-size: 24px;
            font-family: 'Poppins', sans-serif;
            text-align: center;
            letter-spacing: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            outline: none;
            transition: border-color 0.2s;
        }

        .verify-input:focus {
            border-color: #162447;
        }

        .verify-input.error {
            border-color: #e74c3c;
        }

        .verify-btn {
            width: 100%;
            padding: 14px;
            background: #162447;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
            margin-top: 20px;
            transition: background 0.2s;
        }

        .verify-btn:hover {
            background: #1e3a5f;
        }

        .verify-btn:disabled {
            background: #aaa;
            cursor: not-allowed;
        }

        .error-msg {
            color: #e74c3c;
            font-size: 14px;
            margin-top: 12px;
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .verify-card {
                padding: 28px 20px;
            }

            .verify-card h2 {
                font-size: 20px;
            }

            .verify-input {
                font-size: 20px;
                letter-spacing: 8px;
            }

            .header-title img {
                max-height: 50px;
            }

            .header-content {
                padding: 0 10px;
            }
        }
    </style>
</head>
<body>

<!-- Header -->
<div class="dashboard-header">
    <div class="header-content">
        <div class="header-title">
            <img src="<?php echo base_url('assets/image/logo.png'); ?>">
        </div>
    </div>
</div>

<!-- Verification Form -->
<div class="verify-wrapper">
    <div class="verify-card">
        <div class="verify-icon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
        </div>
        <h2>Identity Verification</h2>
        <p class="customer-name"><?php echo htmlspecialchars($customer_name); ?></p>
        <p class="instruction">Enter the last 4 digits of your phone number to continue.</p>

        <form method="POST" action="<?php echo site_url('customer/' . $hash . '/verify'); ?>">
            <input
                type="text"
                name="phone_last4"
                class="verify-input<?php echo $error ? ' error' : ''; ?>"
                maxlength="4"
                pattern="[0-9]{4}"
                inputmode="numeric"
                autocomplete="off"
                placeholder="••••"
                required
                autofocus
            >
            <button type="submit" class="verify-btn">Verify</button>
            <?php if ($error): ?>
                <p class="error-msg"><?php echo htmlspecialchars($error); ?></p>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    // Auto-focus and restrict input to digits only
    document.addEventListener('DOMContentLoaded', function() {
        var input = document.querySelector('.verify-input');
        if (input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '');
            });
        }
    });
</script>

</body>
</html>
