(function () {
  const customerId = window.CD_CUSTOMER_ID;
  const isAdmin = !!window.CD_IS_ADMIN;
  const API = '/reservation_system_study/api/customer_dashboard.php';

  const statusLabel = { active: 'アクティブ', expired: '期限切れ', pending: '予定', hold: '休会中', unregistered: '未登録' };
  const reservationStatusLabel = { reserved: '予約中', show: '出席', noshow: '欠席', cancelled: 'キャンセル' };
  const eventTypeLabel = { hold: 'ホールド', extend: '延長', transfer: '譲渡' };

  function escapeHtml(s) {
    return (s == null ? '' : String(s)).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
  }
  function fmtMoney(n) { return '¥' + Number(n || 0).toLocaleString(); }
  function fmtDateTime(s) { return s ? s.replace('T', ' ').slice(0, 16) : '-'; }

  function apiUrl(section, extra) {
    const params = new URLSearchParams(extra || {});
    params.set('section', section);
    if (isAdmin) params.set('customer_id', customerId);
    return `${API}?${params.toString()}`;
  }

  function getJson(url) { return fetch(url).then(r => r.json()); }
  function postJson(url, body) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(async r => { const data = await r.json().catch(() => ({})); return { ok: r.ok, data }; });
  }

  /* ---------- タブ切替 ---------- */

  const loadedTabs = new Set();
  const tabLoaders = {
    dashboard: loadDashboard,
    products: loadProducts,
    payments: loadPayments,
    attendance: loadAttendance,
    reservations: loadReservations,
    events: loadEvents,
    contracts: loadContracts,
    coupons: loadCoupons,
    logs: loadLogs,
    notes: loadNotes,
  };

  document.getElementById('dashTabs').addEventListener('click', e => {
    const btn = e.target.closest('button[data-tab]');
    if (!btn) return;
    const tab = btn.dataset.tab;
    document.querySelectorAll('#dashTabs button').forEach(b => b.classList.toggle('active', b === btn));
    document.querySelectorAll('.dash-pane').forEach(p => p.style.display = p.dataset.pane === tab ? '' : 'none');
    if (!loadedTabs.has(tab)) {
      loadedTabs.add(tab);
      (tabLoaders[tab] || function () {})();
    }
  });

  function switchTab(tab) {
    document.querySelector(`#dashTabs button[data-tab="${tab}"]`)?.click();
  }
  window.CD_switchTab = switchTab;

  function pane(name) { return document.querySelector(`.dash-pane[data-pane="${name}"]`); }

  /* ---------- ミニカレンダー ---------- */

  function renderMiniCalendar(dateSet) {
    const today = new Date();
    const y = today.getFullYear(), m = today.getMonth();
    const weekdayNames = ['日', '月', '火', '水', '木', '金', '土'];
    const firstDay = new Date(y, m, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(y, m + 1, 0).getDate();
    const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;
    const todayStr = today.toISOString().slice(0, 10);

    let cells = '';
    for (let i = 0; i < totalCells; i++) {
      const dayNum = i - startOffset + 1;
      if (dayNum < 1 || dayNum > daysInMonth) { cells += '<div class="mini-day empty"></div>'; continue; }
      const dateStr = `${y}-${String(m + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
      const isToday = dateStr === todayStr;
      const has = dateSet.has(dateStr);
      cells += `<div class="mini-day ${isToday ? 'today' : ''} ${has ? 'mine' : ''}"><span>${dayNum}</span>${has ? '<i class="mini-dot"></i>' : ''}</div>`;
    }

    return `<div class="mini-month" style="max-width:280px;">
      <div class="mini-month-title">${y}年${m + 1}月</div>
      <div class="mini-month-grid">
        ${weekdayNames.map(w => `<div class="mini-weekday">${w}</div>`).join('')}
        ${cells}
      </div>
    </div>`;
  }

  /* ---------- ダッシュボード ---------- */

  function loadDashboard() {
    getJson(apiUrl('dashboard')).then(d => {
      const p = pane('dashboard');
      const pass = d.pass;

      const passHtml = pass ? `
        <div class="stat-grid" style="margin-bottom:16px;">
          <div class="stat-card" style="grid-column: span 2;">
            <div class="label">現在の利用券</div>
            <div class="value" style="font-size:18px;">${escapeHtml(pass.product_name)}</div>
            <div class="sub">${escapeHtml(pass.progress_label)}・${pass.start_date}${pass.end_date ? ' 〜 ' + pass.end_date : ''}</div>
            <div class="bar"><span style="width:${pass.progress_pct}%;"></span></div>
          </div>
          <div class="stat-card">
            <div class="label">状態</div>
            <div class="value" style="font-size:18px;"><span class="badge-status badge-${pass.status}">${statusLabel[pass.status] || pass.status}</span></div>
          </div>
        </div>
      ` : `<div class="panel mb-3 text-secondary">利用中の利用券はありません。<a href="#" onclick="CD_switchTab('products'); return false;">商品内訳を見る</a></div>`;

      const dateSet = new Set(d.month_reservation_dates || []);
      const weekRows = (d.week_reservations || []).map(r => `
        <div class="panel mb-2 p-2">
          <div style="font-weight:700;">${r.schedule_date} ${r.start_time.slice(0,5)}-${r.end_time.slice(0,5)}</div>
          <div>${escapeHtml(r.class_name)} ・ ${escapeHtml(r.branch_name)}</div>
        </div>`).join('') || '<div class="text-secondary">今週の予約はありません。</div>';

      const paymentRows = (d.recent_payments || []).map(pm => `
        <tr><td>${pm.created_at.slice(0, 10)}</td><td>${escapeHtml(pm.product_name || '-')}</td><td>${fmtMoney(pm.amount)}</td></tr>
      `).join('') || '<tr><td colspan="3" class="text-secondary">決済履歴はありません。</td></tr>';

      let adminExtra = '';
      if (isAdmin) {
        const noteRows = (d.recent_notes || []).map(n => `<div class="mb-2"><div style="font-size:12px;" class="text-secondary">${fmtDateTime(n.created_at)} ・ ${escapeHtml(n.staff_name || '-')}</div><div>${escapeHtml(n.body)}</div></div>`).join('') || '<div class="text-secondary">メモはありません。</div>';
        const logRows = (d.recent_logs || []).map(l => `<div class="mb-2"><div style="font-size:12px;" class="text-secondary">${fmtDateTime(l.created_at)} ・ ${escapeHtml(l.service_type)}</div><div>${escapeHtml(l.description)}</div></div>`).join('') || '<div class="text-secondary">ログはありません。</div>';
        adminExtra = `
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <div class="panel">
              <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">特記事項・メモ</h6><a href="#" onclick="CD_switchTab('notes'); return false;">もっと見る ›</a></div>
              ${noteRows}
            </div>
          </div>
          <div class="col-md-6">
            <div class="panel">
              <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">最近のログ記録</h6><a href="#" onclick="CD_switchTab('logs'); return false;">もっと見る ›</a></div>
              ${logRows}
            </div>
          </div>
        </div>`;
      }

      p.innerHTML = `
        ${passHtml}
        <div class="row g-3">
          <div class="col-md-5">
            <div class="panel">
              <h6 class="mb-2">今月の予約カレンダー</h6>
              ${renderMiniCalendar(dateSet)}
            </div>
          </div>
          <div class="col-md-7">
            <div class="panel">
              <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">今週の予約</h6><a href="#" onclick="CD_switchTab('reservations'); return false;">もっと見る ›</a></div>
              ${weekRows}
            </div>
          </div>
        </div>
        <div class="row g-3 mt-1">
          <div class="col-md-6">
            <div class="panel">
              <div class="d-flex justify-content-between align-items-center mb-2"><h6 class="mb-0">決済内訳</h6><a href="#" onclick="CD_switchTab('payments'); return false;">もっと見る ›</a></div>
              <table class="data-table"><thead><tr><th>日付</th><th>商品</th><th>金額</th></tr></thead><tbody>${paymentRows}</tbody></table>
            </div>
          </div>
          <div class="col-md-6">
            <div class="panel">
              <h6 class="mb-2">その他</h6>
              <div class="text-secondary">契約書 ${d.contracts_count} 件 ・ 有効クーポン ${d.active_coupons_count} 件 ・ マイレージ ${d.customer.mileage_points}pt</div>
            </div>
          </div>
        </div>
        ${adminExtra}
      `;
    });
  }

  /* ---------- 商品内訳 ---------- */

  let productListCache = [];

  function loadProducts() {
    getJson(apiUrl('products')).then(rows => {
      productListCache = rows;
      const p = pane('products');
      const body = rows.length ? rows.map(m => `
        <tr>
          <td>${escapeHtml(m.product_name)}</td>
          <td>${m.product_type === 'count' ? '回数券' : '期間券'}</td>
          <td>${m.product_type === 'count' ? (m.remaining_count + '回 残り') : (m.start_date + ' 〜 ' + (m.end_date || '-'))}</td>
          <td><span class="badge-status badge-${m.status}">${statusLabel[m.status] || m.status}</span></td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-secondary">保有中の利用券はありません。</td></tr>';

      p.innerHTML = `
        <div class="panel">
          <h6 class="mb-2">利用券一覧</h6>
          <table class="data-table"><thead><tr><th>プラン名</th><th>種別</th><th>期間／回数</th><th>状態</th></tr></thead><tbody>${body}</tbody></table>
        </div>
        ${isAdmin ? `
        <div class="panel mt-3">
          <h6 class="mb-2">利用券を追加</h6>
          <div class="d-flex gap-2 flex-wrap">
            <select id="pdAddProduct" class="form-select" style="max-width:260px;"></select>
            <input type="date" id="pdAddStart" class="form-control" style="max-width:160px;">
            <button class="btn-accent" id="pdAddBtn">追加</button>
          </div>
        </div>` : ''}
      `;

      if (isAdmin) {
        document.getElementById('pdAddStart').value = new Date().toISOString().slice(0, 10);
        getJson('/reservation_system_study/api/memberships.php?products=1').then(products => {
          document.getElementById('pdAddProduct').innerHTML = products.map(pr => `<option value="${pr.id}">${escapeHtml(pr.name)}</option>`).join('');
        });
        document.getElementById('pdAddBtn').addEventListener('click', () => {
          postJson('/reservation_system_study/api/memberships.php', {
            customer_id: customerId,
            product_id: document.getElementById('pdAddProduct').value,
            start_date: document.getElementById('pdAddStart').value,
          }).then(({ ok }) => {
            if (!ok) { alert('追加に失敗しました。'); return; }
            loadedTabs.delete('products'); loadedTabs.delete('dashboard'); loadedTabs.delete('payments');
            loadProducts();
          });
        });
      }
    });
  }

  /* ---------- 決済内訳 ---------- */

  function loadPayments() {
    getJson(apiUrl('payments')).then(d => {
      const p = pane('payments');
      const rows = d.rows.length ? d.rows.map(pm => `
        <tr>
          <td>${fmtDateTime(pm.created_at)}</td>
          <td><span class="badge-status badge-${pm.type === 'refund' ? 'expired' : 'active'}">${pm.type === 'refund' ? '返金' : '売上'}</span></td>
          <td>${escapeHtml(pm.product_name || pm.memo || '-')}</td>
          <td>${fmtMoney(pm.amount)}</td>
          <td>${pm.method === 'stripe' ? 'Stripe' : '手動'}</td>
          <td>${escapeHtml(pm.staff_name || '-')}</td>
        </tr>`).join('') : '<tr><td colspan="6" class="text-secondary">決済履歴はありません。</td></tr>';

      p.innerHTML = `
        <div class="stat-grid" style="margin-bottom:16px;">
          <div class="stat-card"><div class="label">総売上額</div><div class="value">${fmtMoney(d.sale_total)}</div></div>
          <div class="stat-card"><div class="label">総返金額</div><div class="value">${fmtMoney(d.refund_total)}</div></div>
        </div>
        <div class="panel">
          <table class="data-table"><thead><tr><th>取引日時</th><th>種別</th><th>商品/内容</th><th>金額</th><th>方法</th><th>担当</th></tr></thead><tbody>${rows}</tbody></table>
        </div>
        ${isAdmin ? `
        <div class="panel mt-3">
          <h6 class="mb-2">手動登録</h6>
          <div class="d-flex gap-2 flex-wrap">
            <select id="pmType" class="form-select" style="max-width:120px;"><option value="sale">売上</option><option value="refund">返金</option></select>
            <input type="number" id="pmAmount" class="form-control" placeholder="金額" style="max-width:140px;">
            <input type="text" id="pmMemo" class="form-control" placeholder="メモ（任意）" style="max-width:200px;">
            <button class="btn-accent" id="pmAddBtn">登録</button>
          </div>
        </div>` : ''}
      `;

      if (isAdmin) {
        document.getElementById('pmAddBtn').addEventListener('click', () => {
          const amount = Number(document.getElementById('pmAmount').value);
          if (!amount) { alert('金額を入力してください。'); return; }
          postJson('/reservation_system_study/api/payments.php', {
            customer_id: customerId,
            type: document.getElementById('pmType').value,
            amount,
            memo: document.getElementById('pmMemo').value,
          }).then(({ ok }) => {
            if (!ok) { alert('登録に失敗しました。'); return; }
            loadedTabs.delete('payments'); loadedTabs.delete('dashboard');
            loadPayments();
          });
        });
      }
    });
  }

  /* ---------- 出席内訳 ---------- */

  function loadAttendance() {
    getJson(apiUrl('attendance')).then(rows => {
      const p = pane('attendance');
      const body = rows.length ? rows.map(r => `
        <tr>
          <td>${r.schedule_date}</td>
          <td>${r.start_time.slice(0,5)}-${r.end_time.slice(0,5)}</td>
          <td>${escapeHtml(r.class_name)}</td>
          <td><span class="badge-status badge-${r.status === 'show' ? 'active' : 'expired'}">${reservationStatusLabel[r.status]}</span></td>
          <td>${escapeHtml(r.branch_name)}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-secondary">出席履歴はありません。</td></tr>';
      p.innerHTML = `<div class="panel"><table class="data-table"><thead><tr><th>日付</th><th>時間</th><th>クラス</th><th>状態</th><th>店舗</th></tr></thead><tbody>${body}</tbody></table></div>`;
    });
  }

  /* ---------- 予約内訳 ---------- */

  function loadReservations() {
    getJson(apiUrl('reservations')).then(rows => {
      const p = pane('reservations');
      const todayStr = new Date().toISOString().slice(0, 10);
      const showCancel = !isAdmin;
      const body = rows.length ? rows.map(r => {
        const cancellable = showCancel && r.status === 'reserved' && r.schedule_date >= todayStr;
        return `
        <tr>
          <td>${r.schedule_date}</td>
          <td>${r.start_time.slice(0,5)}-${r.end_time.slice(0,5)}</td>
          <td>${escapeHtml(r.class_name)}</td>
          <td><span class="badge-status badge-${r.status === 'show' || r.status === 'reserved' ? 'active' : 'expired'}">${reservationStatusLabel[r.status] || r.status}</span></td>
          <td>${escapeHtml(r.branch_name)}</td>
          ${showCancel ? `<td>${cancellable ? `<button class="btn btn-sm btn-outline-danger" onclick="CD_cancelReservation(${r.id})">キャンセル</button>` : ''}</td>` : ''}
        </tr>`;
      }).join('') : `<tr><td colspan="${showCancel ? 6 : 5}" class="text-secondary">予約履歴はありません。</td></tr>`;
      p.innerHTML = `<div class="panel"><table class="data-table"><thead><tr><th>日付</th><th>時間</th><th>クラス</th><th>状態</th><th>店舗</th>${showCancel ? '<th></th>' : ''}</tr></thead><tbody>${body}</tbody></table></div>`;
    });
  }

  window.CD_cancelReservation = function (id) {
    if (!confirm('この予約をキャンセルしますか？')) return;
    fetch(`/reservation_system_study/api/customer_reservations.php?id=${id}`, { method: 'DELETE' })
      .then(async r => {
        const data = await r.json();
        if (!r.ok) { alert('キャンセルに失敗しました。'); return; }
        loadedTabs.delete('reservations'); loadedTabs.delete('dashboard');
        loadReservations();
      });
  };

  /* ---------- ホールド・延長・譲渡 ---------- */

  function loadEvents() {
    getJson(apiUrl('events')).then(rows => {
      const p = pane('events');
      const body = rows.length ? rows.map(ev => `
        <tr>
          <td>${fmtDateTime(ev.created_at)}</td>
          <td>${eventTypeLabel[ev.type] || ev.type}</td>
          <td>${escapeHtml(ev.product_name)}</td>
          <td>${escapeHtml(ev.detail || '-')}</td>
          <td>${escapeHtml(ev.staff_name || '-')}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-secondary">ホールド・延長・譲渡の履歴はありません。</td></tr>';

      p.innerHTML = `
        <div class="panel">
          <table class="data-table"><thead><tr><th>日時</th><th>種別</th><th>利用券</th><th>内容</th><th>担当</th></tr></thead><tbody>${body}</tbody></table>
        </div>
        ${isAdmin ? `
        <div class="panel mt-3">
          <h6 class="mb-2">操作</h6>
          <div class="mb-2">
            <select id="evMembership" class="form-select" style="max-width:320px;"></select>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <button class="btn btn-outline-light btn-sm" id="evHoldBtn">ホールド開始</button>
            <button class="btn btn-outline-light btn-sm" id="evResumeBtn">ホールド解除</button>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center mb-2">
            <input type="number" id="evExtendDays" class="form-control" placeholder="延長日数" style="max-width:140px;">
            <button class="btn btn-outline-light btn-sm" id="evExtendBtn">延長する</button>
          </div>
          <div class="d-flex gap-2 flex-wrap align-items-center">
            <input type="number" id="evTransferTo" class="form-control" placeholder="譲渡先の顧客ID" style="max-width:160px;">
            <button class="btn btn-outline-light btn-sm" id="evTransferBtn">譲渡する</button>
          </div>
        </div>` : ''}
      `;

      if (isAdmin) {
        function fillMembershipSelect() {
          const sel = document.getElementById('evMembership');
          sel.innerHTML = productListCache.map(m => `<option value="${m.id}">${escapeHtml(m.product_name)}（${m.status}）</option>`).join('') || '<option value="">利用券がありません</option>';
        }
        if (productListCache.length) fillMembershipSelect();
        else getJson(apiUrl('products')).then(rows2 => { productListCache = rows2; fillMembershipSelect(); });

        function doEvent(action, extra) {
          const membershipId = document.getElementById('evMembership').value;
          if (!membershipId) { alert('対象の利用券を選択してください。'); return; }
          postJson('/reservation_system_study/api/membership_events.php', { customer_membership_id: membershipId, action, ...extra })
            .then(({ ok, data }) => {
              if (!ok) { alert(data.error || '処理に失敗しました。'); return; }
              loadedTabs.delete('events'); loadedTabs.delete('products'); loadedTabs.delete('dashboard');
              loadEvents();
            });
        }
        document.getElementById('evHoldBtn').addEventListener('click', () => doEvent('hold'));
        document.getElementById('evResumeBtn').addEventListener('click', () => doEvent('resume'));
        document.getElementById('evExtendBtn').addEventListener('click', () => {
          const days = Number(document.getElementById('evExtendDays').value);
          if (!days) { alert('延長日数を入力してください。'); return; }
          doEvent('extend', { days });
        });
        document.getElementById('evTransferBtn').addEventListener('click', () => {
          const toId = Number(document.getElementById('evTransferTo').value);
          if (!toId) { alert('譲渡先の顧客IDを入力してください。'); return; }
          if (!confirm('この利用券を指定の顧客に譲渡しますか？')) return;
          doEvent('transfer', { to_customer_id: toId });
        });
      }
    });
  }

  /* ---------- 契約書 ---------- */

  function loadContracts() {
    getJson(apiUrl('contracts')).then(rows => {
      const p = pane('contracts');
      const body = rows.length ? rows.map(c => `
        <tr>
          <td>${c.contract_date}</td>
          <td>${escapeHtml(c.title)}</td>
          <td>${escapeHtml(c.memo || '-')}</td>
          <td>${escapeHtml(c.staff_name || '-')}</td>
          ${isAdmin ? `<td><button class="btn btn-sm btn-outline-danger" onclick="CD_deleteContract(${c.id})">削除</button></td>` : ''}
        </tr>`).join('') : `<tr><td colspan="${isAdmin ? 5 : 4}" class="text-secondary">登録された契約書がありません。</td></tr>`;

      p.innerHTML = `
        <div class="panel">
          <table class="data-table"><thead><tr><th>契約日</th><th>タイトル</th><th>メモ</th><th>担当</th>${isAdmin ? '<th></th>' : ''}</tr></thead><tbody>${body}</tbody></table>
        </div>
        ${isAdmin ? `
        <div class="panel mt-3">
          <h6 class="mb-2">契約書を追加</h6>
          <div class="d-flex gap-2 flex-wrap">
            <input type="text" id="ctTitle" class="form-control" placeholder="タイトル" style="max-width:220px;">
            <input type="date" id="ctDate" class="form-control" style="max-width:160px;">
            <input type="text" id="ctMemo" class="form-control" placeholder="メモ（任意）" style="max-width:220px;">
            <button class="btn-accent" id="ctAddBtn">追加</button>
          </div>
        </div>` : ''}
      `;

      if (isAdmin) {
        document.getElementById('ctDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('ctAddBtn').addEventListener('click', () => {
          const title = document.getElementById('ctTitle').value.trim();
          if (!title) { alert('タイトルを入力してください。'); return; }
          postJson('/reservation_system_study/api/contracts.php', {
            customer_id: customerId,
            title,
            contract_date: document.getElementById('ctDate').value,
            memo: document.getElementById('ctMemo').value,
          }).then(({ ok }) => {
            if (!ok) { alert('追加に失敗しました。'); return; }
            loadedTabs.delete('contracts'); loadedTabs.delete('dashboard');
            loadContracts();
          });
        });
      }
    });
  }

  window.CD_deleteContract = function (id) {
    if (!confirm('この契約書を削除しますか？')) return;
    fetch(`/reservation_system_study/api/contracts.php?id=${id}`, { method: 'DELETE' })
      .then(() => { loadedTabs.delete('contracts'); loadContracts(); });
  };

  /* ---------- クーポン・マイレージ ---------- */

  function loadCoupons() {
    getJson(apiUrl('coupons')).then(d => {
      const p = pane('coupons');
      const couponRows = d.coupons.length ? d.coupons.map(c => `
        <tr>
          <td>${escapeHtml(c.name)}</td>
          <td>${fmtMoney(c.discount_amount)}</td>
          <td>${c.valid_until || '-'}</td>
          <td>${c.used_at ? '使用済み' : '<span class="badge-status badge-active">利用可能</span>'}</td>
        </tr>`).join('') : '<tr><td colspan="4" class="text-secondary">利用可能なクーポンはありません。</td></tr>';

      const mileageRows = d.mileage_logs.length ? d.mileage_logs.map(l => `
        <div class="mb-1"><span class="text-secondary" style="font-size:12px;">${fmtDateTime(l.created_at)}</span> ${l.points > 0 ? '+' : ''}${l.points}pt ${escapeHtml(l.reason || '')}</div>
      `).join('') : '<div class="text-secondary">マイレージ履歴はありません。</div>';

      p.innerHTML = `
        <div class="stat-grid" style="margin-bottom:16px;">
          <div class="stat-card"><div class="label">保有マイレージ</div><div class="value">${d.mileage_points}pt</div></div>
        </div>
        <div class="panel mb-3">
          <h6 class="mb-2">クーポン</h6>
          <table class="data-table"><thead><tr><th>名前</th><th>割引額</th><th>有効期限</th><th>状態</th></tr></thead><tbody>${couponRows}</tbody></table>
        </div>
        <div class="panel">
          <h6 class="mb-2">マイレージ履歴</h6>
          ${mileageRows}
        </div>
        ${isAdmin ? `
        <div class="panel mt-3">
          <h6 class="mb-2">クーポン発行</h6>
          <div class="d-flex gap-2 flex-wrap mb-3">
            <input type="text" id="cpName" class="form-control" placeholder="クーポン名" style="max-width:200px;">
            <input type="number" id="cpDiscount" class="form-control" placeholder="割引額" style="max-width:140px;">
            <input type="date" id="cpValidUntil" class="form-control" style="max-width:160px;">
            <button class="btn-accent" id="cpAddBtn">発行</button>
          </div>
          <h6 class="mb-2">マイレージ調整</h6>
          <div class="d-flex gap-2 flex-wrap">
            <input type="number" id="mlPoints" class="form-control" placeholder="pt（マイナス可）" style="max-width:160px;">
            <input type="text" id="mlReason" class="form-control" placeholder="理由（任意）" style="max-width:200px;">
            <button class="btn-accent" id="mlAddBtn">反映</button>
          </div>
        </div>` : ''}
      `;

      if (isAdmin) {
        document.getElementById('cpAddBtn').addEventListener('click', () => {
          const name = document.getElementById('cpName').value.trim();
          if (!name) { alert('クーポン名を入力してください。'); return; }
          postJson('/reservation_system_study/api/coupons.php', {
            customer_id: customerId,
            name,
            discount_amount: Number(document.getElementById('cpDiscount').value) || 0,
            valid_until: document.getElementById('cpValidUntil').value,
          }).then(({ ok }) => { if (!ok) { alert('発行に失敗しました。'); return; } loadedTabs.delete('coupons'); loadedTabs.delete('dashboard'); loadCoupons(); });
        });
        document.getElementById('mlAddBtn').addEventListener('click', () => {
          const points = Number(document.getElementById('mlPoints').value);
          if (!points) { alert('ポイント数を入力してください。'); return; }
          postJson('/reservation_system_study/api/coupons.php?action=mileage', {
            customer_id: customerId,
            points,
            reason: document.getElementById('mlReason').value,
          }).then(({ ok }) => {
            if (!ok) { alert('反映に失敗しました。'); return; }
            loadedTabs.delete('coupons'); loadedTabs.delete('dashboard');
            loadCoupons();
          });
        });
      }
    });
  }

  /* ---------- ログ記録（管理者専用） ---------- */

  function loadLogs() {
    if (!isAdmin) return;
    getJson(apiUrl('logs')).then(rows => {
      const p = pane('logs');
      const body = rows.length ? rows.map(l => `
        <tr>
          <td>${fmtDateTime(l.created_at)}</td>
          <td>${escapeHtml(l.service_type)}</td>
          <td>${escapeHtml(l.action_type)}</td>
          <td>${escapeHtml(l.description)}</td>
          <td>${escapeHtml(l.staff_name || '-')}</td>
        </tr>`).join('') : '<tr><td colspan="5" class="text-secondary">ログはありません。</td></tr>';
      p.innerHTML = `<div class="panel"><table class="data-table"><thead><tr><th>作業日時</th><th>サービス</th><th>種別</th><th>内容</th><th>担当</th></tr></thead><tbody>${body}</tbody></table></div>`;
    });
  }

  /* ---------- 特記事項・メモ（管理者専用） ---------- */

  function loadNotes() {
    if (!isAdmin) return;
    getJson(apiUrl('notes')).then(rows => {
      const p = pane('notes');
      const list = rows.length ? rows.map(n => `
        <div class="panel mb-2 p-2">
          <div class="d-flex justify-content-between">
            <span class="text-secondary" style="font-size:12px;">${fmtDateTime(n.created_at)} ・ ${escapeHtml(n.staff_name || '-')}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="CD_deleteNote(${n.id})">削除</button>
          </div>
          <div>${escapeHtml(n.body)}</div>
        </div>`).join('') : '<div class="text-secondary">メモはありません。</div>';

      p.innerHTML = `
        <div class="panel mb-3">
          <h6 class="mb-2">メモ追加</h6>
          <div class="d-flex gap-2">
            <textarea id="noteBody" class="form-control" rows="2" placeholder="メモを入力してください。"></textarea>
            <button class="btn-accent" id="noteAddBtn" style="align-self:flex-start;">追加</button>
          </div>
        </div>
        ${list}
      `;
      document.getElementById('noteAddBtn').addEventListener('click', () => {
        const body = document.getElementById('noteBody').value.trim();
        if (!body) return;
        postJson('/reservation_system_study/api/customer_notes.php', { customer_id: customerId, body })
          .then(({ ok }) => { if (!ok) { alert('追加に失敗しました。'); return; } loadedTabs.delete('notes'); loadNotes(); });
      });
    });
  }

  window.CD_deleteNote = function (id) {
    if (!confirm('このメモを削除しますか？')) return;
    fetch(`/reservation_system_study/api/customer_notes.php?id=${id}`, { method: 'DELETE' })
      .then(() => { loadedTabs.delete('notes'); loadNotes(); });
  };

  /* ---------- 初期表示 ---------- */

  loadDashboard();
  loadedTabs.add('dashboard');
})();
