<?php
require __DIR__ . '/../includes/auth.php';
require __DIR__ . '/../config/database.php';
require_customer_login();
$pageTitle = '予約する - 予約管理システム';
$activeMenu = 'booking';
require __DIR__ . '/../includes/customer_header.php';
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
    <h6 class="mb-2" id="sideDate">日付を選択してください</h6>
    <div id="sideScheduleList"><div class="text-secondary">-</div></div>
  </div>
</div>

<!-- 予約詳細モーダル -->
<div class="modal fade" id="bookingModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bmTitle">レッスン詳細</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="bmMeta" class="mb-3"></div>
        <div id="bmAction"></div>
      </div>
    </div>
  </div>
</div>

<script>
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));

const today = new Date();
let viewMode = 'month'; // 'year' | 'month' | 'week'
let viewYear = today.getFullYear();
let viewMonth = today.getMonth() + 1;
let weekAnchor = ymd(today);
let selectedDate = ymd(today);
let monthSchedules = [];
let weekSchedules = [];
let yearSummary = {};
let currentSchedule = null;

const WEEK_START_HOUR = 8;
const WEEK_END_HOUR = 20;
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
  fetch(`/reservation_system_study/api/customer_reservations.php?year=${viewYear}&month=${viewMonth}`)
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

    const items = daySchedules.slice(0, 3).map(s => {
      const label = s.my_reservation_id ? '予約済み' : (s.booked >= s.capacity ? '満席' : `${s.booked}/${s.capacity}`);
      return `<div class="sched-item">${s.start_time.slice(0,5)} ${escapeHtml(s.class_name)} ${label}</div>`;
    }).join('');
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
  fetch(`/reservation_system_study/api/customer_reservations.php?start=${startStr}&end=${endStr}`)
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
  const todayStr = ymd(today);

  const header = document.getElementById('weekHeader');
  header.innerHTML = '<div class="week-time-col"></div>' + days.map(d => {
    const dateStr = ymd(d);
    const isSelected = dateStr === selectedDate;
    return `<div class="week-day-head ${dateStr === todayStr ? 'today' : ''} ${isSelected ? 'selected' : ''}" data-date="${dateStr}">
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
    const isPast = dateStr < todayStr;
    const daySchedules = weekSchedules.filter(s => s.schedule_date === dateStr);
    const events = daySchedules.map(s => {
      const [sh, sm] = s.start_time.split(':').map(Number);
      const [eh, em] = s.end_time.split(':').map(Number);
      const top = ((sh - WEEK_START_HOUR) + sm / 60) * HOUR_HEIGHT;
      const height = Math.max((((eh - sh) * 60 + (em - sm)) / 60) * HOUR_HEIGHT, 20);
      const isMine = !!s.my_reservation_id;
      const isFull = s.booked >= s.capacity;
      let cls = 'week-event';
      if (isPast) cls += ' we-past';
      else if (isMine) cls += ' we-mine';
      else if (isFull) cls += ' we-full';
      const label = isMine ? '予約済み' : (isFull ? '満席' : `${s.category}`);
      return `<div class="${cls}" style="top:${top}px; height:${height}px;" onclick="openBookingById(${s.id})">
        <div class="we-time">${s.start_time.slice(0,5)}-${s.end_time.slice(0,5)}</div>
        <div class="we-title">${escapeHtml(s.class_name)}</div>
        <div class="we-count">${s.booked}/${s.capacity} ・ ${label}</div>
      </div>`;
    }).join('');
    dayColsHtml += `<div class="week-day-col" style="height:${totalHeight}px;">${events}</div>`;
  });

  body.innerHTML = `<div class="week-time-col" style="height:${totalHeight}px;">${hourLabels.join('')}</div>${dayColsHtml}`;
}

/* ---------- 年ビュー ---------- */

function loadYear() {
  fetch(`/reservation_system_study/api/customer_reservations.php?year=${viewYear}&summary=1`)
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
    list.innerHTML = '<div class="text-secondary">登録されているレッスンはありません。</div>';
    return;
  }
  list.innerHTML = daySchedules.map(s => {
    const isMine = !!s.my_reservation_id;
    const isFull = s.booked >= s.capacity;
    const badge = isMine
      ? '<span class="badge-status badge-active">予約済み</span>'
      : isFull
        ? '<span class="badge-status badge-expired">満席</span>'
        : '<span class="badge-status badge-pending">' + s.booked + '/' + s.capacity + '</span>';
    return `
    <div class="panel mb-2 p-2" style="cursor:pointer;" onclick="openBookingById(${s.id})">
      <div style="font-weight:700;">${s.start_time.slice(0,5)} - ${s.end_time.slice(0,5)}</div>
      <div>${escapeHtml(s.class_name)}</div>
      <div class="mt-1">${badge}</div>
    </div>`;
  }).join('');
}

/* ---------- 予約モーダル ---------- */

function openBookingById(id) {
  const source = viewMode === 'week' ? weekSchedules : monthSchedules;
  currentSchedule = source.find(s => s.id === id);
  if (!currentSchedule) return;
  const s = currentSchedule;
  const todayStr = ymd(today);
  const isPast = s.schedule_date < todayStr;
  const isMine = !!s.my_reservation_id;
  const isFull = s.booked >= s.capacity;

  document.getElementById('bmTitle').textContent = `${escapeHtml(s.class_name)} (${s.schedule_date})`;
  document.getElementById('bmMeta').innerHTML =
    `${s.start_time.slice(0,5)} - ${s.end_time.slice(0,5)} ・ 定員: ${s.booked}/${s.capacity}名`;

  const actionEl = document.getElementById('bmAction');
  if (isPast) {
    actionEl.innerHTML = '<div class="text-secondary">過去のレッスンです。</div>';
  } else if (isMine) {
    actionEl.innerHTML = `<button class="btn btn-outline-danger w-100" onclick="cancelBooking(${s.my_reservation_id})">キャンセルする</button>`;
  } else if (isFull) {
    actionEl.innerHTML = '<button class="btn-accent w-100" disabled style="opacity:.5;">満席です</button>';
  } else {
    actionEl.innerHTML = `<button class="btn-accent w-100" onclick="bookSchedule(${s.id})">予約する</button>`;
  }
  bookingModal.show();
}

const bookErrorMessages = {
  capacity_full: '定員に達しています。',
  already_reserved: '既に予約済みです。',
  past_schedule: '過去のレッスンは予約できません。',
  daily_limit_reached: '1日に1回まで予約できます。',
};

function bookSchedule(scheduleId) {
  fetch('/reservation_system_study/api/customer_reservations.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ schedule_id: scheduleId }),
  })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) { alert(bookErrorMessages[data.error] || '予約に失敗しました。'); return; }
      bookingModal.hide();
      refresh();
    });
}

function cancelBooking(reservationId) {
  if (!confirm('この予約をキャンセルしますか？')) return;
  fetch(`/reservation_system_study/api/customer_reservations.php?id=${reservationId}`, { method: 'DELETE' })
    .then(async r => {
      const data = await r.json();
      if (!r.ok) { alert(bookErrorMessages[data.error] || 'キャンセルに失敗しました。'); return; }
      bookingModal.hide();
      refresh();
    });
}

refresh();
</script>

<?php require __DIR__ . '/../includes/customer_footer.php'; ?>
