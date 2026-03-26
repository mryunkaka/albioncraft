(function () {
  const tableBody = document.getElementById("price-table-body");
  const qInput = document.getElementById("filter-q");
  const priceTypeSelect = document.getElementById("filter-price-type");
  const citySelect = document.getElementById("filter-city-id");
  const perPageSelect = document.getElementById("filter-per-page");
  const prevBtn = document.getElementById("page-prev");
  const nextBtn = document.getElementById("page-next");
  const pageInfo = document.getElementById("page-info");
  const form = document.getElementById("price-form");
  const bulkForm = document.getElementById("bulk-price-form");
  const bulkSaveBtn = document.getElementById("bulk-save-btn");
  const bulkResult = document.getElementById("bulk-result");
  const saveBtn = document.getElementById("price-save-btn");
  const cancelEditBtn = document.getElementById("price-cancel-edit");
  const idInput = document.getElementById("price-id");
  const itemSelect = document.getElementById("price-item-id");

  let page = 1;
  let lastPage = 1;
  let debounceTimer = null;

  function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = String(text ?? "");
    return div.innerHTML;
  }

  function fmtMoney(n) {
    const value = Number(n);
    if (!Number.isFinite(value)) return "-";
    return new Intl.NumberFormat("id-ID", {
      style: "currency",
      currency: "IDR",
      maximumFractionDigits: 0,
    }).format(Math.round(value));
  }

  function buildQuery() {
    const params = new URLSearchParams();
    const q = qInput ? qInput.value.trim() : "";
    const pt = priceTypeSelect ? priceTypeSelect.value : "";
    const cityId = citySelect ? citySelect.value : "";
    const perPage = perPageSelect ? perPageSelect.value : "20";

    if (q !== "") params.set("q", q);
    if (pt !== "") params.set("price_type", pt);
    if (cityId !== "") params.set("city_id", cityId);
    params.set("per_page", perPage);
    params.set("page", String(page));
    return params;
  }

  function renderRows(rows) {
    if (!tableBody) return;
    if (!Array.isArray(rows) || rows.length === 0) {
      tableBody.innerHTML = "<tr><td colspan=\"10\">Tidak ada data.</td></tr>";
      return;
    }

    tableBody.innerHTML = rows
      .map((row) => `
        <tr data-row='${escapeHtml(JSON.stringify(row))}'>
          <td>${escapeHtml(row.id)}</td>
          <td>${escapeHtml(row.item_name)}</td>
          <td>${escapeHtml(row.item_code)}</td>
          <td>${escapeHtml(row.city_name || "-")}</td>
          <td>${escapeHtml(row.price_type)}</td>
          <td class="right">${escapeHtml(fmtMoney(row.price_value))}</td>
          <td>${escapeHtml(row.observed_at || "-")}</td>
          <td>${escapeHtml(row.updated_at || "-")}</td>
          <td>${escapeHtml(row.notes || "-")}</td>
          <td>
            <div class="flex gap-2">
              <button class="button button-ghost js-edit" type="button">Edit</button>
              <button class="button button-danger js-delete" type="button">Delete</button>
            </div>
          </td>
        </tr>
      `)
      .join("");

    tableBody.querySelectorAll(".js-edit").forEach((btn) => {
      btn.addEventListener("click", () => {
        const tr = btn.closest("tr");
        if (!tr) return;
        const raw = tr.getAttribute("data-row");
        if (!raw) return;
        let row;
        try {
          row = JSON.parse(raw);
        } catch (_) {
          return;
        }
        fillFormForEdit(row);
      });
    });

    tableBody.querySelectorAll(".js-delete").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const tr = btn.closest("tr");
        if (!tr) return;
        const raw = tr.getAttribute("data-row");
        if (!raw) return;
        let row;
        try {
          row = JSON.parse(raw);
        } catch (_) {
          return;
        }
        await deleteRow(row);
      });
    });
  }

  function toDatetimeLocal(dbValue) {
    if (!dbValue) return "";
    const s = String(dbValue).trim();
    if (s.length < 16) return "";
    return s.slice(0, 16).replace(" ", "T");
  }

  function fillFormForEdit(row) {
    if (!form || !row) return;
    if (idInput) idInput.value = String(row.id || "");
    if (itemSelect) itemSelect.value = String(row.item_id || "");

    const cityInput = form.querySelector('[name="city_id"]');
    if (cityInput) cityInput.value = row.city_id == null ? "" : String(row.city_id);

    const typeInput = form.querySelector('[name="price_type"]');
    if (typeInput) typeInput.value = String(row.price_type || "BUY");

    const valueInput = form.querySelector('[name="price_value"]');
    if (valueInput) valueInput.value = String(row.price_value || 0);

    const observedInput = form.querySelector('[name="observed_at"]');
    if (observedInput) observedInput.value = toDatetimeLocal(row.observed_at);

    const notesInput = form.querySelector('[name="notes"]');
    if (notesInput) notesInput.value = String(row.notes || "");

    if (saveBtn) saveBtn.textContent = "Update Harga";
    if (cancelEditBtn) cancelEditBtn.hidden = false;
    form.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function resetEditState() {
    if (idInput) idInput.value = "";
    if (saveBtn) saveBtn.textContent = "Simpan Harga";
    if (cancelEditBtn) cancelEditBtn.hidden = true;
  }

  async function deleteRow(row) {
    if (!row || !row.id) return;
    const ok = window.confirm(`Hapus data harga ID ${row.id}?`);
    if (!ok) return;

    const fd = new FormData();
    fd.append("_token", (window.__PRICE_DATA__ && window.__PRICE_DATA__.csrfToken) || "");
    fd.append("id", String(row.id));

    let res;
    try {
      res = await fetch("/price-data/delete", {
        method: "POST",
        headers: { "X-Requested-With": "XMLHttpRequest" },
        body: fd,
      });
    } catch (_) {
      alert("Gagal request delete.");
      return;
    }

    const json = await res.json().catch(() => null);
    if (!json || json.success !== true) {
      alert((json && json.message) || "Gagal hapus data.");
      return;
    }

    alert(json.message || "Berhasil dihapus.");
    loadRows();
  }

  async function loadRows() {
    if (!tableBody) return;
    tableBody.innerHTML = "<tr><td colspan=\"9\">Loading...</td></tr>";
    const params = buildQuery();

    let res;
    try {
      res = await fetch("/api/price-data/list?" + params.toString(), {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      });
    } catch (_) {
      tableBody.innerHTML = "<tr><td colspan=\"9\">Gagal request ke server.</td></tr>";
      return;
    }

    const json = await res.json().catch(() => null);
    if (!json || json.success !== true || !json.data) {
      tableBody.innerHTML = "<tr><td colspan=\"9\">Gagal load data.</td></tr>";
      return;
    }

    const data = json.data;
    lastPage = Number(data.last_page || 1);
    page = Number(data.page || 1);
    renderRows(data.rows || []);
    if (pageInfo) pageInfo.textContent = `Page ${page} / ${lastPage} | Total ${Number(data.total || 0)}`;

    if (prevBtn) prevBtn.disabled = page <= 1;
    if (nextBtn) nextBtn.disabled = page >= lastPage;
  }

  function debounceLoad() {
    if (debounceTimer) window.clearTimeout(debounceTimer);
    debounceTimer = window.setTimeout(() => {
      page = 1;
      loadRows();
    }, 350);
  }

  if (qInput) qInput.addEventListener("input", debounceLoad);
  if (priceTypeSelect) priceTypeSelect.addEventListener("change", debounceLoad);
  if (citySelect) citySelect.addEventListener("change", debounceLoad);
  if (perPageSelect) perPageSelect.addEventListener("change", debounceLoad);

  if (prevBtn) {
    prevBtn.addEventListener("click", () => {
      if (page > 1) {
        page -= 1;
        loadRows();
      }
    });
  }
  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      if (page < lastPage) {
        page += 1;
        loadRows();
      }
    });
  }

  if (form) {
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!saveBtn) return;

      saveBtn.disabled = true;
      const oldText = saveBtn.textContent;
      saveBtn.textContent = "Menyimpan...";

      const fd = new FormData(form);
      let res;
      try {
        res = await fetch("/price-data/save", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd,
        });
      } catch (_) {
        alert("Gagal request ke server.");
        saveBtn.disabled = false;
        saveBtn.textContent = oldText;
        return;
      }

      const json = await res.json().catch(() => null);
      if (!json || json.success !== true) {
        alert((json && json.message) || "Gagal menyimpan data.");
      } else {
        alert(json.message || "Berhasil disimpan.");
        form.reset();
        resetEditState();
        loadRows();
      }

      saveBtn.disabled = false;
      saveBtn.textContent = oldText;
    });
  }

  if (cancelEditBtn) {
    cancelEditBtn.addEventListener("click", () => {
      if (form) form.reset();
      resetEditState();
    });
  }

  if (bulkForm) {
    bulkForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!bulkSaveBtn) return;

      bulkSaveBtn.disabled = true;
      const oldText = bulkSaveBtn.textContent;
      bulkSaveBtn.textContent = "Memproses...";
      if (bulkResult) bulkResult.textContent = "";

      const fd = new FormData(bulkForm);
      let res;
      try {
        res = await fetch("/price-data/bulk-save", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd,
        });
      } catch (_) {
        if (bulkResult) bulkResult.textContent = "Gagal request bulk ke server.";
        bulkSaveBtn.disabled = false;
        bulkSaveBtn.textContent = oldText;
        return;
      }

      const json = await res.json().catch(() => null);
      if (!json || json.success !== true) {
        const errors = json && json.data && Array.isArray(json.data.errors) ? json.data.errors : [];
        if (bulkResult) {
          bulkResult.textContent = ((json && json.message) || "Bulk gagal.") + (errors.length ? " " + errors.join(" | ") : "");
        }
      } else {
        const data = json.data || {};
        if (bulkResult) {
          bulkResult.textContent = `${json.message || "Bulk selesai."} Errors: ${(data.error_count || 0)}.`;
        }
        bulkForm.reset();
        loadRows();
      }

      bulkSaveBtn.disabled = false;
      bulkSaveBtn.textContent = oldText;
    });
  }

  loadRows();
})();
