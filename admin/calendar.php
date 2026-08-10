<?php
require __DIR__ . '/../includes/auth.php';
require_login();
$pageTitle = 'スケジュール管理';
$activeMenu = 'calendar';
require __DIR__ . '/../includes/header.php';
?>

<div class="cal-toolbar">
  <div class="d-flex align-items-center gap-2">
    <button class="btn btn-outline-light btn-sm" id="btnToday">今日</button>
    <button class="btn btn-outline-light btn-sm" id="btnPrev">&lt;</button>
    <button class="btn btn-outline-light btn-sm" id="btnNext">&gt;</button>
    <h2 id="calTitle" class="ms-2">-</h2>
  </div>
  <div class="view-switcher" id="viewSwitcher">
    <button data-view="year">年</button>
    <button data-view="month" class="active">月</button>
    <button data-view="week">週</button>
  </div>
</div>

<div class="d-flex gap-3 align-items-start">
  <div style="flex:1; min-width:0;">
    <!-- 年ビュー -->
    <div id="viewYear" class="cal-view" style="display:none;">
      <div class="year-grid" id="yearGrid"></div>
    </div>

    <!-- 月ビュー -->
    <div id="viewMonth" class="cal-view">
      <div class="cal-grid" id="calWeekdays">
        <div class="cal-weekday">日</div><div class="cal-weekday">月</div><div class="cal-weekday">火</div>
        <div class="cal-weekday">水</div><div class="cal-weekday">木</div><div class="cal-weekday">金</div><div class="cal-weekday">土</div>
      </div>
      <div class="cal-grid" id="calGrid" style="margin-top:1px;"></div>
    </div>

    <!-- 週ビュー -->
    <div id="viewWeek" class="cal-view" style="display:none;">
      <div class="week-wrap">
        <div class="week-header" id="weekHeader"></div>
        <div class="week-body" id="weekBody"></div>
      </div>
    </div>
  </div>

  <div class="side-panel panel">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h6 class="mb-0" id="sideDate">日付を選択してください</h6>
      <button class="btn-accent btn-sm" id="btnAddSchedule">+ スケジュール追加</button>
    </div>
    <div id="sideScheduleList"><div class="text-secondary">-</div></div>
  </div>
</div>

<!-- スケジュール追加モーダル -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">スケジュール追加</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-2">
          <label class="form-label">クラス</label>
          <select id="sClassId" class="form-select"></select>
        </div>
        <div class="mb-2">
          <label class="form-label">インストラクター名</label>
          <input type="text" id="sInstructor" class="form-control">
        </div>
        <div class="row">
          <div class="col-6 mb-2">
            <label class="form-label">開始時間</label>
            <input type="time" id="sStart" class="form-control" value="10:00">
          </div>
          <div class="col-6 mb-2">
            <label class="form-label">終了時間</label>
            <input type="time" id="sEnd" class="form-control" value="10:50">
          </div>
        </div>
        <div class="mb-2">
          <label class="form-label">定員</label>
          <input type="number" id="sCapacity" class="form-control" value="10" min="1">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
        <button type="button" class="btn-accent" id="btnSaveSchedule">保存</button>
      </div>
    </div>
  </div>
</div>

<!-- スケジュール詳細（予約）モーダル -->
<div class="modal fade" id="scheduleDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="sdTitle">スケジュール詳細</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3" id="sdMeta"></div>
        <h6>予約状況</h6>
        <table class="data-table mb-3">
          <thead><tr><th>顧客名</th><th>状態</th><th>予約日時</th><th>操作</th></tr></thead>
          <tbody id="sdReservations"></tbody>
        </table>
        <h6>予約を追加</h6>
        <div class="d-flex gap-2 position-relative">
          <input type="text" id="sdCustomerSearch" class="form-control" placeholder="顧客名で検索">
          <button class="btn-accent" id="btnAddReservation" disabled>追加</button>
        </div>
        <div id="sdCustomerResults" class="list-group position-absolute" style="z-index:10; max-width:400px;"></div>
        <input type="hidden" id="sdSelectedCustomerId">
        <div class="mt-3 text-end">
          <button class="btn btn-outline-danger btn-sm" id="btnDeleteSchedule">このスケジュールを削除</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleModal'));
const scheduleDetailModal = new bootstrap.Modal(document.getElementById('scheduleDetailModal'));

const today = new Date();
let viewMode = 'month'; // 'year' | 'month' | 'week'
let viewYear = today.getFullYear();
let viewMonth = today.getMonth() + 1; // 1-12
let weekAnchor = ymd(today); // any date within the displayed week
let selectedDate = ymd(today);
let monthSchedules = [];
let weekSchedules = [];
let yearSummary = {}; // date -> count
let currentScheduleId = null;

const WEEK_START_HOUR = 8;
const WEEK_END_HOUR = 22;
const HOUR_HEIGHT = 48;

function ymd(d) {
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}
function parseYmd(s) {
  const [y, m, d] = s.split('-').map(Number);
  return new Date(y, m - 1, d);
}
function addDays(dateStr, n) {
  const d = parseYmd(dateStr);
  d.setDate(d.getDate() + n);
  return ymd(d);
}
function escapeHtml(s) {
  return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

const statusLabel = { reserved: '予約', show: '出席', noshow: 'ノーショー', cancelled: 'キャンセル' };

/* ---------- 表示切り替え ---------- */

document.getElementById('viewSwitcher').addEventListener('click', e => {
  const btn = e.target.closest('button[data-view]');
  if (!btn) return;
  viewMode = btn.dataset.view;
  document.querySelectorAll('#viewSwitcher button').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.getElementById('viewYear').style.display = viewMode === 'year' ? '' : 'none';
  document.getElementById('viewMonth').style.display = viewMode === 'month' ? '' : 'none';
  document.getElementById('viewWeek').style.display = viewMode === 'week' ? '' : 'none';
  if (viewMode === 'week') weekAnchor = selectedDate;
  if (viewMode === 'month') { const d = parseYmd(selectedDate); viewYear = d.getFullYear(); viewMonth = d.getMonth() + 1; }
  if (viewMode === 'year') { viewYear = parseYmd(selectedDate).getFullYear(); }
  refresh();
});

document.getElementById('btnPrev').addEventListener('click', () => {
  if (viewMode === 'year') viewYear--;
  else if (viewMode === 'month') { viewMonth--; if (viewMonth < 1) { viewMonth = 12; viewYear--; } }
  else weekAnchor = addDays(weekAnchor, -7);
  refresh();
});
document.getElementById('btnNext').addEventListener('click', () => {
  if (viewMode === 'year') viewYear++;
  else if (viewMode === 'month') { viewMonth++; if (viewMonth > 12) { viewMonth = 1; viewYear++; } }
  else weekAnchor = addDays(weekAnchor, 7);
  refresh();
});
document.getElementById('btnToday').addEventListener('click', () => {
  viewYear = today.getFullYear(); viewMonth = today.getMonth() + 1;
  weekAnchor = ymd(today); selectedDate = ymd(today);
  refresh();
});

function refresh() {
  updateTitle();
  if (viewMode === 'year') loadYear();
  else if (viewMode === 'month') loadMonth();
  else loadWeek();
}

function updateTitle() {
  const title = document.getElementById('calTitle');
  if (viewMode === 'year') {
    title.textContent = `${viewYear}年`;
  } else if (viewMode === 'month') {
    title.textContent = `${viewYear}年${viewMonth}月`;
  } else {
    const start = getWeekStart(weekAnchor);
    const end = addDays(ymd(start), 6);
    const s = parseYmd(ymd(start)), e = parseYmd(end);
    title.textContent = `${s.getFullYear()}年${s.getMonth()+1}月${s.getDate()}日 〜 ${e.getMonth()+1}月${e.getDate()}日`;
  }
}

/* ---------- 月ビュー ---------- */

function loadMonth() {
  fetch(`/reservation_system_study/api/schedules.php?year=${viewYear}&month=${viewMonth}`)
    .then(r => r.json())
    .then(rows => {
      monthSchedules = rows;
      renderMonthGrid();
      renderSidePanel();
    });
}

function renderMonthGrid() {
  const grid = document.getElementById('calGrid');
  const firstDay = new Date(viewYear, viewMonth - 1, 1);
  const startOffset = firstDay.getDay();
  const daysInMonth = new Date(viewYear, viewMonth, 0).getDate();
  const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

  let html = '';
  for (let i = 0; i < totalCells; i++) {
    const dayNum = i - startOffset + 1;
    let cellDate, otherMonth = false;
    if (dayNum < 1) {
      cellDate = new Date(viewYear, viewMonth - 2, new Date(viewYear, viewMonth - 1, 0).getDate() + dayNum);
      otherMonth = true;
    } else if (dayNum > daysInMonth) {
      cellDate = new Date(viewYear, viewMonth, dayNum - daysInMonth);
      otherMonth = true;
    } else {
      cellDate = new Date(viewYear, viewMonth - 1, dayNum);
    }
    const dateStr = ymd(cellDate);
    const isToday = dateStr === ymd(today);
    const isSelected = dateStr === selectedDate;
    const daySchedules = monthSchedules.filter(s => s.schedule_date === dateStr);

    let classes = 'cal-cell';
    if (otherMonth) classes += ' other-month';
    if (isToday) classes += ' today';
    if (isSelected) classes += ' selected';

    const items = daySchedules.slice(0, 3).map(s =>
      `<div class="sched-item">${s.start_time.slice(0,5)} ${escapeHtml(s.class_name)} ${s.booked}/${s.capacity}</div>`
    ).join('');
    const more = daySchedules.length > 3 ? `<div class="sched-item text-secondary">他 ${daySchedules.length - 3} 件</div>` : '';

    html += `<div class="${classes}" data-date="${dateStr}">
      <div class="date-num">${cellDate.getDate()}</div>
      ${items}${more}
    </div>`;
  }
  grid.innerHTML = html;

  grid.querySelectorAll('.cal-cell').forEach(cell => {
    cell.addEventListener('click', () => {
      selectedDate = cell.dataset.date;
      renderMonthGrid();
      renderSidePanel();
    });
  });
}

/* ---------- 週ビュー ---------- */

function getWeekStart(dateStr) {
  const d = parseYmd(dateStr);
  d.setDate(d.getDate() - d.getDay());
  return d;
}

function loadWeek() {
  const start = getWeekStart(weekAnchor);
  const startStr = ymd(start);
  const endStr = addDays(startStr, 7);
  fetch(`/reservation_system_study/api/schedules.php?start=${startStr}&end=${endStr}`)
    .then(r => r.json())
    .then(rows => {
      weekSchedules = rows;
      renderWeekGrid(start);
      renderSidePanel();
    });
}

function renderWeekGrid(weekStart) {
  const days = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(weekStart);
    d.setDate(d.getDate() + i);
    days.push(d);
  }
  const weekdayNames = ['日','月','火','水','木','金','土'];

  const header = document.getElementById('weekHeader');
  header.innerHTML = '<div class="week-time-col"></div>' + days.map(d => {
    const dateStr = ymd(d);
    const isToday = dateStr === ymd(today);
    const isSelected = dateStr === selectedDate;
    return `<div class="week-day-head ${isToday ? 'today' : ''} ${isSelected ? 'selected' : ''}" data-date="${dateStr}">
      <div class="wd-name">${weekdayNames[d.getDay()]}</div>
      <div class="wd-num">${d.getDate()}</div>
    </div>`;
  }).join('');

  header.querySelectorAll('.week-day-head').forEach(el => {
    el.addEventListener('click', () => {
      selectedDate = el.dataset.date;
      renderWeekGrid(weekStart);
      renderSidePanel();
    });
  });

  const totalHeight = (WEEK_END_HOUR - WEEK_START_HOUR) * HOUR_HEIGHT;
  const hourLabels = [];
  for (let h = WEEK_START_HOUR; h < WEEK_END_HOUR; h++) {
    hourLabels.push(`<div class="week-hour-label" style="top:${(h - WEEK_START_HOUR) * HOUR_HEIGHT}px;">${h}:00</div>`);
  }

  const body = document.getElementById('weekBody');
  let dayColsHtml = '';
  days.forEach(d => {
    const dateStr = ymd(d);
    const daySchedules = weekSchedules.filter(s => s.schedule_date === dateStr);
    const events = daySchedules.map(s => {
      const [sh, sm] = s.start_time.split(':').map(Number);
      const [eh, em] = s.end_time.split(':').map(Number);
      const top = ((sh - WEEK_START_HOUR) + sm / 60) * HOUR_HEIGHT;
      const height = Math.max((((eh - sh) * 60 + (em - sm)) / 60) * HOUR_HEIGHT, 20);
      return `<div class="week-event" style="top:${top}px; height:${height}px;" onclick="openScheduleDetail(${s.id})">
        <div class="we-time">${s.start_time.slice(0,5)}-${s.end_time.slice(0,5)}</div>
        <div class="we-title">${escapeHtml(s.class_name)}</div>
        <div class="we-count">${s.booked}/${s.capacity}</div>
      </div>`;
    }).join('');
    dayColsHtml += `<div class="week-day-col" style="height:${totalHeight}px;">${events}</div>`;
  });

  body.innerHTML = `<div class="week-time-col" style="height:${totalHeight}px;">${hourLabels.join('')}</div>${dayColsHtml}`;
}

/* ---------- 年ビュー ---------- */

function loadYear() {
  fetch(`/reservation_system_study/api/schedules.php?year=${viewYear}&summary=1`)
    .then(r => r.json())
    .then(rows => {
      yearSummary = {};
      rows.forEach(r => { yearSummary[r.schedule_date] = r.count; });
      renderYearGrid();
    });
}

function renderYearGrid() {
  const weekdayNames = ['日','月','火','水','木','金','土'];
  let html = '';
  for (let m = 1; m <= 12; m++) {
    const firstDay = new Date(viewYear, m - 1, 1);
    const startOffset = firstDay.getDay();
    const daysInMonth = new Date(viewYear, m, 0).getDate();
    const totalCells = Math.ceil((startOffset + daysInMonth) / 7) * 7;

    let cells = '';
    for (let i = 0; i < totalCells; i++) {
      const dayNum = i - startOffset + 1;
      if (dayNum < 1 || dayNum > daysInMonth) {
        cells += '<div class="mini-day empty"></div>';
        continue;
      }
      const d = new Date(viewYear, m - 1, dayNum);
      const dateStr = ymd(d);
      const isToday = dateStr === ymd(today);
      const count = yearSummary[dateStr] || 0;
      cells += `<div class="mini-day ${isToday ? 'today' : ''}" data-date="${dateStr}">
        <span>${dayNum}</span>${count > 0 ? '<i class="mini-dot"></i>' : ''}
      </div>`;
    }

    html += `<div class="mini-month">
      <div class="mini-month-title">${m}月</div>
      <div class="mini-month-grid">
        ${weekdayNames.map(w => `<div class="mini-weekday">${w}</div>`).join('')}
        ${cells}
      </div>
    </div>`;
  }
  document.getElementById('yearGrid').innerHTML = html;

  document.querySelectorAll('.mini-day[data-date]').forEach(el => {
    el.addEventListener('click', () => {
      const dateStr = el.dataset.date;
      const d = parseYmd(dateStr);
      selectedDate = dateStr;
      viewYear = d.getFullYear();
      viewMonth = d.getMonth() + 1;
      viewMode = 'month';
      document.querySelectorAll('#viewSwitcher button').forEach(b => b.classList.remove('active'));
      document.querySelector('#viewSwitcher button[data-view="month"]').classList.add('active');
      document.getElementById('viewYear').style.display = 'none';
      document.getElementById('viewMonth').style.display = '';
      document.getElementById('viewWeek').style.display = 'none';
      refresh();
    });
  });
}

/* ---------- サイドパネル ---------- */

function renderSidePanel() {
  document.getElementById('sideDate').textContent = selectedDate;
  const source = viewMode === 'week' ? weekSchedules : monthSchedules;
  const daySchedules = source.filter(s => s.schedule_date === selectedDate);
  const list = document.getElementById('sideScheduleList');
  if (!daySchedules.length) {
    list.innerHTML = '<div class="text-secondary">登録されているスケジュールはありません。</div>';
    return;
  }
  list.innerHTML = daySchedules.map(s => `
    <div class="panel mb-2 p-2" style="cursor:pointer;" onclick="openScheduleDetail(${s.id})">
      <div style="font-weight:700;">${s.start_time.slice(0,5)} - ${s.end_time.slice(0,5)}</div>
      <div>${escapeHtml(s.class_name)} ${s.instructor_name ? '・' + escapeHtml(s.instructor_name) : ''}</div>
      <div class="text-secondary">${s.booked} / ${s.capacity}名</div>
    </div>
  `).join('');
}

/* ---------- スケジュール追加 ---------- */

let classList = [];
fetch('/reservation_system_study/api/schedules.php?classes=1')
  .then(r => r.json())
  .then(classes => {
    classList = classes;
    document.getElementById('sClassId').innerHTML = classes.map(c => `<option value="${c.id}">${escapeHtml(c.name)}</option>`).join('');
  });

document.getElementById('sClassId').addEventListener('change', e => {
  const cls = classList.find(c => String(c.id) === e.target.value);
  if (cls) document.getElementById('sCapacity').value = cls.capacity;
});

document.getElementById('btnAddSchedule').addEventListener('click', () => scheduleModal.show());

document.getElementById('btnSaveSchedule').addEventListener('click', () => {
  const payload = {
    class_id: document.getElementById('sClassId').value,
    instructor_name: document.getElementById('sInstructor').value.trim(),
    schedule_date: selectedDate,
    start_time: document.getElementById('sStart').value,
    end_time: document.getElementById('sEnd').value,
    capacity: document.getElementById('sCapacity').value,
  };
  fetch('/reservation_system_study/api/schedules.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  })
    .then(r => r.json())
    .then(() => { scheduleModal.hide(); refresh(); });
});

/* ---------- スケジュール詳細・予約 ---------- */

function openScheduleDetail(id) {
  currentScheduleId = id;
  document.getElementById('sdSelectedCustomerId').value = '';
  document.getElementById('sdCustomerSearch').value = '';
  document.getElementById('sdCustomerResults').innerHTML = '';
  document.getElementById('btnAddReservation').disabled = true;

  fetch(`/reservation_system_study/api/schedules.php?id=${id}`)
    .then(r => r.json())
    .then(s => {
      document.getElementById('sdTitle').textContent = `${s.class_name} (${s.schedule_date})`;
      document.getElementById('sdMeta').innerHTML =
        `${s.start_time.slice(0,5)} - ${s.end_time.slice(0,5)} ・ インストラクター: ${escapeHtml(s.instructor_name || '-')} ・ 定員: ${s.reservations.filter(r => ['reserved','show'].includes(r.status)).length}/${s.capacity}`;

      const tbody = document.getElementById('sdReservations');
      if (!s.reservations.length) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-secondary">予約はありません。</td></tr>';
      } else {
        tbody.innerHTML = s.reservations.map(r => `
          <tr>
            <td>${escapeHtml(r.customer_name)}</td>
            <td><span class="badge-status badge-${r.status === 'show' ? 'active' : r.status === 'noshow' ? 'expired' : 'pending'}">${statusLabel[r.status]}</span></td>
            <td class="text-secondary">${(r.reserved_at || '').slice(0, 16)}</td>
            <td>
              <button class="btn btn-sm btn-outline-light" onclick="updateReservation(${r.id}, 'show')">出席</button>
              <button class="btn btn-sm btn-outline-light" onclick="updateReservation(${r.id}, 'noshow')">ノーショー</button>
              <button class="btn btn-sm btn-outline-danger" onclick="deleteReservation(${r.id})">削除</button>
            </td>
          </tr>
        `).join('');
      }
      scheduleDetailModal.show();
    });
}

function updateReservation(id, status) {
  fetch(`/reservation_system_study/api/reservations.php?id=${id}`, {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status }),
  })
    .then(r => r.json())
    .then(() => { openScheduleDetail(currentScheduleId); refresh(); });
}

function deleteReservation(id) {
  if (!confirm('この予約を削除しますか？')) return;
  fetch(`/reservation_system_study/api/reservations.php?id=${id}`, { method: 'DELETE' })
    .then(r => r.json())
    .then(() => { openScheduleDetail(currentScheduleId); refresh(); });
}

let custSearchTimer;
document.getElementById('sdCustomerSearch').addEventListener('input', e => {
  clearTimeout(custSearchTimer);
  const q = e.target.value.trim();
  document.getElementById('btnAddReservation').disabled = true;
  document.getElementById('sdSelectedCustomerId').value = '';
  if (!q) { document.getElementById('sdCustomerResults').innerHTML = ''; return; }
  custSearchTimer = setTimeout(() => {
    fetch(`/reservation_system_study/api/reservations.php?customer_search=${encodeURIComponent(q)}`)
      .then(r => r.json())
      .then(rows => {
        const box = document.getElementById('sdCustomerResults');
        box.innerHTML = rows.map(c =>
          `<a href="#" class="list-group-item list-group-item-action" style="background:var(--panel-2); color:var(--text);" onclick="selectCustomer(${c.id}, '${escapeHtml(c.name)}'); return false;">${escapeHtml(c.name)} (${c.phone || '-'})</a>`
        ).join('') || '<div class="list-group-item text-secondary" style="background:var(--panel-2);">検索結果がありません</div>';
      });
  }, 250);
});

function selectCustomer(id, name) {
  document.getElementById('sdSelectedCustomerId').value = id;
  document.getElementById('sdCustomerSearch').value = name;
  document.getElementById('sdCustomerResults').innerHTML = '';
  document.getElementById('btnAddReservation').disabled = false;
}

document.getElementById('btnAddReservation').addEventListener('click', () => {
  const customerId = document.getElementById('sdSelectedCustomerId').value;
  if (!customerId || !currentScheduleId) return;
  fetch('/reservation_system_study/api/reservations.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ schedule_id: currentScheduleId, customer_id: customerId }),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) { alert(data.error === 'capacity_full' ? '定員に達しています。' : data.error === 'already_reserved' ? '既に予約済みの顧客です。' : '予約に失敗しました。'); return; }
      openScheduleDetail(currentScheduleId);
      refresh();
    });
});

document.getElementById('btnDeleteSchedule').addEventListener('click', () => {
  if (!currentScheduleId || !confirm('このスケジュールを削除しますか？関連する予約もすべて削除されます。')) return;
  fetch(`/reservation_system_study/api/schedules.php?id=${currentScheduleId}`, { method: 'DELETE' })
    .then(r => r.json())
    .then(() => { scheduleDetailModal.hide(); refresh(); });
});

refresh();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
