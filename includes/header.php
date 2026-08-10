<?php
/**
 * @var string $pageTitle
 * @var string $activeMenu one of: dashboard, customers, calendar
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/branch.php';
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle ?? '予約管理システム') ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="/reservation_system_study/assets/css/app.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>
<div class="app-shell">
  <div class="sidebar">
    <div class="brand">RSVP</div>
    <a class="nav-link <?= ($activeMenu ?? '') === 'dashboard' ? 'active' : '' ?>" href="/reservation_system_study/admin/index.php">📊 ダッシュボード</a>
    <a class="nav-link <?= ($activeMenu ?? '') === 'customers' ? 'active' : '' ?>" href="/reservation_system_study/admin/customers.php">👤 顧客管理</a>
    <a class="nav-link <?= ($activeMenu ?? '') === 'calendar' ? 'active' : '' ?>" href="/reservation_system_study/admin/calendar.php">📅 スケジュール管理</a>
    <?php if (is_admin()): ?>
    <a class="nav-link <?= ($activeMenu ?? '') === 'staff' ? 'active' : '' ?>" href="/reservation_system_study/admin/staff.php">🔑 スタッフ管理</a>
    <?php endif; ?>
  </div>
  <div class="main">
    <div class="topbar">
      <div class="d-flex align-items-center gap-3">
        <div class="branch-switcher" id="branchSwitcher">
          <button type="button" class="branch-switcher-btn" id="branchSwitcherBtn">
            <span id="branchSwitcherLabel"><?= htmlspecialchars(current_branch_name()) ?></span>
            <span class="chevron">▾</span>
          </button>
          <div class="branch-dropdown" id="branchDropdown">
            <input type="text" id="branchSearchInput" class="form-control form-control-sm" placeholder="店舗名を検索">
            <div id="branchList" class="branch-list"></div>
            <?php if (is_admin()): ?>
            <button type="button" class="branch-add-btn" id="branchAddBtn">+ 店舗を追加</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="center-name text-secondary" style="font-size:13px;"><?= htmlspecialchars($pageTitle ?? '') ?></div>
      </div>
      <div class="user">
        <?= htmlspecialchars($_SESSION['staff_name'] ?? '') ?>さん
        <a href="/reservation_system_study/admin/logout.php" class="ms-2">ログアウト</a>
      </div>
    </div>
    <div class="content">
