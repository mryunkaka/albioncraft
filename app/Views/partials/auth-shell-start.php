<?php

declare(strict_types=1);

$authNav = null;
if (is_array($auth ?? null)) {
    $authNav = $auth;
} elseif (is_array($user ?? null)) {
    $authNav = $user;
}

$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$headerTitle = (string) ($header_title ?? 'Albion Crafting Profit Calculator');
$showGuideButton = ($show_guide_button ?? false) === true;
$adminOpen = str_starts_with($requestPath, '/admin/');
$navClass = static function (string $path, bool $exact = true) use ($requestPath): string {
    $active = $exact ? $requestPath === $path : str_starts_with($requestPath, $path);
    return $active ? 'nav-link active' : 'nav-link';
};
?>
<body>
  <style>
    .sidebar-body { display:flex; min-height:0; flex:1 1 0%; flex-direction:column; gap:1rem; }
    .sidebar-menu-area { min-height:0; flex:1 1 0%; overflow-y:auto; padding-right:.25rem; }
    .sidebar-meta { display:flex; flex-direction:column; gap:1rem; }
    .sidebar-admin-group { display:flex; flex-direction:column; gap:.5rem; }
    .sidebar-admin-group summary { list-style:none; cursor:pointer; }
    .sidebar-admin-group summary::-webkit-details-marker { display:none; }
    .nav-link-admin { border:1px solid #fecaca; background:#fef2f2; color:#b91c1c; }
    .nav-link-admin:hover { border-color:#fca5a5; background:#fee2e2; color:#991b1b; }
    .nav-link-admin.active { border-color:#f87171; background:#b91c1c; color:#fff; box-shadow:0 20px 40px rgba(127,29,29,.16); }
    .sidebar-submenu { margin-left:.5rem; display:flex; flex-direction:column; gap:.375rem; border-left:2px solid #fecaca; padding-left:.75rem; }
    .nav-sublink { display:block; border-radius:.75rem; padding:.45rem .75rem; font-size:.875rem; line-height:1.25rem; font-weight:500; color:#334155; transition:color .2s, background-color .2s, box-shadow .2s; }
    .nav-sublink:hover { background:#edf6ff; color:#1848df; }
    .nav-sublink.active { background:#1f5ff2; color:#fff; box-shadow:0 20px 40px rgba(11,41,86,.1); }
    .nav-link-stack { display:flex; flex-direction:column; gap:.15rem; }
    .nav-link-note { font-size:.72rem; line-height:1rem; color:#64748b; }
    .nav-link.active .nav-link-note,
    .nav-link:hover .nav-link-note { color:inherit; opacity:.9; }
  </style>
  <div class="app-shell">
    <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
    <aside class="app-sidebar animate-stagger">
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="sidebar-brand">AlbionCraft</div>
          <div class="sidebar-subtitle">Profit Calculator Suite</div>
        </div>
        <button class="button button-ghost" type="button" id="close-sidebar">Close</button>
      </div>
      <div class="sidebar-body">
        <div class="sidebar-menu-area">
          <nav class="sidebar-nav">
            <a class="<?= $navClass('/calculator') ?>" href="/calculator">Calculator</a>
            <a class="<?= $navClass('/dashboard') ?>" href="/dashboard">
              <span class="nav-link-stack">
                <span>Dashboard</span>
                <span class="nav-link-note">Khusus Medium &amp; Pro</span>
              </span>
            </a>
            <a class="<?= $navClass('/subscription') ?>" href="/subscription">Subscription</a>
            <a class="<?= $navClass('/referral') ?>" href="/referral">Referral</a>
            <a class="<?= $navClass('/price-data') ?>" href="/price-data">
              <span class="nav-link-stack">
                <span>Data Harga</span>
                <span class="nav-link-note">Khusus Pro</span>
              </span>
            </a>
            <?php if (($is_admin ?? false) === true): ?>
              <details class="sidebar-admin-group" <?= $adminOpen ? 'open' : '' ?>>
                <summary class="nav-link nav-link-admin<?= $adminOpen ? ' active' : '' ?>">Admin</summary>
                <div class="sidebar-submenu">
                  <a class="<?= str_starts_with($requestPath, '/admin/users') ? 'nav-sublink active' : 'nav-sublink' ?>" href="/admin/users">User Management</a>
                  <a class="<?= str_starts_with($requestPath, '/admin/subscription-requests') ? 'nav-sublink active' : 'nav-sublink' ?>" href="/admin/subscription-requests">Subscription Requests</a>
                  <a class="<?= str_starts_with($requestPath, '/admin/subscription-actions') ? 'nav-sublink active' : 'nav-sublink' ?>" href="/admin/subscription-actions">Action History</a>
                </div>
              </details>
            <?php endif; ?>
          </nav>
        </div>
        <div class="sidebar-meta">
          <div class="widget animate-float">
            <div class="widget-title">Mode</div>
            <div class="widget-value">STRICT</div>
            <div class="widget-muted">Sesuai docs/11 & docs/12</div>
          </div>
          <div class="widget">
            <div class="widget-title">Asset Pipeline</div>
            <div class="widget-value">Tailwind</div>
            <div class="widget-muted">Local build, no CDN</div>
          </div>
        </div>
      </div>
    </aside>

    <div class="app-main">
      <header class="app-header">
        <div class="header-inner">
          <div>
            <div class="mobile-brand">AlbionCraft</div>
            <div class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($headerTitle) ?></div>
          </div>
          <div class="header-actions">
            <button class="button button-ghost" type="button" id="toggle-sidebar">Menu</button>
            <?php if ($showGuideButton): ?>
              <button class="button button-secondary" type="button" id="open-help-modal">Guide</button>
            <?php endif; ?>
            <?php if ($authNav !== null): ?>
              <form method="post" action="/logout">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                <button class="button button-ghost" type="submit">Logout</button>
              </form>
            <?php else: ?>
              <a class="button button-ghost" href="/login">Login</a>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <main class="app-content">
        <div class="container stack">
