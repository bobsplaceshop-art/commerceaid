<?php
/**
 * Invitation invalid or expired.
 *
 * Rendered through layouts.auth. Matches the shape of the
 * "Link expired" branch in resources/views/auth/reset-password.php.
 *
 * Shown when:
 *   - The token does not exist
 *   - The token is expired
 *   - The user is no longer in 'pending' state
 */
?>

<h1 class="h4 mb-1">Invitation not valid</h1>
<p class="muted small mb-4">
    That invitation link is either expired or has already been used.
</p>

<div class="alert alert-warning mb-3" role="alert" style="font-size:.85rem;">
    Ask an administrator to send you a new invitation from the admin panel.
</div>

<a href="/login" class="btn btn-primary w-100 py-2">
    <i class="bi bi-arrow-left me-1"></i>
    Back to sign in
</a>
