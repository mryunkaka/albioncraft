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
  const saveBtn = document.getElementById("price-save-btn");

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
      tableBody.innerHTML = "<tr><td colspan=\"9\">Tidak ada data.</td></tr>";
      return;
    }

    tableBody.innerHTML = rows
      .map((row) => `
        <tr>
          <td>${escapeHtml(row.id)}</td>
          <td>${escapeHtml(row.item_name)}</td>
          <td>${escapeHtml(row.item_code)}</td>
          <td>${escapeHtml(row.city_name || "-")}</td>
          <td>${escapeHtml(row.price_type)}</td>
          <td class="right">${escapeHtml(fmtMoney(row.price_value))}</td>
          <td>${escapeHtml(row.observed_at || "-")}</td>
          <td>${escapeHtml(row.updated_at || "-")}</td>
          <td>${escapeHtml(row.notes || "-")}</td>
        </tr>
      `)
      .join("");
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
        loadRows();
      }

      saveBtn.disabled = false;
      saveBtn.textContent = oldText;
    });
  }

  loadRows();
})();

