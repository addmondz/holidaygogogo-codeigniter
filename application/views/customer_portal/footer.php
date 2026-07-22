<footer class="portal-footer">
    <div class="portal-footer-links">
        <a href="https://www.holidaygogogo.com/terms-condition/" target="_blank">Terms & Condition</a>
        <span class="portal-footer-separator">|</span>
        <a href="https://www.holidaygogogo.com/pdpa-notice/" target="_blank">PDPA Notice</a>
        <span class="portal-footer-separator">|</span>
        <a href="https://www.holidaygogogo.com/privacy-policy/" target="_blank">Privacy Policy</a>
    </div>
</footer>

<style>
    .portal-footer {
        background-color: #162447;
        padding: 20px 0;
        text-align: center;
        margin-top: 40px;
    }

    .portal-footer-links {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        font-family: 'Poppins', sans-serif;
        font-size: 13px;
    }

    .portal-footer-links a {
        color: #ffffff;
        text-decoration: none;
        transition: opacity 0.2s;
    }

    .portal-footer-links a:hover {
        opacity: 0.8;
        text-decoration: underline;
    }

    .portal-footer-separator {
        color: rgba(255, 255, 255, 0.4);
    }

    @media (max-width: 480px) {
        .portal-footer-links {
            flex-direction: column;
            gap: 8px;
        }

        .portal-footer-separator {
            display: none;
        }
    }
</style>
