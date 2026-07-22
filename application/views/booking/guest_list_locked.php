<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>HolidayGoGoGo | Guest List</title>
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="icon">
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/login.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/plugins-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/prismjs-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/style-bundle.css?param=kiriel'); ?>" type="text/css" rel="stylesheet">
    <script src="<?php echo base_url('assets/js/plugins-bundle.js'); ?>"></script>
</head>

<body>
    <div class="login login-2 login-signin-on d-flex flex-row-fluid">
        <div class="d-flex flex-center flex-row-fluid bgi-size-cover bgi-position-top bgi-no-repeat" style="background-image:url(<?php echo base_url('assets/image/login.jpg'); ?>);">
            <div class="container p-7 position-relative overflow-hidden">
                <div class="d-flex flex-center mb-10">
                    <a>
                        <img src="<?php echo base_url('assets/image/logo.png'); ?>" class="max-h-75px">
                    </a>
                </div>
                <div class="text-center">
                    <div class="alert alert-warning" style="max-width: 600px; margin: 0 auto; padding: 30px; font-size: 16px;">
                        <h3 style="margin-bottom: 20px; color: #856404;">
                            <i class="la la-lock" style="font-size: 24px;"></i> Guest List Locked
                        </h3>
                        <p style="margin-bottom: 15px; font-weight: 500;">
                            This guest list is currently being edited by another user. Please try again later.
                        </p>
                        <?php if(!empty($expires_at)) { ?>
                            <p style="margin-bottom: 0; font-size: 14px; color: #666;">
                                Lock expires at: <?php echo date('g:i A', strtotime($expires_at)); ?>
                            </p>
                        <?php } ?>
                    </div>
                    <div style="margin-top: 30px;">
                        <button onclick="window.location.reload();" class="btn btn-primary">
                            <i class="la la-refresh"></i> Refresh Page
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds to check if lock is released
        setInterval(function() {
            window.location.reload();
        }, 30000);
    </script>
</body>

</html>

