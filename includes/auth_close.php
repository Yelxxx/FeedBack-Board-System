<?php
// ============================================================================
//  Closes the authentication page shell.
// ============================================================================
if (!defined('APP_NAME')) {
    http_response_code(403);
    exit('Access denied.');
}
$base       = $base ?? '../';
$authLayout = $authLayout ?? 'center';
$isSplit    = $authLayout === 'split';
?>
<?php if ($isSplit): ?>
      </div><!-- /.auth-card-form -->
      <p class="auth-footer"><?= e(APP_NAME) ?> · <a href="<?= e($base) ?>index.php">View the Public Board</a></p>
    </div><!-- /.auth-panel-card -->
  </div><!-- /.auth-panel -->
</div><!-- /.auth-split -->
<?php else: ?>
  </div><!-- /.auth-card -->
  <p class="auth-footer"><?= e(APP_NAME) ?> · <a href="<?= e($base) ?>index.php">View the Public Board</a></p>
</div><!-- /.auth-shell -->
<?php endif; ?>

<script src="<?= e($base) ?>assets/js/main.js"></script>
</body>
</html>
