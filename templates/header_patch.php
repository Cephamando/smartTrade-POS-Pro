<?php
// We will insert this logic into the header
$isChef = in_array($_SESSION['role'] ?? '', ['chef', 'head_chef', 'admin', 'dev']);
?>
<?php if ($isChef): ?>
<li class="nav-item">
    <a class="nav-link <?= isActive('menu') ?>" href="index.php?page=menu">
        <i class="bi bi-egg-fried"></i> Menu Manager
    </a>
</li>
<?php endif; ?>
