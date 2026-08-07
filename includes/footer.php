<?php
// ============================================================================
//  Shared page footer.
// ============================================================================
if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Access denied.');
}
$base = $base ?? '';
?>
</main>

<footer class="footer">
  <div class="container">
    <p><?= e(APP_NAME) ?> — Capstone Project · Built with PHP &amp; MySQL</p>
  </div>
</footer>

<script src="<?= e($base) ?>assets/js/main.js"></script>
</body>
</html>
