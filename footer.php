<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    header("Location: login.php");
    exit();
}
?>
</main>
<footer
    style="background: var(--footer-bg); backdrop-filter: blur(10px); border-top: 1px solid var(--dark-border); padding: 30px 0; margin-top: auto;">
    <div class="container text-center" style="text-align: center;">
        <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 500; margin: 0;">
            &copy; <?php echo date("Y"); ?>
            <span
                style="background: linear-gradient(135deg, #06b6d4 0%, #8b5cf6 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: 800;">
                PixelPanga Tech
            </span>. All Rights Reserved.
        </p>
        <p style="color: #475569; font-size: 0.85rem; margin-top: 8px; margin-bottom: 0; letter-spacing: 0.5px;">
            <i class="fa-solid fa-shield-halved me-1 text-emerald-500"></i> Secure Internal Device Tracking System
        </p>
    </div>
</footer>


</body>

</html>