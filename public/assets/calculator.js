(function () {
  const STORAGE_KEY = "albion_calc_strict_v1";

  const form = document.getElementById("calc-form");
  const materialsRoot = document.getElementById("materials");
  const addMaterialBtn = document.getElementById("add-material");
  const clearBtn = document.getElementById("clear-local");
  const errorBox = document.getElementById("calc-error");
  const helpModal = document.getElementById("help-modal");
  const openHelpModalBtn = document.getElementById("open-help-modal");
  const closeHelpModalBtn = document.getElementById("close-help-modal");
  const closeHelpFooterBtn = document.getElementById("close-help-footer");
  const closeHelpBackdrop = document.getElementById("close-help-backdrop");
  const sidebar = document.querySelector(".app-sidebar");
  const sidebarBackdrop = document.getElementById("sidebar-backdrop");
  const toggleSidebarBtn = document.getElementById("toggle-sidebar");
  const closeSidebarBtn = document.getElementById("close-sidebar");
  const heroTotalProfit = document.getElementById("calc-hero-total-profit");
  const heroTotalProfitNote = document.getElementById("calc-hero-total-profit-note");
  const heroProfitItem = document.getElementById("calc-hero-profit-item");
  const heroProfitItemNote = document.getElementById("calc-hero-profit-item-note");
  const heroMargin = document.getElementById("calc-hero-margin");
  const heroMarginNote = document.getElementById("calc-hero-margin-note");
  const tooltipButtons = document.querySelectorAll(".field-help-trigger");
  const tooltipPopover = document.getElementById("calc-tooltip-popover");
  const tooltipTitle = document.getElementById("calc-tooltip-title");
  const tooltipBody = document.getElementById("calc-tooltip-body");
  const tooltipPreviewBtn = document.getElementById("calc-tooltip-preview");
  const tooltipImageModal = document.getElementById("tooltip-image-modal");
  const tooltipImageBackdrop = document.getElementById("tooltip-image-backdrop");
  const tooltipImageClose = document.getElementById("tooltip-image-close");
  const tooltipImageTitle = document.getElementById("tooltip-image-title");
  const tooltipImageStage = document.getElementById("tooltip-image-stage");
  const tooltipImageFigure = document.getElementById("tooltip-image-figure");
  const tooltipImagePreview = document.getElementById("tooltip-image-preview");
  const tooltipZoomInBtn = document.getElementById("tooltip-image-zoom-in");
  const tooltipZoomOutBtn = document.getElementById("tooltip-image-zoom-out");
  const tooltipResetBtn = document.getElementById("tooltip-image-reset");
  let activeTooltipButton = null;
  let activeTooltipImage = "";
  const imageState = {
    scale: 1,
    x: 0,
    y: 0,
    minScale: 1,
    maxScale: 5,
    dragging: false,
    pointerIds: new Map(),
    pinchStartDistance: 0,
    pinchStartScale: 1,
  };
  const recipeItemSelect = document.getElementById("recipe-item-select");
  const recipeCitySelect = document.getElementById("recipe-city-select");
  const recipeAutoFillBtn = document.getElementById("recipe-autofill-btn");
  const bonusLocalCitySelect = document.getElementById("bonus-local-city-select");
  const craftFeeCitySelect = document.getElementById("craft-fee-city-select");
  const saveCraftFeeBtn = document.getElementById("save-craft-fee-btn");
  const sellPriceCitySelect = document.getElementById("sell-price-city-select");
  const saveSellPriceBtn = document.getElementById("save-sell-price-btn");
  const saveMaterialPricesBtn = document.getElementById("save-material-prices-btn");
  const recipeRecommendations = document.getElementById("recipe-recommendations");
  const analysisRecommendation = document.getElementById("analysis-recommendation");
  const analysisRecommendationList = document.getElementById("analysis-recommendation-list");
  const calculatorCitiesData = document.getElementById("calculator-cities-data");
  const cityOptions = parseCityOptions();
  let lastRenderedAnalysisText = "";

  function upper(v) {
    return String(v || "").toUpperCase();
  }

  function parseCityOptions() {
    if (!calculatorCitiesData) return [];
    try {
      const parsed = JSON.parse(calculatorCitiesData.textContent || "[]");
      return Array.isArray(parsed) ? parsed : [];
    } catch (_) {
      return [];
    }
  }

  function el(tag, className, attrs) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (attrs) {
      for (const [k, v] of Object.entries(attrs)) node.setAttribute(k, v);
    }
    return node;
  }

  function addMaterialRow(initial) {
    const row = el("div", "calc-material-row");

    const itemId = el("input", "material-item-id", { type: "hidden" });
    itemId.value = (initial && initial.item_id != null) ? String(initial.item_id) : "";

    const name = el("input", "input material-name", { type: "text", placeholder: "Nama material", autocomplete: "off" });
    name.value = upper((initial && initial.name) || "");
    name.addEventListener("input", () => {
      name.value = upper(name.value);
    });

    const qty = el("input", "input material-qty", { type: "number", step: "0.0001", placeholder: "Qty/recipe", min: "0" });
    qty.value = (initial && initial.qty_per_recipe != null) ? String(initial.qty_per_recipe) : "0";

    const price = el("input", "input material-price", { type: "number", step: "0.01", placeholder: "Buy price", min: "0" });
    price.value = (initial && initial.buy_price != null) ? String(initial.buy_price) : "0";

    const rt = el("select", "select material-type");
    rt.appendChild(new Option("RETURN", "RETURN"));
    rt.appendChild(new Option("NON_RETURN", "NON_RETURN"));
    rt.value = (initial && initial.return_type) || "RETURN";

    const city = el("select", "select material-city");
    city.appendChild(new Option("Kota beli material", ""));
    for (const option of cityOptions) {
      city.appendChild(new Option(String(option.name || ""), String(option.id || "")));
    }
    city.value = (initial && initial.city_id != null) ? String(initial.city_id) : "";

    const action = el("div", "material-action");
    const remove = el("button", "button button-ghost material-remove", { type: "button" });
    remove.textContent = "Hapus";
    remove.addEventListener("click", () => {
      row.remove();
      scheduleSave();
      reindexMaterialNames();
    });

    row.appendChild(itemId);
    row.appendChild(name);
    row.appendChild(qty);
    row.appendChild(price);
    row.appendChild(rt);
    row.appendChild(city);
    action.appendChild(remove);
    row.appendChild(action);

    materialsRoot.appendChild(row);
    reindexMaterialNames();
  }

  function reindexMaterialNames() {
    const rows = materialsRoot.querySelectorAll(".calc-material-row");
    let i = 0;
    for (const row of rows) {
      const inputs = row.querySelectorAll("input, select");
      const itemId = inputs[0];
      const name = inputs[1];
      const qty = inputs[2];
      const price = inputs[3];
      const rt = inputs[4];
      const city = inputs[5];

      itemId.name = `materials[${i}][item_id]`;
      name.name = `materials[${i}][name]`;
      qty.name = `materials[${i}][qty_per_recipe]`;
      price.name = `materials[${i}][buy_price]`;
      rt.name = `materials[${i}][return_type]`;
      city.name = `materials[${i}][city_id]`;

      name.id = `mat-${i}-name`;
      qty.id = `mat-${i}-qty`;
      price.id = `mat-${i}-price`;
      rt.id = `mat-${i}-type`;
      city.id = `mat-${i}-city`;
      i++;
    }
  }

  function clearMaterials() {
    materialsRoot.innerHTML = "";
  }

  function defaultMaterials() {
    clearMaterials();
    addMaterialRow({ name: "Hide T3", qty_per_recipe: 2, buy_price: 100, return_type: "RETURN" });
    addMaterialRow({ name: "Leather T2", qty_per_recipe: 1, buy_price: 80, return_type: "RETURN" });
    reindexMaterialNames();
  }

  function readNumber(fd, key) {
    const v = fd.get(key);
    if (v == null || v === "") return 0;
    return Number(v);
  }

  function readInt(fd, key) {
    const n = readNumber(fd, key);
    return Number.isFinite(n) ? Math.trunc(n) : 0;
  }

  async function fetchJson(url) {
    const res = await fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    const json = await res.json().catch(() => null);
    if (!json || json.success !== true) {
      const msg = (json && json.message) ? json.message : "Request gagal.";
      throw new Error(msg);
    }
    return json;
  }

  function setFieldValue(name, value) {
    const field = form.querySelector(`[name="${CSS.escape(name)}"]`);
    if (!field) return;
    field.value = value == null ? "" : String(value);
  }

  function populateRecipeDetail(data) {
    if (!data || typeof data !== "object") return;

    const item = data.item || {};
    const cityBonus = data.city_bonus || {};
    const materials = Array.isArray(data.materials) ? data.materials : [];
    const recommendations = data.recommendations || {};
    const cheapestMaterialMap = new Map();
    const cheapestMaterials = Array.isArray(recommendations.cheapest_material_cities) ? recommendations.cheapest_material_cities : [];

    for (const row of cheapestMaterials) {
      cheapestMaterialMap.set(Number(row.item_id || 0), Number(row.city_id || 0));
    }

    setFieldValue("item_id", item.id || "");
    setFieldValue("item_name", upper(item.name || ""));
    setFieldValue("item_value", item.item_value != null ? item.item_value : 0);
    setFieldValue("output_qty", item.output_qty != null ? item.output_qty : 1);
    setFieldValue("sell_price", item.sell_price != null ? item.sell_price : "");
    if (sellPriceCitySelect) {
      const bestSellCityId = Number((((recommendations.best_sell_city || {}).city_id) || 0));
      sellPriceCitySelect.value = bestSellCityId > 0 ? String(bestSellCityId) : "";
    }
    if (item.craft_fee != null) {
      setFieldValue("usage_fee", item.craft_fee);
    }
    if (craftFeeCitySelect) {
      const bestCraftCityId = Number((((recommendations.best_craft_fee_city || {}).city_id) || 0));
      craftFeeCitySelect.value = bestCraftCityId > 0 ? String(bestCraftCityId) : "";
    }
    setFieldValue("bonus_local", cityBonus.bonus_percent != null ? cityBonus.bonus_percent : 0);
    if (bonusLocalCitySelect) {
      const bonusCityId = Number(cityBonus.city_id || 0);
      bonusLocalCitySelect.value = bonusCityId > 0 ? String(bonusCityId) : "";
    }

    clearMaterials();
    if (materials.length === 0) {
      addMaterialRow();
    } else {
      for (const material of materials) {
        addMaterialRow({
          item_id: material.item_id != null ? material.item_id : "",
          name: material.name || "",
          qty_per_recipe: material.qty_per_recipe != null ? material.qty_per_recipe : 0,
          buy_price: material.buy_price != null ? material.buy_price : 0,
          return_type: material.return_type || "RETURN",
          city_id: cheapestMaterialMap.get(Number(material.item_id || 0)) || "",
        });
      }
    }
    reindexMaterialNames();
    renderRecommendations(recommendations);
    scheduleSave();
  }

  function renderRecommendations(data) {
    if (!recipeRecommendations) return;
    if (!data || typeof data !== "object") {
      recipeRecommendations.hidden = true;
      recipeRecommendations.innerHTML = "";
      return;
    }

    const lines = [];

    const craft = data.best_craft_fee_city || null;
    if (craft && craft.price_value != null) {
      lines.push(`Craft paling murah: ${craft.city_name || "-"} @ ${fmtMoney(Number(craft.price_value))}`);
    }

    const recommendedCraft = data.recommended_craft_city || null;
    if (recommendedCraft && recommendedCraft.estimated_cost_per_item != null) {
      lines.push(`Rekomendasi kota craft: ${recommendedCraft.city_name || "-"} | estimasi cost/item ${fmtMoney(Number(recommendedCraft.estimated_cost_per_item))} | bonus ${Number(recommendedCraft.bonus_percent || 0).toFixed(0)}%`);
    }

    const sell = data.best_sell_city || null;
    if (sell && sell.price_value != null) {
      lines.push(`Jual paling tinggi: ${sell.city_name || "-"} @ ${fmtMoney(Number(sell.price_value))}`);
    }

    const localBonuses = Array.isArray(data.local_bonus_cities) ? data.local_bonus_cities : [];
    if (localBonuses.length > 0) {
      lines.push(`Bonus local tersedia: ${localBonuses.map((row) => `${row.city_name} (${Number(row.bonus_percent || 0).toFixed(0)}%)`).join(", ")}`);
    }

    const cheapestMaterials = Array.isArray(data.cheapest_material_cities) ? data.cheapest_material_cities : [];
    for (const row of cheapestMaterials) {
      lines.push(`Beli murah ${row.name || "-"}: ${(row.city_name || "Global")} @ ${fmtMoney(Number(row.price_value || 0))}`);
    }

    if (lines.length === 0) {
      recipeRecommendations.hidden = true;
      recipeRecommendations.innerHTML = "";
      return;
    }

    recipeRecommendations.hidden = false;
    recipeRecommendations.innerHTML = lines.map((line) => `<div>${line}</div>`).join("");
  }

  async function loadRecipeItems(keyword) {
    if (!recipeItemSelect) return;
    const q = keyword ? `?q=${encodeURIComponent(keyword)}` : "";
    const json = await fetchJson(`/api/calculator/recipes/items${q}`);
    const current = recipeItemSelect.value;

    recipeItemSelect.innerHTML = "";
    recipeItemSelect.appendChild(new Option("Pilih item recipe database", ""));

    const rows = Array.isArray(json.data) ? json.data : [];
    for (const row of rows) {
      const fallbackLabel = row.item_code
        ? `${row.name} (${row.item_code})`
        : String(row.name || "Untitled Recipe");
      const label = String(row.label || fallbackLabel);
      recipeItemSelect.appendChild(new Option(label, String(row.id)));
    }

    if (current) {
      recipeItemSelect.value = current;
    }
  }

  function setFeedback(msg, type) {
    if (!msg) {
      errorBox.hidden = true;
      errorBox.textContent = "";
      errorBox.classList.add("alert-error");
      return;
    }
    errorBox.hidden = false;
    errorBox.classList.toggle("alert-error", type !== "success");
    errorBox.textContent = msg;
  }

  const itemNameInput = form.querySelector('[name="item_name"]');
  if (itemNameInput) {
    itemNameInput.addEventListener("input", () => {
      itemNameInput.value = upper(itemNameInput.value);
    });
  }

  function renderMaterialSummary(materials) {
    const table = document.getElementById("material-summary");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!Array.isArray(materials) || materials.length === 0) {
      const tr = document.createElement("tr");
      const td = document.createElement("td");
      td.colSpan = 3;
      td.textContent = "-";
      tr.appendChild(td);
      tbody.appendChild(tr);
      return;
    }

    for (const m of materials) {
      const tr = document.createElement("tr");

      const tdName = document.createElement("td");
      tdName.textContent = m.name || "-";

      const tdBuy = document.createElement("td");
      tdBuy.className = "right";
      tdBuy.textContent = (m.material_to_buy == null) ? "-" : String(m.material_to_buy);

      const tdLeft = document.createElement("td");
      tdLeft.className = "right";
      tdLeft.textContent = (m.leftover_qty == null) ? "-" : String(m.leftover_qty);

      tr.appendChild(tdName);
      tr.appendChild(tdBuy);
      tr.appendChild(tdLeft);
      tbody.appendChild(tr);
    }
  }

  function renderFocusSummary(focus) {
    const table = document.getElementById("focus-summary");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    const rows = [
      ["Focus Points", focus && focus.focus_points],
      ["Focus per Craft", focus && focus.focus_per_craft],
      ["Sisa Focus Point", focus && focus.sisa_focus_point],
      ["Kamu Bisa Craft", focus && focus.kamu_bisa_craft],
      ["Total Crafted Item", focus && focus.total_crafted_item],
    ];

    for (const [label, value] of rows) {
      const tr = document.createElement("tr");
      const tdL = document.createElement("td");
      tdL.textContent = label;
      const tdV = document.createElement("td");
      tdV.className = "right";
      tdV.textContent = (value == null) ? "-" : String(value);
      tr.appendChild(tdL);
      tr.appendChild(tdV);
      tbody.appendChild(tr);
    }
  }

  function renderMaterialFields(fields) {
    const table = document.getElementById("material-fields");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!fields || typeof fields !== "object") return;

    const rowDefs = [
      ["Material Type", fields.types],
      ["Material To Buy", fields.to_buy],
      ["Material Needed", fields.needed],
      ["Material Price", fields.price],
      ["Effective Stock", fields.effective_stock],
      ["Craftable (Crafts)", fields.craftable_crafts],
      ["Return Material", fields.return_material],
    ];

    for (const [label, arr] of rowDefs) {
      const tr = document.createElement("tr");
      const td0 = document.createElement("td");
      td0.textContent = label;
      tr.appendChild(td0);

      const values = Array.isArray(arr) ? arr : [];
      for (let i = 0; i < 6; i++) {
        const td = document.createElement("td");
        td.className = "right";
        const v = values[i];
        td.textContent = (v == null) ? "0" : String(v);
        tr.appendChild(td);
      }

      tbody.appendChild(tr);
    }
  }

  function renderIterations(iterations) {
    const table = document.getElementById("iteration-table");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    if (!Array.isArray(iterations) || iterations.length === 0) return;

    for (const row of iterations) {
      const tr = document.createElement("tr");

      const tdIter = document.createElement("td");
      tdIter.textContent = String(row.iteration ?? "-");
      tr.appendChild(tdIter);

      const stocks = Array.isArray(row.stocks) ? row.stocks : [];
      for (let i = 0; i < 6; i++) {
        const td = document.createElement("td");
        td.className = "right";
        td.textContent = String(stocks[i] ?? 0);
        tr.appendChild(td);
      }

      const tdCraftable = document.createElement("td");
      tdCraftable.className = "right";
      tdCraftable.textContent = String(row.craftable_output ?? 0);
      tr.appendChild(tdCraftable);

      tbody.appendChild(tr);
    }
  }

  function fmtMoney(n) {
    if (typeof n !== "number" || !Number.isFinite(n)) return "-";
    return idr.format(Math.round(n));
  }

  function fmtRrr(n) {
    if (typeof n !== "number" || !Number.isFinite(n)) return "-";
    return n.toFixed(4);
  }

  const idr = new Intl.NumberFormat("id-ID", {
    style: "currency",
    currency: "IDR",
    maximumFractionDigits: 0,
  });

  function fmtPercent(n) {
    if (typeof n !== "number" || !Number.isFinite(n)) return "-";
    return n.toFixed(2) + "%";
  }

  function getCityNameById(cityId) {
    const targetId = Number(cityId || 0);
    if (!targetId) return "";
    const match = cityOptions.find((row) => Number(row.id || 0) === targetId);
    return match ? String(match.name || "") : "";
  }

  function collectMaterialAnalysisRows() {
    const rows = [];
    const materialRows = materialsRoot ? materialsRoot.querySelectorAll(".calc-material-row") : [];

    for (const row of materialRows) {
      const nameField = row.querySelector(".material-name");
      const cityField = row.querySelector(".material-city");
      const priceField = row.querySelector(".material-price");
      const qtyField = row.querySelector(".material-qty");
      const typeField = row.querySelector(".material-type");

      const name = String(nameField && "value" in nameField ? nameField.value : "").trim();
      const cityId = Number(cityField && "value" in cityField ? cityField.value : 0);
      const cityName = getCityNameById(cityId);
      const buyPrice = Number(priceField && "value" in priceField ? priceField.value : NaN);
      const qtyPerRecipe = Number(qtyField && "value" in qtyField ? qtyField.value : NaN);
      const returnType = String(typeField && "value" in typeField ? typeField.value : "").trim();

      rows.push({
        name,
        cityId,
        cityName,
        buyPrice,
        qtyPerRecipe,
        returnType,
        totalSpend: Number.isFinite(buyPrice) && Number.isFinite(qtyPerRecipe) ? (buyPrice * qtyPerRecipe) : NaN,
      });
    }

    return rows;
  }

  function buildAnalysisRecommendations() {
    const recommendations = [];
    const materialRows = collectMaterialAnalysisRows();
    const pricedMaterials = materialRows.filter((row) => row.name && row.cityId > 0 && Number.isFinite(row.buyPrice));

    if (pricedMaterials.length > 0) {
      const materialLines = pricedMaterials.map((row, index) => {
        const qtyText = Number.isFinite(row.qtyPerRecipe) ? `${Number(row.qtyPerRecipe)}` : "-";
        const reasonParts = [`${row.name} di ${row.cityName} @ ${fmtMoney(row.buyPrice)}`];
        if (qtyText !== "-") {
          reasonParts.push(`qty/recipe ${qtyText}`);
        }
        return `${index + 1}. ${reasonParts.join(" | ")}`;
      });

      recommendations.push({
        label: "Bahan yang dipakai",
        value: materialLines.join("\n"),
      });
    } else {
      recommendations.push({
        label: "Bahan yang dipakai",
        value: "Belum ada data kota dan harga bahan yang valid.",
      });
    }

    const totalMaterialCost = pricedMaterials
      .filter((row) => Number.isFinite(row.totalSpend))
      .reduce((sum, row) => sum + row.totalSpend, 0);

    if (pricedMaterials.length > 0) {
      const cheapestSource = pricedMaterials
        .slice()
        .sort((a, b) => {
          if (a.buyPrice !== b.buyPrice) return a.buyPrice - b.buyPrice;
          return a.name.localeCompare(b.name);
        })[0];

      recommendations.push({
        label: "Rekomendasi beli bahan",
        value: `Ambil ${cheapestSource.name} di ${cheapestSource.cityName} karena harga input paling rendah (${fmtMoney(cheapestSource.buyPrice)}). Total modal bahan dari input saat ini ${fmtMoney(totalMaterialCost)}.`,
      });
    }

    const bonusLocalCityId = Number((bonusLocalCitySelect && bonusLocalCitySelect.value) || 0);
    const bonusLocalCity = getCityNameById(bonusLocalCityId);
    const bonusLocalValue = Number(form.querySelector('[name="bonus_local"]')?.value || 0);
    const craftFeeCityId = Number((craftFeeCitySelect && craftFeeCitySelect.value) || 0);
    const craftFeeCity = getCityNameById(craftFeeCityId);
    const craftFeeValue = Number(form.querySelector('[name="usage_fee"]')?.value || 0);

    if (bonusLocalCity && bonusLocalValue > 0 && craftFeeCity) {
      const sameCity = bonusLocalCityId > 0 && bonusLocalCityId === craftFeeCityId;
      recommendations.push({
        label: "Rekomendasi craft",
        value: sameCity
          ? `${craftFeeCity} cocok untuk craft karena bonus local ${bonusLocalValue.toFixed(0)}% dan craft fee ${fmtMoney(craftFeeValue)} ada di kota yang sama.`
          : `${bonusLocalCity} unggul untuk hemat bahan karena bonus local ${bonusLocalValue.toFixed(0)}%, sedangkan data craft fee yang Anda pakai berasal dari ${craftFeeCity} sebesar ${fmtMoney(craftFeeValue)}.`,
      });
    } else if (bonusLocalCity && bonusLocalValue > 0) {
      recommendations.push({
        label: "Rekomendasi craft",
        value: `${bonusLocalCity} layak diprioritaskan untuk craft karena bonus local ${bonusLocalValue.toFixed(0)}% paling berpengaruh ke efisiensi bahan pada input ini.`,
      });
    } else if (craftFeeCity) {
      recommendations.push({
        label: "Rekomendasi craft",
        value: `${craftFeeCity} dipakai sebagai acuan craft karena craft fee input ada di kota ini (${fmtMoney(craftFeeValue)}). Bonus local belum diisi, jadi belum ada pembanding efisiensi bahan.`,
      });
    }

    const sellCityId = Number((sellPriceCitySelect && sellPriceCitySelect.value) || 0);
    const sellCity = getCityNameById(sellCityId);
    const sellPriceValue = Number(form.querySelector('[name="sell_price"]')?.value || 0);
    if (sellCity && Number.isFinite(sellPriceValue) && sellPriceValue > 0) {
      recommendations.push({
        label: "Rekomendasi jual",
        value: `${sellCity} menjadi tujuan jual pada analisa ini karena harga market input diset ${fmtMoney(sellPriceValue)} di kota tersebut.`,
      });
    }

    return recommendations;
  }

  function renderAnalysisRecommendation(d) {
    if (!analysisRecommendation || !analysisRecommendationList) return;
    const recommendations = buildAnalysisRecommendations();
    lastRenderedAnalysisText = recommendations
      .map((item) => `${item.label}\n${item.value}`)
      .join("\n\n");

    analysisRecommendationList.innerHTML = recommendations
      .map((item) => `<div class="analysis-recommendation-item"><div class="analysis-recommendation-label">${item.label}</div><div class="analysis-recommendation-value">${item.value}</div></div>`)
      .join("");
    analysisRecommendation.hidden = false;
  }

  function resetAnalysisRecommendation() {
    if (!analysisRecommendation || !analysisRecommendationList) return;
    analysisRecommendation.hidden = true;
    analysisRecommendationList.innerHTML = "";
    lastRenderedAnalysisText = "";
  }

  async function copyTextToClipboard(text) {
    if (!text) return false;
    if (navigator.clipboard && window.isSecureContext) {
      await navigator.clipboard.writeText(text);
      return true;
    }

    const textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.setAttribute("readonly", "readonly");
    textarea.style.position = "fixed";
    textarea.style.left = "-9999px";
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    let ok = false;
    try {
      ok = document.execCommand("copy");
    } catch (_) {
      ok = false;
    }
    textarea.remove();
    return ok;
  }

  function buildCopyPayload(d, itemName, materialList) {
    const sc = d && d.scenario ? d.scenario : null;
    const profitMap = new Map();
    if (Array.isArray(d.profit_targets)) {
      for (const r of d.profit_targets) {
        profitMap.set(Number(r.target_margin_percent), r);
      }
    }

    const p5 = profitMap.get(5);
    const p10 = profitMap.get(10);
    const p15 = profitMap.get(15);
    const rows = [
      ["NAMA ITEM", itemName || "-"],
      ["QTY", d.total_output != null ? String(d.total_output) : "-"],
      ["HARGA MARKET", sc && sc.sell_price != null ? fmtMoney(Number(sc.sell_price)) : "-"],
      ["MARGIN", sc && sc.margin_percent != null ? fmtPercent(Number(sc.margin_percent)) : "-"],
      ["SRP 5%", p5 && p5.srp != null ? fmtMoney(Number(p5.srp)) : fmtMoney(Number(d.srp_5))],
      ["SRP 10%", p10 && p10.srp != null ? fmtMoney(Number(p10.srp)) : fmtMoney(Number(d.srp_10))],
      ["SRP 15%", p15 && p15.srp != null ? fmtMoney(Number(p15.srp)) : fmtMoney(Number(d.srp_15))],
      ["MATERIAL LIST TO BUY", materialList || "-"],
      ["PRODUCTION COST", d.production_cost != null ? fmtMoney(Number(d.production_cost)) : "-"],
      ["PROD COST / ITEM", d.production_cost_per_item != null ? fmtMoney(Number(d.production_cost_per_item)) : "-"],
      ["TOTAL PROFIT", sc && sc.total_profit != null ? fmtMoney(Number(sc.total_profit)) : "-"],
      ["PROFIT / ITEM", sc && sc.profit_per_item != null ? fmtMoney(Number(sc.profit_per_item)) : "-"],
    ];

    const summaryTable = rows.map((row) => row.join("\t")).join("\n");
    return `RESULT SUMMARY\n${summaryTable}${lastRenderedAnalysisText ? `\n\nANALISA\n${lastRenderedAnalysisText}` : ""}`;
  }

  function renderHeroSummary(d) {
    const sc = d && d.scenario ? d.scenario : null;
    const modeLabel = sc && sc.mode === "MARKET" ? "Market price" : "SRP 10 default";

    if (heroTotalProfit) {
      heroTotalProfit.textContent = sc && sc.total_profit != null ? fmtMoney(Number(sc.total_profit)) : "-";
    }
    if (heroTotalProfitNote) {
      heroTotalProfitNote.textContent = `Skenario: ${modeLabel}`;
    }

    if (heroProfitItem) {
      heroProfitItem.textContent = sc && sc.profit_per_item != null ? fmtMoney(Number(sc.profit_per_item)) : "-";
    }
    if (heroProfitItemNote) {
      heroProfitItemNote.textContent = "Net per item setelah tax + setup fee";
    }

    if (heroMargin) {
      heroMargin.textContent = sc && sc.margin_percent != null ? fmtPercent(Number(sc.margin_percent)) : "-";
    }
    if (heroMarginNote) {
      const status = d && d.status ? String(d.status) : "-";
      const level = d && d.status_level ? String(d.status_level) : "-";
      heroMarginNote.textContent = `Status: ${status} | Level: ${level}`;
    }
  }

  function resetHeroSummary() {
    if (heroTotalProfit) heroTotalProfit.textContent = "-";
    if (heroTotalProfitNote) heroTotalProfitNote.textContent = "Belum ada hasil kalkulasi";
    if (heroProfitItem) heroProfitItem.textContent = "-";
    if (heroProfitItemNote) heroProfitItemNote.textContent = "Net per item setelah tax + setup fee";
    if (heroMargin) heroMargin.textContent = "-";
    if (heroMarginNote) heroMarginNote.textContent = "Status profit akan tampil setelah hitung";
  }

  function closeTooltipPopover() {
    if (!tooltipPopover) return;
    tooltipPopover.classList.remove("is-open");
    tooltipPopover.setAttribute("aria-hidden", "true");
    activeTooltipButton = null;
    activeTooltipImage = "";
  }

  function positionTooltipPopover(trigger) {
    if (!tooltipPopover || !trigger) return;
    const rect = trigger.getBoundingClientRect();
    const popRect = tooltipPopover.getBoundingClientRect();
    let left = rect.left + (rect.width / 2) - (popRect.width / 2);
    let top = rect.bottom + 12;

    const maxLeft = window.innerWidth - popRect.width - 12;
    left = Math.max(12, Math.min(left, maxLeft));

    if (top + popRect.height > window.innerHeight - 12) {
      top = rect.top - popRect.height - 12;
    }
    top = Math.max(12, top);

    tooltipPopover.style.left = `${left}px`;
    tooltipPopover.style.top = `${top}px`;
  }

  function openTooltipPopover(trigger) {
    if (!tooltipPopover || !tooltipTitle || !tooltipBody || !trigger) return;

    const title = trigger.dataset.tooltipTitle || "Info";
    const body = trigger.dataset.tooltipBody || "";
    activeTooltipImage = trigger.dataset.tooltipImage || "";
    tooltipTitle.textContent = title;
    tooltipBody.textContent = body;
    if (tooltipPreviewBtn) {
      tooltipPreviewBtn.hidden = !activeTooltipImage;
    }

    tooltipPopover.classList.add("is-open");
    tooltipPopover.setAttribute("aria-hidden", "false");
    activeTooltipButton = trigger;
    positionTooltipPopover(trigger);
  }

  function applyImageTransform() {
    if (!tooltipImageFigure) return;
    tooltipImageFigure.style.transform = `translate(-50%, -50%) translate(${imageState.x}px, ${imageState.y}px) scale(${imageState.scale})`;
  }

  function resetImageTransform() {
    imageState.scale = 1;
    imageState.x = 0;
    imageState.y = 0;
    imageState.pinchStartDistance = 0;
    imageState.pinchStartScale = 1;
    imageState.pointerIds.clear();
    applyImageTransform();
  }

  function openTooltipImageModal(src, title) {
    if (!tooltipImageModal || !tooltipImagePreview || !src) return;
    tooltipImagePreview.src = src;
    if (tooltipImageTitle) tooltipImageTitle.textContent = title || "Panduan";
    tooltipImageModal.classList.add("is-open");
    tooltipImageModal.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
    resetImageTransform();
  }

  function closeTooltipImageModal() {
    if (!tooltipImageModal || !tooltipImagePreview) return;
    tooltipImageModal.classList.remove("is-open");
    tooltipImageModal.setAttribute("aria-hidden", "true");
    tooltipImagePreview.removeAttribute("src");
    document.body.style.overflow = "";
    resetImageTransform();
  }

  function adjustImageScale(multiplier) {
    const next = Math.max(imageState.minScale, Math.min(imageState.maxScale, imageState.scale * multiplier));
    imageState.scale = next;
    if (next <= 1) {
      imageState.x = 0;
      imageState.y = 0;
    }
    applyImageTransform();
  }

  function pointerDistance(points) {
    const values = Array.from(points.values());
    if (values.length < 2) return 0;
    const a = values[0];
    const b = values[1];
    const dx = a.x - b.x;
    const dy = a.y - b.y;
    return Math.sqrt((dx * dx) + (dy * dy));
  }

  tooltipButtons.forEach((btn) => {
    btn.addEventListener("click", (ev) => {
      ev.preventDefault();
      ev.stopPropagation();
      if (activeTooltipButton === btn && tooltipPopover && tooltipPopover.classList.contains("is-open")) {
        closeTooltipPopover();
        return;
      }
      openTooltipPopover(btn);
    });
  });

  if (tooltipPreviewBtn) {
    tooltipPreviewBtn.addEventListener("click", () => {
      const title = activeTooltipButton ? (activeTooltipButton.dataset.tooltipTitle || "Panduan") : "Panduan";
      const imageSrc = activeTooltipImage;
      closeTooltipPopover();
      openTooltipImageModal(imageSrc, title);
    });
  }

  document.addEventListener("click", (ev) => {
    const target = ev.target;
    if (!(target instanceof Node)) return;
    if (tooltipPopover && tooltipPopover.contains(target)) return;
    if (target instanceof HTMLElement && target.closest(".field-help-trigger")) return;
    closeTooltipPopover();
  });

  window.addEventListener("resize", () => {
    if (activeTooltipButton) positionTooltipPopover(activeTooltipButton);
  });
  window.addEventListener("scroll", () => {
    if (activeTooltipButton) positionTooltipPopover(activeTooltipButton);
  }, true);

  if (tooltipImageBackdrop) tooltipImageBackdrop.addEventListener("click", closeTooltipImageModal);
  if (tooltipImageClose) tooltipImageClose.addEventListener("click", closeTooltipImageModal);
  if (tooltipZoomInBtn) tooltipZoomInBtn.addEventListener("click", () => adjustImageScale(1.2));
  if (tooltipZoomOutBtn) tooltipZoomOutBtn.addEventListener("click", () => adjustImageScale(1 / 1.2));
  if (tooltipResetBtn) tooltipResetBtn.addEventListener("click", resetImageTransform);

  if (tooltipImageStage) {
    tooltipImageStage.addEventListener("wheel", (ev) => {
      if (!tooltipImageModal || !tooltipImageModal.classList.contains("is-open")) return;
      ev.preventDefault();
      adjustImageScale(ev.deltaY < 0 ? 1.12 : 1 / 1.12);
    }, { passive: false });

    tooltipImageStage.addEventListener("pointerdown", (ev) => {
      if (!tooltipImageModal || !tooltipImageModal.classList.contains("is-open")) return;
      tooltipImageStage.classList.add("is-dragging");
      tooltipImageStage.setPointerCapture(ev.pointerId);
      imageState.pointerIds.set(ev.pointerId, {
        x: ev.clientX,
        y: ev.clientY,
        lastX: ev.clientX,
        lastY: ev.clientY,
      });
      if (imageState.pointerIds.size === 2) {
        imageState.pinchStartDistance = pointerDistance(imageState.pointerIds);
        imageState.pinchStartScale = imageState.scale;
      } else {
        imageState.dragging = true;
      }
    });

    tooltipImageStage.addEventListener("pointermove", (ev) => {
      if (!tooltipImageModal || !tooltipImageModal.classList.contains("is-open")) return;
      const point = imageState.pointerIds.get(ev.pointerId);
      if (!point) return;

      point.x = ev.clientX;
      point.y = ev.clientY;

      if (imageState.pointerIds.size === 2) {
        const distance = pointerDistance(imageState.pointerIds);
        if (imageState.pinchStartDistance > 0) {
          imageState.scale = Math.max(
            imageState.minScale,
            Math.min(imageState.maxScale, imageState.pinchStartScale * (distance / imageState.pinchStartDistance))
          );
          applyImageTransform();
        }
        return;
      }

      if (!imageState.dragging || imageState.scale <= 1) return;
      imageState.x += ev.clientX - point.lastX;
      imageState.y += ev.clientY - point.lastY;
      point.lastX = ev.clientX;
      point.lastY = ev.clientY;
      applyImageTransform();
    });

    const stopPointer = (ev) => {
      imageState.pointerIds.delete(ev.pointerId);
      imageState.dragging = false;
      if (tooltipImageStage) tooltipImageStage.classList.remove("is-dragging");
      if (imageState.pointerIds.size < 2) {
        imageState.pinchStartDistance = 0;
        imageState.pinchStartScale = imageState.scale;
      }
    };

    tooltipImageStage.addEventListener("pointerup", stopPointer);
    tooltipImageStage.addEventListener("pointercancel", stopPointer);
    tooltipImageStage.addEventListener("pointerleave", stopPointer);
  }

  addMaterialBtn.addEventListener("click", () => addMaterialRow());

  function openSidebar() {
    if (!sidebar || !sidebarBackdrop) return;
    sidebar.classList.add("is-open");
    sidebarBackdrop.classList.add("is-open");
  }

  function closeSidebar() {
    if (!sidebar || !sidebarBackdrop) return;
    sidebar.classList.remove("is-open");
    sidebarBackdrop.classList.remove("is-open");
  }

  if (toggleSidebarBtn) toggleSidebarBtn.addEventListener("click", openSidebar);
  if (closeSidebarBtn) closeSidebarBtn.addEventListener("click", closeSidebar);
  if (sidebarBackdrop) sidebarBackdrop.addEventListener("click", closeSidebar);

  function openHelpModal() {
    if (!helpModal) return;
    helpModal.classList.add("is-open");
    helpModal.setAttribute("aria-hidden", "false");
  }

  function closeHelpModal() {
    if (!helpModal) return;
    helpModal.classList.remove("is-open");
    helpModal.setAttribute("aria-hidden", "true");
  }

  if (openHelpModalBtn) openHelpModalBtn.addEventListener("click", openHelpModal);
  if (closeHelpModalBtn) closeHelpModalBtn.addEventListener("click", closeHelpModal);
  if (closeHelpFooterBtn) closeHelpFooterBtn.addEventListener("click", closeHelpModal);
  if (closeHelpBackdrop) closeHelpBackdrop.addEventListener("click", closeHelpModal);
  document.addEventListener("keydown", (ev) => {
    if (ev.key !== "Escape") return;
    closeTooltipPopover();
    closeTooltipImageModal();
    closeHelpModal();
  });

  function serializeState() {
    const fd = new FormData(form);
    const fields = {};
    for (const [k, v] of fd.entries()) {
      const s = String(v);
      // Don't persist empty strings so defaults remain effective on reload.
      if (s === "") continue;
      fields[k] = s;
    }

    const materials = [];
    for (const row of materialsRoot.querySelectorAll(".calc-material-row")) {
      const inputs = row.querySelectorAll("input, select");
      materials.push({
        item_id: inputs[0].value,
        name: inputs[1].value,
        qty_per_recipe: inputs[2].value,
        buy_price: inputs[3].value,
        return_type: inputs[4].value,
        city_id: inputs[5].value,
      });
    }

    return { fields, materials };
  }

  function applyState(state) {
    if (!state || typeof state !== "object") return;
    const fields = state.fields || {};
    for (const [k, v] of Object.entries(fields)) {
      const elField = form.querySelector(`[name="${CSS.escape(k)}"]`);
      if (!elField) continue;
      const s = String(v);
      if (s === "") continue;
      elField.value = s;
    }

    if (Array.isArray(state.materials)) {
      clearMaterials();
      for (const m of state.materials) {
        addMaterialRow({
          item_id: m && m.item_id != null ? Number(m.item_id) : 0,
          name: (m && m.name) || "",
          qty_per_recipe: m && m.qty_per_recipe != null ? Number(m.qty_per_recipe) : 0,
          buy_price: m && m.buy_price != null ? Number(m.buy_price) : 0,
          return_type: (m && m.return_type) || "RETURN",
          city_id: m && m.city_id != null ? Number(m.city_id) : 0,
        });
      }
      reindexMaterialNames();
    }
  }

  let saveTimer = null;
  function scheduleSave() {
    if (saveTimer) window.clearTimeout(saveTimer);
    saveTimer = window.setTimeout(() => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(serializeState()));
      } catch (_) {
        // ignore
      }
    }, 200);
  }

  function loadState() {
    try {
      const raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return false;
      const parsed = JSON.parse(raw);
      applyState(parsed);
      return true;
    } catch (_) {
      return false;
    }
  }

  // Initialize: restore from localStorage, else defaults.
  if (!loadState()) {
    defaultMaterials();
  }
  reindexMaterialNames();

  form.addEventListener("input", scheduleSave);
  form.addEventListener("change", scheduleSave);

  if (clearBtn) {
    clearBtn.addEventListener("click", () => {
      try { localStorage.removeItem(STORAGE_KEY); } catch (_) {}
      form.reset();
      setFieldValue("item_id", "");
      defaultMaterials();
      setFeedback("");
      resetHeroSummary();
      // Ensure required numeric field stays at HTML default after reset.
      const t = form.querySelector('[name="target_output_qty"]');
      if (t && (!t.value || String(t.value).trim() === "")) t.value = "100";
      scheduleSave();
    });
  }

  if (recipeItemSelect) {
    loadRecipeItems("").catch(() => {});
  }

  if (recipeAutoFillBtn) {
    recipeAutoFillBtn.addEventListener("click", async () => {
      setFeedback("");

      const entryId = recipeItemSelect ? String(recipeItemSelect.value || "") : "";
      const cityId = recipeCitySelect ? Number(recipeCitySelect.value || "0") : 0;
      if (!entryId) {
        setFeedback("Pilih item recipe database terlebih dahulu.");
        return;
      }

      recipeAutoFillBtn.disabled = true;
      try {
        const qs = new URLSearchParams({ entry_id: entryId });
        if (cityId > 0) qs.set("city_id", String(cityId));
        const json = await fetchJson(`/api/calculator/recipes/detail?${qs.toString()}`);
        populateRecipeDetail(json.data || null);
      } catch (err) {
        setFeedback(err instanceof Error ? err.message : "Gagal load recipe.");
      } finally {
        recipeAutoFillBtn.disabled = false;
      }
    });
  }

  if (saveCraftFeeBtn) {
    saveCraftFeeBtn.addEventListener("click", async () => {
      setFeedback("");
      const itemId = Number((form.querySelector('[name="item_id"]') || {}).value || "0");
      const cityId = craftFeeCitySelect ? Number(craftFeeCitySelect.value || "0") : 0;
      const usageFee = Number((form.querySelector('[name="usage_fee"]') || {}).value || "0");
      const csrfToken = String((form.querySelector('[name="_token"]') || {}).value || "");

      if (!itemId) {
        setFeedback("Pilih item recipe database terlebih dahulu agar craft price bisa disimpan.");
        return;
      }
      if (!cityId) {
        setFeedback("Pilih kota craft price terlebih dahulu.");
        return;
      }

      saveCraftFeeBtn.disabled = true;
      try {
        const fd = new FormData();
        fd.append("_token", csrfToken);
        fd.append("item_id", String(itemId));
        fd.append("city_id", String(cityId));
        fd.append("usage_fee", String(usageFee));
        const res = await fetch("/api/calculator/craft-fee/save", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd,
        });
        const json = await res.json().catch(() => null);
        if (!json || json.success !== true) {
          throw new Error((json && json.message) || "Gagal simpan craft price.");
        }
        setFeedback("Craft price berhasil disimpan.", "success");
      } catch (err) {
        setFeedback(err instanceof Error ? err.message : "Gagal simpan craft price.");
      } finally {
        saveCraftFeeBtn.disabled = false;
      }
    });
  }

  if (saveSellPriceBtn) {
    saveSellPriceBtn.addEventListener("click", async () => {
      setFeedback("");
      const itemId = Number((form.querySelector('[name="item_id"]') || {}).value || "0");
      const cityId = sellPriceCitySelect ? Number(sellPriceCitySelect.value || "0") : 0;
      const sellPrice = Number((form.querySelector('[name="sell_price"]') || {}).value || "0");
      const csrfToken = String((form.querySelector('[name="_token"]') || {}).value || "");

      if (!itemId) {
        setFeedback("Pilih item recipe database terlebih dahulu agar harga jual bisa disimpan.");
        return;
      }
      if (!cityId) {
        setFeedback("Pilih kota jual terlebih dahulu.");
        return;
      }

      saveSellPriceBtn.disabled = true;
      try {
        const fd = new FormData();
        fd.append("_token", csrfToken);
        fd.append("item_id", String(itemId));
        fd.append("city_id", String(cityId));
        fd.append("sell_price", String(sellPrice));
        const res = await fetch("/api/calculator/sell-price/save", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd,
        });
        const json = await res.json().catch(() => null);
        if (!json || json.success !== true) {
          throw new Error((json && json.message) || "Gagal simpan harga jual.");
        }
        setFeedback("Harga jual berhasil disimpan.", "success");
      } catch (err) {
        setFeedback(err instanceof Error ? err.message : "Gagal simpan harga jual.");
      } finally {
        saveSellPriceBtn.disabled = false;
      }
    });
  }

  if (saveMaterialPricesBtn) {
    saveMaterialPricesBtn.addEventListener("click", async () => {
      setFeedback("");
      const csrfToken = String((form.querySelector('[name="_token"]') || {}).value || "");
      const materials = [];

      for (const row of materialsRoot.querySelectorAll(".calc-material-row")) {
        const itemIdField = row.querySelector(".material-item-id");
        const cityField = row.querySelector(".material-city");
        const priceField = row.querySelector(".material-price");
        const itemId = Number((itemIdField || {}).value || "0");
        const cityId = Number((cityField || {}).value || "0");
        const buyPrice = Number((priceField || {}).value || "0");

        if (!itemId || !cityId) continue;
        materials.push({ item_id: itemId, city_id: cityId, buy_price: buyPrice });
      }

      if (materials.length === 0) {
        setFeedback("Pilih minimal satu kota beli material dari item recipe database.");
        return;
      }

      saveMaterialPricesBtn.disabled = true;
      try {
        const fd = new FormData();
        fd.append("_token", csrfToken);
        for (let i = 0; i < materials.length; i++) {
          fd.append(`materials[${i}][item_id]`, String(materials[i].item_id));
          fd.append(`materials[${i}][city_id]`, String(materials[i].city_id));
          fd.append(`materials[${i}][buy_price]`, String(materials[i].buy_price));
        }
        const res = await fetch("/api/calculator/material-prices/save", {
          method: "POST",
          headers: { "X-Requested-With": "XMLHttpRequest" },
          body: fd,
        });
        const json = await res.json().catch(() => null);
        if (!json || json.success !== true) {
          throw new Error((json && json.message) || "Gagal simpan harga material.");
        }
        setFeedback(json.message || "Harga material berhasil disimpan.", "success");
      } catch (err) {
        setFeedback(err instanceof Error ? err.message : "Gagal simpan harga material.");
      } finally {
        saveMaterialPricesBtn.disabled = false;
      }
    });
  }

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    setFeedback("");

    const fd = new FormData(form);

    const materials = [];
    for (const row of materialsRoot.querySelectorAll(".calc-material-row")) {
      const inputs = row.querySelectorAll("input, select");
      const name = inputs[1].value.trim();
      const normalizedName = upper(name);
      inputs[1].value = normalizedName;
      const qty = Number(inputs[2].value || "0");
      const price = Number(inputs[3].value || "0");
      const rt = String(inputs[4].value || "RETURN");
      materials.push({ name: normalizedName, qty_per_recipe: qty, buy_price: price, return_type: rt });
    }

    const payload = {
      item_name: upper(String(fd.get("item_name") || "")),
      bonus_basic: readNumber(fd, "bonus_basic"),
      bonus_local: readNumber(fd, "bonus_local"),
      bonus_daily: readNumber(fd, "bonus_daily"),
      return_rounding_mode: String(fd.get("return_rounding_mode") || "SPREADSHEET_BULK"),
      craft_with_focus: String(fd.get("craft_with_focus")) === "1",
      focus_points: readNumber(fd, "focus_points"),
      focus_per_craft: readNumber(fd, "focus_per_craft"),
      usage_fee: readNumber(fd, "usage_fee"),
      item_value: readNumber(fd, "item_value"),
      output_qty: readInt(fd, "output_qty"),
      target_output_qty: readInt(fd, "target_output_qty"),
      premium_status: String(fd.get("premium_status")) === "1",
      materials,
    };

    const rawSell = fd.get("sell_price");
    if (rawSell != null && String(rawSell).trim() !== "") {
      payload.sell_price = Number(rawSell);
    }

    if (!payload.item_name || payload.item_name.trim() === "") {
      setFeedback("Nama Item wajib diisi.");
      return;
    }

    if (!payload.output_qty || payload.output_qty < 1) {
      setFeedback("Output Quantity / Recipe wajib diisi minimal 1.");
      return;
    }

    if (!payload.target_output_qty || payload.target_output_qty < 1) {
      setFeedback("Target Output (Item) wajib diisi minimal 1.");
      return;
    }

    if (payload.bonus_local > 0) {
      const bonusLocalCityId = String(fd.get("bonus_local_city_id") || "").trim();
      if (bonusLocalCityId === "") {
        setFeedback("Kota Bonus Local wajib dipilih jika Bonus Local lebih dari 0.");
        return;
      }
    }

    if (rawSell == null || String(rawSell).trim() === "") {
      setFeedback("Market Price wajib diisi.");
      return;
    }

    if (payload.craft_with_focus) {
      if (!payload.focus_points || payload.focus_points <= 0) {
        setFeedback("Focus Points wajib diisi jika Craft With Focus = Yes.");
        return;
      }
      if (!payload.focus_per_craft || payload.focus_per_craft <= 0) {
        setFeedback("Focus per Craft wajib diisi jika Craft With Focus = Yes.");
        return;
      }
      if (payload.focus_points < payload.focus_per_craft) {
        setFeedback("Focus Points harus lebih besar atau sama dengan Focus per Craft. Cek apakah nilai tertukar (contoh: points 30000, per craft 6602).");
        return;
      }
    }

    let res;
    try {
      res = await fetch("/api/calculate", {
        method: "POST",
        headers: { "Content-Type": "application/json", "X-Requested-With": "XMLHttpRequest" },
        body: JSON.stringify(payload),
      });
    } catch (err) {
      setFeedback("Gagal request ke server.");
      return;
    }

    const json = await res.json().catch(() => null);
    if (!json || json.success !== true) {
      const msg = (json && json.message) ? json.message : "Kalkulasi gagal.";
      const errors = (json && json.errors) ? JSON.stringify(json.errors) : "";
      setFeedback(errors ? (msg + " " + errors) : msg);
      return;
    }

    const d = json.data;
    renderMaterialSummary(d.materials);
    renderFocusSummary(d.focus);
    renderMaterialFields(d.material_fields);
    renderIterations(d.iterations);
    renderExcelResult(d);
    renderSummaryRow(d);
    renderHeroSummary(d);
    renderAnalysisRecommendation(d);

    scheduleSave();
  });

  function renderExcelResult(d) {
    const table = document.getElementById("excel-result");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    const profitMap = new Map();
    if (Array.isArray(d.profit_targets)) {
      for (const r of d.profit_targets) {
        profitMap.set(Number(r.target_margin_percent), r);
      }
    }

    function addRow(label, value, isMoney) {
      const tr = document.createElement("tr");
      const tdL = document.createElement("td");
      tdL.textContent = label;
      const tdV = document.createElement("td");
      tdV.className = "right";
      if (value == null || value === "") tdV.textContent = "-";
      else if (isMoney) tdV.textContent = fmtMoney(Number(value));
      else tdV.textContent = String(value);
      tr.appendChild(tdL);
      tr.appendChild(tdV);
      tbody.appendChild(tr);
    }

    addRow("Craftable Item", d.total_output, false);
    addRow("Craft Fee/CRAFT (Output/Recipe)", d.craft_fee_per_recipe, true);
    addRow("Craft Fee Total", d.craft_fee_total, true);
    addRow("Material Cost", d.material_cost, true);
    addRow("Material Return", d.material_return_value, true);
    addRow("MATERIAL COST TOTAL", d.net_material_cost, true);
    addRow("TAX %", (d.tax_percent != null) ? (Number(d.tax_percent).toFixed(0)) : null, false);
    addRow("SETUP FEE %", (d.setup_fee_percent != null) ? (Number(d.setup_fee_percent).toFixed(1)) : null, false);
    addRow("Production Cost", d.production_cost, true);
    addRow("Production Cost per Item", d.production_cost_per_item, true);
    addRow("SRP 5%", d.srp_5, true);
    addRow("SRP 10%", d.srp_10, true);
    addRow("SRP 15%", d.srp_15, true);
    addRow("SRP 20%", d.srp_20, true);

    const p5 = profitMap.get(5);
    const p10 = profitMap.get(10);
    const p15 = profitMap.get(15);
    const p20 = profitMap.get(20);
    addRow("PROFIT 5%", p5 ? p5.total_profit : null, true);
    addRow("PROFIT 10%", p10 ? p10.total_profit : null, true);
    addRow("PROFIT 15%", p15 ? p15.total_profit : null, true);
    addRow("PROFIT 20%", p20 ? p20.total_profit : null, true);

    // Always show a profit/status scenario: MARKET if provided, else SRP 10 default.
    const sc = d.scenario || null;
    if (sc) {
      addRow("MARKET MODE", sc.mode, false);
      addRow("MARKET PRICE", sc.sell_price, true);
      addRow("PROFIT PER ITEM", sc.profit_per_item, true);
      addRow("TOTAL PROFIT", sc.total_profit, true);
      addRow("MARGIN", sc.margin_percent != null ? (Number(sc.margin_percent).toFixed(2) + "%") : null, false);
      addRow("STATUS", d.status, false);
      addRow("STATUS LEVEL", d.status_level, false);
    }
  }

  function renderSummaryRow(d) {
    const table = document.getElementById("summary-row");
    if (!table) return;
    const tbody = table.querySelector("tbody");
    if (!tbody) return;
    tbody.innerHTML = "";

    const nameInput = form.querySelector('[name="item_name"]');
    const itemName = nameInput ? String(nameInput.value || "").trim() : "";

    const profitMap = new Map();
    if (Array.isArray(d.profit_targets)) {
      for (const r of d.profit_targets) {
        profitMap.set(Number(r.target_margin_percent), r);
      }
    }

    const p5 = profitMap.get(5);
    const p10 = profitMap.get(10);
    const p15 = profitMap.get(15);

    const materialListLines = Array.isArray(d.materials)
      ? d.materials
          .filter((m) => m && m.material_to_buy && m.buy_price != null)
          .map((m, idx) => `${idx + 1}. ${m.material_to_buy} ${m.name} @${Number(m.buy_price).toFixed(0)}`)
      : [];
    const materialList = materialListLines.join("\n");

    const tr = document.createElement("tr");

    function td(text, right) {
      const cell = document.createElement("td");
      if (right) cell.className = "right";
      cell.textContent = text;
      return cell;
    }

    function tdBadgeMoney(value) {
      const cell = document.createElement("td");
      cell.className = "right";
      if (value == null || value === "" || !Number.isFinite(Number(value))) {
        cell.textContent = "-";
        return cell;
      }
      const n = Number(value);
      const badge = document.createElement("span");
      badge.className = "badge " + (n > 0 ? "badge-positive" : (n < 0 ? "badge-negative" : "badge-neutral"));
      badge.textContent = fmtMoney(n);
      cell.appendChild(badge);
      return cell;
    }

    const qty = d.total_output != null ? String(d.total_output) : "-";

    const sc = d.scenario || null;
    const marketValue = sc && sc.sell_price != null ? Number(sc.sell_price) : NaN;
    const market = Number.isFinite(marketValue) ? fmtMoney(marketValue) : "-";

    const margin = sc && sc.margin_percent != null
      ? (Number(sc.margin_percent).toFixed(2) + "%")
      : "-";
    const srp5 = p5 && p5.srp != null ? fmtMoney(Number(p5.srp)) : fmtMoney(Number(d.srp_5));
    const srp10 = p10 && p10.srp != null ? fmtMoney(Number(p10.srp)) : fmtMoney(Number(d.srp_10));
    const srp15 = p15 && p15.srp != null ? fmtMoney(Number(p15.srp)) : fmtMoney(Number(d.srp_15));
    const prodCost = d.production_cost != null ? fmtMoney(Number(d.production_cost)) : "-";
    const prodCostItem = d.production_cost_per_item != null ? fmtMoney(Number(d.production_cost_per_item)) : "-";
    const totalProfit = sc && sc.total_profit != null ? fmtMoney(Number(sc.total_profit)) : "-";
    const profitItem = sc && sc.profit_per_item != null ? fmtMoney(Number(sc.profit_per_item)) : "-";

    tr.appendChild(td(itemName || "-", false));
    tr.appendChild(td(qty, true));
    tr.appendChild(td(market, true));
    tr.appendChild(td(margin, true));
    tr.appendChild(td(srp5, true));
    tr.appendChild(td(srp10, true));
    tr.appendChild(td(srp15, true));
    const tdList = td(materialList || "-", false);
    tdList.classList.add("material-list");
    tr.appendChild(tdList);
    tr.appendChild(td(prodCost, true));
    tr.appendChild(td(prodCostItem, true));
    tr.appendChild(tdBadgeMoney(sc && sc.total_profit != null ? sc.total_profit : null));
    tr.appendChild(tdBadgeMoney(sc && sc.profit_per_item != null ? sc.profit_per_item : null));

    const actionCell = document.createElement("td");
    actionCell.className = "summary-action-cell";
    const copyBtn = document.createElement("button");
    copyBtn.type = "button";
    copyBtn.className = "button button-ghost summary-copy-button";
    copyBtn.textContent = "Salin";
    copyBtn.addEventListener("click", async () => {
      const originalText = copyBtn.textContent;
      const copied = await copyTextToClipboard(buildCopyPayload(d, itemName, materialList));
      copyBtn.textContent = copied ? "Tersalin" : "Gagal";
      window.setTimeout(() => {
        copyBtn.textContent = originalText;
      }, 1400);
    });
    actionCell.appendChild(copyBtn);
    tr.appendChild(actionCell);

    tbody.appendChild(tr);
  }

  resetHeroSummary();
  resetAnalysisRecommendation();
})();
