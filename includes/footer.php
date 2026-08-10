    </div>
  </div>
</div>
<script src="/reservation_system_study/assets/js/password-toggle.js"></script>
<script>
(function () {
  const switcher = document.getElementById('branchSwitcher');
  if (!switcher) return;

  const btn = document.getElementById('branchSwitcherBtn');
  const dropdown = document.getElementById('branchDropdown');
  const searchInput = document.getElementById('branchSearchInput');
  const listEl = document.getElementById('branchList');
  const addBtn = document.getElementById('branchAddBtn');
  let branches = [];
  let currentId = null;

  function escapeHtml(s) {
    return (s || '').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  }

  function renderList(filter) {
    const q = (filter || '').trim().toLowerCase();
    const rows = branches.filter(b => !q || b.name.toLowerCase().includes(q));
    if (!rows.length) {
      listEl.innerHTML = '<div class="branch-item text-secondary">検索結果がありません</div>';
      return;
    }
    listEl.innerHTML = rows.map(b => `
      <div class="branch-item ${b.id === currentId ? 'active' : ''}" data-id="${b.id}">
        ${b.id === currentId ? '✓ ' : ''}${escapeHtml(b.name)}
      </div>
    `).join('');
  }

  function loadBranches() {
    fetch('/reservation_system_study/api/branches.php')
      .then(r => r.json())
      .then(data => {
        branches = data.branches;
        currentId = data.current_id;
        renderList(searchInput.value);
      });
  }

  btn.addEventListener('click', () => {
    dropdown.classList.toggle('open');
    if (dropdown.classList.contains('open')) {
      searchInput.value = '';
      loadBranches();
      searchInput.focus();
    }
  });

  document.addEventListener('click', e => {
    if (!switcher.contains(e.target)) dropdown.classList.remove('open');
  });

  searchInput.addEventListener('input', () => renderList(searchInput.value));

  listEl.addEventListener('click', e => {
    const item = e.target.closest('.branch-item[data-id]');
    if (!item) return;
    const id = parseInt(item.dataset.id, 10);
    fetch('/reservation_system_study/api/branches.php?switch=1', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ branch_id: id }),
    })
      .then(r => r.json())
      .then(() => location.reload());
  });

  if (addBtn) {
    addBtn.addEventListener('click', () => {
      const name = prompt('追加する店舗名を入力してください（例: RIZZ PILATES 梅田）');
      if (!name || !name.trim()) return;
      let brand = (prompt('ブランドを入力してください（RIZZ または EN）', 'RIZZ') || '').trim().toUpperCase();
      if (brand !== 'EN') brand = 'RIZZ';
      fetch('/reservation_system_study/api/branches.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name.trim(), brand }),
      })
        .then(async r => {
          const data = await r.json();
          if (!r.ok) { alert(data.error === 'duplicate_name' ? '既に存在する店舗名です。' : '店舗の追加に失敗しました。'); return; }
          location.reload();
        });
    });
  }
})();
</script>
</body>
</html>
