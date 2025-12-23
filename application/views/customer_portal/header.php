<style>
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

    .header-title h1 {
        font-size: 24px;
        font-weight: 600;
        margin: 0;
    }

    .header-title p {
        font-size: 14px;
        opacity: 0.9;
        margin: 5px 0 0 0;
    }

    .header-title img {
        max-height: 75px;
    }

    @media (max-width: 768px) {
        .dashboard-header {
            padding: 8px 0;
        }

        .header-content {
            padding: 0 15px;
        }

        .header-title h1 {
            font-size: 20px;
        }

        .header-title p {
            font-size: 12px;
        }
    }

    @media (max-width: 480px) {
        .header-content {
            padding: 0 10px;
        }

        .header-title h1 {
            font-size: 18px;
        }
    }
</style>

<!-- Header -->
<div class="dashboard-header">
    <div class="header-content">
        <div class="header-title">
            <img src="<?php echo base_url('assets/image/logo.png'); ?>">
        </div>
    </div>
</div>

