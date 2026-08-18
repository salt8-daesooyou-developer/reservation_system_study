<?php
/**
 * 顧客ダッシュボード（管理者の顧客詳細ページ／顧客本人のマイページ 共用パーシャル）
 *
 * @param int  $customerId
 * @param bool $isAdminView 管理者から見ている場合 true。ログ記録・特記事項メモタブと管理系フォームを表示する。
 */
function render_customer_dashboard(PDO $pdo, int $customerId, bool $isAdminView): void {
    $stmt = $pdo->prepare('SELECT c.*, b.name AS branch_name FROM customers c LEFT JOIN branches b ON b.id = c.branch_id WHERE c.id = ?');
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        echo '<div class="panel text-secondary">顧客が見つかりません。</div>';
        return;
    }

    $statusLabel = ['active' => 'アクティブ', 'expired' => '期限切れ', 'pending' => '予定', 'hold' => '休会中', 'unregistered' => '未登録'];

    $tabs = [
        ['id' => 'dashboard', 'label' => 'ダッシュボード'],
        ['id' => 'products', 'label' => '商品内訳'],
        ['id' => 'payments', 'label' => '決済内訳'],
        ['id' => 'attendance', 'label' => '出席内訳'],
        ['id' => 'reservations', 'label' => '予約内訳'],
        ['id' => 'events', 'label' => 'ホールド・延長・譲渡'],
        ['id' => 'contracts', 'label' => '契約書'],
        ['id' => 'coupons', 'label' => 'クーポン・マイレージ'],
    ];
    if ($isAdminView) {
        $tabs[] = ['id' => 'logs', 'label' => 'ログ記録'];
        $tabs[] = ['id' => 'notes', 'label' => '特記事項・メモ'];
    }
    ?>
    <div id="customerDashboard">
      <div class="panel mb-3">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <div style="font-size:18px; font-weight:700;"><?= htmlspecialchars($customer['name']) ?><?= $isAdminView ? '' : ' 様' ?></div>
            <div class="text-secondary" style="font-size:13px;">
              連絡先: <?= htmlspecialchars($customer['phone'] ?? '-') ?>
              ・ 状態: <span class="badge-status badge-<?= htmlspecialchars($customer['status']) ?>"><?= $statusLabel[$customer['status']] ?? $customer['status'] ?></span>
            </div>
            <div class="text-secondary" style="font-size:13px; margin-top:4px;">
              ご利用店舗: <?= htmlspecialchars($customer['branch_name'] ?? '未登録') ?>
              ・ マイレージ: <span id="dashMileagePoints"><?= (int) $customer['mileage_points'] ?></span>pt
            </div>
          </div>
          <?php if (!$isAdminView): ?>
          <a href="/reservation_system_study/customer/account.php" class="btn btn-outline-light btn-sm">⚙ 設定</a>
          <?php else: ?>
          <a href="/reservation_system_study/customer/booking.php" class="btn-accent btn-sm" style="opacity:.5; pointer-events:none;">+ 予約する</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="status-tabs" id="dashTabs">
        <?php foreach ($tabs as $i => $t): ?>
        <button type="button" data-tab="<?= $t['id'] ?>" class="<?= $i === 0 ? 'active' : '' ?>"><?= htmlspecialchars($t['label']) ?></button>
        <?php endforeach; ?>
      </div>

      <div id="dashTabContent">
        <div class="dash-pane" data-pane="dashboard"></div>
        <div class="dash-pane" data-pane="products" style="display:none;"></div>
        <div class="dash-pane" data-pane="payments" style="display:none;"></div>
        <div class="dash-pane" data-pane="attendance" style="display:none;"></div>
        <div class="dash-pane" data-pane="reservations" style="display:none;"></div>
        <div class="dash-pane" data-pane="events" style="display:none;"></div>
        <div class="dash-pane" data-pane="contracts" style="display:none;"></div>
        <div class="dash-pane" data-pane="coupons" style="display:none;"></div>
        <?php if ($isAdminView): ?>
        <div class="dash-pane" data-pane="logs" style="display:none;"></div>
        <div class="dash-pane" data-pane="notes" style="display:none;"></div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      window.CD_CUSTOMER_ID = <?= (int) $customerId ?>;
      window.CD_IS_ADMIN = <?= $isAdminView ? 'true' : 'false' ?>;
    </script>
    <script src="/reservation_system_study/assets/js/customer_dashboard.js"></script>
    <?php
}
