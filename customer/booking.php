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
    <button class="btn btn-outline-light btn-sm" id="btnToday">今週</button>
    <button class="btn btn-outline-light btn-sm" id="btnPrev">&lt;</button>
    <button class="btn btn-outline-light btn-sm" id="btnNext">&gt;</button>
    <h2 id="calTitle" class="ms-2" style="font-size:16px; margin:0;">-</h2>
  </div>
</div>

<div class="week-wrap">
  <div class="week-header" id="weekHeader"></div>
  <div class="week-body" id="weekBody"></div>
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
let weekAnchor = ymd(today);
let weekSchedules = [];
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
function getWeekStart(dateStr) {
  const d = parseYmd(dateStr);
  d.setDate(d.getDate() - d.getDay());
  return d;
}

document.getElementById('btnPrev').addEventListener('click', () => { weekAnchor = addDays(weekAnchor, -7); refresh(); });
document.getElementById('btnNext').addEventListener('click', () => { weekAnchor = addDays(weekAnchor, 7); refresh(); });
document.getElementById('btnToday').addEventListener('click', () => { weekAnchor = ymd(today); refresh(); });

function refresh() {
  const start = getWeekStart(weekAnchor);
  const startStr = ymd(start);
  const endStr = addDays(startStr, 7);

  const s = parseYmd(startStr), e = parseYmd(addDays(startStr, 6));
  document.getElementById('calTitle').textContent = `${s.getFullYear()}年${s.getMonth()+1}月${s.getDate()}日 〜 ${e.getMonth()+1}月${e.getDate()}日`;

  fetch(`/reservation_system_study/api/customer_reservations.php?start=${startStr}&end=${endStr}`)
    .then(r => r.json())
    .then(rows => {
      weekSchedules = rows;
      renderWeekGrid(start);
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
    return `<div class="week-day-head ${dateStr === todayStr ? 'today' : ''}">
      <div class="wd-name">${weekdayNames[d.getDay()]}</div>
      <div class="wd-num">${d.getDate()}</div>
    </div>`;
  }).join('');

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
      return `<div class="${cls}" style="top:${top}px; height:${height}px;" onclick="openBooking(${s.id})">
        <div class="we-time">${s.start_time.slice(0,5)}-${s.end_time.slice(0,5)}</div>
        <div class="we-title">${escapeHtml(s.class_name)}</div>
        <div class="we-count">${s.booked}/${s.capacity} ・ ${label}</div>
      </div>`;
    }).join('');
    dayColsHtml += `<div class="week-day-col" style="height:${totalHeight}px;">${events}</div>`;
  });

  body.innerHTML = `<div class="week-time-col" style="height:${totalHeight}px;">${hourLabels.join('')}</div>${dayColsHtml}`;
}

function openBooking(id) {
  currentSchedule = weekSchedules.find(s => s.id === id);
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
