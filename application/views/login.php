<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>HolidayGoGoGo | Login</title>
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="icon">
    <link href="<?php echo base_url('assets/image/favicon.png'); ?>" rel="apple-touch-icon">
    <link href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/login.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/plugins-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/prismjs-bundle.css'); ?>" type="text/css" rel="stylesheet">
    <link href="<?php echo base_url('assets/css/style-bundle.css'); ?>" type="text/css" rel="stylesheet">
</head>

<body>
    <div class="login login-2 login-signin-on d-flex flex-row-fluid">
        <div class="d-flex flex-center flex-row-fluid bgi-size-cover bgi-position-top bgi-no-repeat" style="background-image:url(<?php echo base_url('assets/image/login.jpg'); ?>);">
            <div class="login-form p-7 position-relative overflow-hidden">
                <div class="d-flex flex-center mb-15">
                    <a>
                        <img src="<?php echo base_url('assets/image/logo.png'); ?>" class="max-h-75px">
                    </a>
                </div>
                <div class="login-signin">
                    <form action="<?php echo base_url('Login') ?>" method="post" class="form">
                        <div class="form-group mb-5">
                            <label>Username</label>
                            <div class="input-icon">
                                <input required type="text" name="username" autocomplete="off" class="form-control">
                                <span>
                                    <i class="la la-user"></i>
                                </span>
                            </div>
                        </div>
                        <div class="form-group mb-5">
                            <label>Password</label>
                            <div class="input-icon">
                                <input required type="password" name="password" autocomplete="off" class="form-control">
                                <span>
                                    <i class="la la-key"></i>
                                </span>
                            </div>
                        </div>
                        <div class="text-center mt-10">
                            <input type="submit" name="login" value="Login" class="btn btn-primary font-weight-bold px-9 py-4 my-3 mx-4" style="width:180px;">
                            <?php if(isset($error_message)) { ?>
                                <br><br>
                                <div class="alert alert-custom alert-light-danger fade show mb-5">
                                    <div class="alert-text"><?php echo $error_message; ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>

</html>