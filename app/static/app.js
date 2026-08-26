const i18n = {
  en: {
    lang: "Language",
    project: "Project",
    lists: "Lists",
    open: "Open",
    sample: "Open sample MN-P-RHN-PID-0001",
    empty: "No project open",
    header: "Header setup",
    logo: "Upload logo",
    save: "Save template",
    import: "Import Excel",
    export: "Export Excel",
    filter: "Filter rows…",
    select: "Select a list",
    sub: "Open a Plant 3D project folder, then choose a list.",
    foot: "Reads ProcessPower.dcf (SQLite). No AutoCAD licence required for listing.",
    drawings: "Drawings",
    equipment: "Equipment",
    valves: "Hand valves",
    cvalves: "Control valves",
    instruments: "Instruments",
    lines: "Pipe lines",
    items: "Eng. items",
    diffTitle: "Import preview",
    cancel: "Cancel",
    apply: "Write to demo copy",
  },
  nl: {
    lang: "Taal",
    project: "Project",
    lists: "Lijsten",
    open: "Openen",
    sample: "Open voorbeeld MN-P-RHN-PID-0001",
    empty: "Geen project geopend",
    header: "Koptekst instellen",
    logo: "Logo uploaden",
    save: "Sjabloon opslaan",
    import: "Excel importeren",
    export: "Excel exporteren",
    filter: "Filter rijen…",
    select: "Kies een lijst",
    sub: "Open een Plant 3D-projectmap en kies daarna een lijst.",
    foot: "Leest ProcessPower.dcf (SQLite). Geen AutoCAD-licentie nodig voor lijsten.",
    drawings: "Tekeningen",
    equipment: "Apparaten",
    valves: "Handafsluiters",
    cvalves: "Gestuurde afsluiters",
    instruments: "Instrumenten",
    lines: "Leidingen",
    items: "Eng. items",
    diffTitle: "Importvoorbeeld",
    cancel: "Annuleren",
    apply: "Schrijf naar demokopie",
  },
};

let lang = "en";
let templates = [];
let currentId = null;
let currentTemplate = null;
let currentRows = [];
let pendingChanges = [];
let projectDetails = null;

const CATEGORY_LABELS = {
  standard: "Standard project fields",
  S88: "Custom properties (S88)",
  custom: "Other custom properties",
};

const $ = (id) => document.getElementById(id);

function t(key) {
  return i18n[lang][key];
}

function applyLang() {
  $("lbl-project").textContent = t("project");
  $("lbl-lists").textContent = t("lists");
  $("btn-open").textContent = t("open");
  $("btn-header").textContent = t("header");
  $("btn-logo").textContent = t("logo");
  $("btn-save-tpl").textContent = t("save");
  $("btn-import").textContent = t("import");
  $("btn-export").textContent = t("export");
  $("search").placeholder = t("filter");
  $("foot-note").textContent = t("foot");
  $("diff-title").textContent = t("diffTitle");
  $("diff-cancel").textContent = t("cancel");
  $("diff-apply").textContent = t("apply");
  if (!currentId) {
    $("report-title").textContent = t("select");
    $("report-sub").textContent = t("sub");
  }
  renderTemplates();
}

async function api(url, options) {
  const res = await fetch(url, options);
  if (!res.ok) {
    const text = await res.text();
    throw new Error(text || res.statusText);
  }
  const type = res.headers.get("content-type") || "";
  if (type.includes("application/json")) return res.json();
  return res;
}

const STAT_TO_TEMPLATE = {
  drawings: "drawing_list",
  equipment: "equipment_list",
  hand_valves: "valve_list",
  control_valves: "control_valve_list",
  instruments: "instrument_list",
  pipe_lines: "line_list",
  engineering_items: "component_list",
};

function showLogo(url) {
  const img = $("tb-logo-img");
  const placeholder = $("tb-logo-placeholder");
  const box = $("tb-logo");
  if (!url) {
    img.hidden = true;
    img.removeAttribute("src");
    placeholder.hidden = false;
    box.classList.remove("has-image");
    return;
  }
  img.src = url;
  img.hidden = false;
  placeholder.hidden = true;
  box.classList.add("has-image");
}

async function refreshLogo() {
  const probe = await fetch(`/api/logo?t=${Date.now()}`);
  if (probe.ok) {
    showLogo(`/api/logo?t=${Date.now()}`);
  } else {
    showLogo(null);
  }
}

function renderProject(p) {
  const card = $("project-card");
  if (!p) {
    card.innerHTML = `<p class="muted">${t("empty")}</p>`;
    return;
  }
  $("project-path").value = p.project_dir || "";
  card.innerHTML = `
    <h3>${p.name || "—"}</h3>
    <div>${p.description || ""}</div>
    <div class="muted">${p.standard || ""} · ${p.status || ""}</div>
    <div class="muted">${p.location || ""} ${p.location_code ? "(" + p.location_code + ")" : ""}</div>
  `;
  const c = p.counts || {};
  $("stats").innerHTML = [
    ["drawings", c.drawings, t("drawings")],
    ["equipment", c.equipment, t("equipment")],
    ["hand_valves", c.hand_valves, t("valves")],
    ["control_valves", c.control_valves, t("cvalves")],
    ["instruments", c.instruments, t("instruments")],
    ["pipe_lines", c.pipe_lines, t("lines")],
    ["engineering_items", c.engineering_items, t("items")],
  ]
    .map(([key, n, label]) => {
      const templateId = STAT_TO_TEMPLATE[key];
      const active = templateId === currentId ? "active" : "";
      return `<button type="button" class="stat ${active}" data-template="${templateId}"><b>${n ?? 0}</b><span>${label}</span></button>`;
    })
    .join("");
}

function renderTemplates() {
  const nav = $("template-nav");
  nav.innerHTML = templates
    .map((tpl) => {
      const label = lang === "nl" ? tpl.name_nl : tpl.name;
      const active = tpl.id === currentId ? "active" : "";
      return `<button type="button" class="${active}" data-id="${tpl.id}">${label}</button>`;
    })
    .join("");
}

function headerFor(col) {
  return col.header || col.key;
}

function renderSheet(payload) {
  currentTemplate = payload.template;
  currentRows = payload.rows;
  const name = lang === "nl" ? payload.template.name_nl : payload.template.name;
  $("report-title").textContent = name;
  $("report-sub").textContent = `${payload.row_count} rows · ${payload.template.description || ""}`;
  renderTitleBlock(payload.template);
  const body = $("rev-table").querySelector("tbody");
  body.innerHTML = (payload.template.revision_table || [])
    .map(
      (r) =>
        `<tr><td>${r.rev || ""}</td><td>${r.date || ""}</td><td>${r.desc || ""}</td><td>${r.drawn || ""}</td></tr>`
    )
    .join("");
  renderGrid(currentRows);
}

function visibleColumns() {
  return (currentTemplate?.columns || []).filter((c) => c.visible !== false);
}

function renderGrid(rows) {
  const cols = visibleColumns();
  const thead = $("grid").querySelector("thead");
  const tbody = $("grid").querySelector("tbody");
  thead.innerHTML = `<tr>${cols.map((c) => `<th>${headerFor(c)}</th>`).join("")}</tr>`;
  tbody.innerHTML = rows
    .map((row) => `<tr>${cols.map((c) => `<td title="${escapeHtml(row[c.key] ?? "")}">${escapeHtml(row[c.key] ?? "")}</td>`).join("")}</tr>`)
    .join("");
  $("row-count").textContent = `${rows.length} / ${currentRows.length}`;
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

async function ensureTemplates() {
  if (!templates.length) {
    templates = await api("/api/templates");
  }
  renderTemplates();
}

async function loadReport(id) {
  await ensureTemplates();
  currentId = id;
  renderTemplates();
  document.querySelectorAll(".stat").forEach((el) => {
    el.classList.toggle("active", el.dataset.template === id);
  });
  const payload = await api(`/api/report/${id}`);
  renderSheet(payload);
}

function renderTitleBlock(template) {
  const header = template?.header || {};
  $("tb-title").textContent = header.title || template?.name || "—";
  const parts = [];
  for (const field of header.fields || []) {
    parts.push(
      `<div><dt>${escapeHtml(field.label || field.key)}</dt><dd>${escapeHtml(field.value || "")}</dd></div>`
    );
  }
  parts.push(`<div><dt>Document</dt><dd>${escapeHtml(header.document_number || "—")}</dd></div>`);
  parts.push(`<div><dt>Revision</dt><dd>${escapeHtml(header.revision || "—")}</dd></div>`);
  $("tb-fields").innerHTML = parts.join("");
}

const DEFAULT_HEADER_KEYS = [
  "Project_Name",
  "Project_Description",
  "Project_Number",
  "S88_Projectstatus",
  "S88_Locatie",
];

function selectedHeaderKeys() {
  const fields = currentTemplate?.header?.fields || [];
  if (fields.length) {
    return new Set(fields.map((f) => f.key));
  }
  return new Set(DEFAULT_HEADER_KEYS);
}

async function ensureProjectDetails() {
  if (!projectDetails) {
    projectDetails = await api("/api/project/details");
  }
  return projectDetails;
}

function renderHeaderFieldGroups() {
  const container = $("header-field-groups");
  if (!projectDetails?.catalogue?.length) {
    container.innerHTML = `<p class="muted">Open a project first.</p>`;
    return;
  }
  const selected = selectedHeaderKeys();
  const groups = {};
  for (const item of projectDetails.catalogue) {
    groups[item.category] = groups[item.category] || [];
    groups[item.category].push(item);
  }
  container.innerHTML = Object.entries(groups)
    .map(([category, items]) => {
      const title = items[0]?.category_label || CATEGORY_LABELS[category] || category;
      const checks = items
        .map(
          (item) => `<label class="field-check">
            <input type="checkbox" data-key="${escapeHtml(item.key)}" data-label="${escapeHtml(item.label)}" ${selected.has(item.key) ? "checked" : ""} />
            <span>${escapeHtml(item.label)}<small>${escapeHtml(item.value || "—")}</small></span>
          </label>`
        )
        .join("");
      return `<div class="field-group"><div class="field-group-title">${escapeHtml(title)}</div>${checks}</div>`;
    })
    .join("");
}

function renderRevisionEditor(rows) {
  const tbody = $("rev-editor").querySelector("tbody");
  tbody.innerHTML = (rows || [])
    .map(
      (row) => `<tr>
        <td><input data-rev="rev" value="${escapeHtml(row.rev || "")}" /></td>
        <td><input data-rev="date" value="${escapeHtml(row.date || "")}" /></td>
        <td><input data-rev="desc" value="${escapeHtml(row.desc || "")}" /></td>
        <td><input data-rev="drawn" value="${escapeHtml(row.drawn || "")}" /></td>
      </tr>`
    )
    .join("");
}

function readRevisionEditor() {
  return [...$("rev-editor").querySelectorAll("tbody tr")].map((tr) => {
    const item = {};
    tr.querySelectorAll("input[data-rev]").forEach((input) => {
      item[input.dataset.rev] = input.value.trim();
    });
    return item;
  }).filter((row) => row.rev || row.date || row.desc || row.drawn);
}

async function openHeaderDialog() {
  if (!currentTemplate) return;
  await ensureProjectDetails();
  const header = currentTemplate.header || {};
  $("hdr-title").value = header.title || currentTemplate.name || "";
  $("hdr-doc").value = header.document_number || "";
  $("hdr-rev").value = header.revision || "";
  $("hdr-date").value = (header.date || "").slice(0, 10);
  $("hdr-company").value = header.company || "";
  renderHeaderFieldGroups();
  const revisions = currentTemplate.revision_table?.length
    ? currentTemplate.revision_table
    : projectDetails.revisions || [];
  renderRevisionEditor(revisions);
  $("header-dialog").showModal();
}

function applyHeaderToTemplate() {
  if (!currentTemplate) return;
  currentTemplate.header = currentTemplate.header || {};
  currentTemplate.header.title = $("hdr-title").value.trim();
  currentTemplate.header.document_number = $("hdr-doc").value.trim();
  currentTemplate.header.revision = $("hdr-rev").value.trim();
  currentTemplate.header.date = $("hdr-date").value;
  currentTemplate.header.company = $("hdr-company").value.trim();
  currentTemplate.header.fields = [...$("header-field-groups").querySelectorAll("input[type=checkbox]:checked")].map(
    (input) => ({ key: input.dataset.key, label: input.dataset.label })
  );
  for (const field of currentTemplate.header.fields) {
    field.value = projectDetails?.values?.[field.key] || "";
  }
  currentTemplate.revision_table = readRevisionEditor();
}

async function persistCurrentTemplate() {
  if (!currentTemplate?.id) return;
  await api("/api/templates", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(currentTemplate),
  });
}

async function openProject(path) {
  const result = await api("/api/open-project", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ path }),
  });
  projectDetails = null;
  renderProject(result.project);
  await ensureProjectDetails();
  await ensureTemplates();
  await loadReport(currentId || "valve_list");
}

async function boot() {
  applyLang();
  await refreshLogo();
  await ensureTemplates();
  try {
    const sample = await api("/api/sample-path");
    $("project-path").value = sample.path;
    if (sample.exists) {
      await openProject(sample.path);
    }
  } catch (err) {
    $("project-card").innerHTML = `<p class="muted">${escapeHtml(err.message)}</p>`;
  }
}

$("template-nav").addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-id]");
  if (btn) loadReport(btn.dataset.id);
});

$("btn-open").addEventListener("click", async () => {
  try {
    await openProject($("project-path").value.trim());
  } catch (err) {
    alert(err.message);
  }
});

$("stats").addEventListener("click", (e) => {
  const card = e.target.closest("[data-template]");
  if (card) loadReport(card.dataset.template).catch((err) => alert(err.message));
});

$("search").addEventListener("input", () => {
  const q = $("search").value.toLowerCase();
  if (!q) return renderGrid(currentRows);
  renderGrid(
    currentRows.filter((row) =>
      Object.values(row).some((v) => String(v).toLowerCase().includes(q))
    )
  );
});

$("btn-export").addEventListener("click", () => {
  if (!currentId) return;
  window.location = `/api/export/${currentId}`;
});

$("btn-save-tpl").addEventListener("click", async () => {
  if (!currentTemplate) return;
  const copy = structuredClone(currentTemplate);
  const baseId = copy.id.replace(/_standard$/, "");
  copy.id = `${baseId}_standard`;
  if (!copy.name.includes("(company standard)")) {
    copy.name = `${copy.name} (company standard)`;
  }
  if (!copy.name_nl.includes("(bedrijfsstandaard)")) {
    copy.name_nl = `${copy.name_nl} (bedrijfsstandaard)`;
  }
  await api("/api/templates", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify(copy),
  });
  templates = await api("/api/templates");
  renderTemplates();
  alert("Template saved with header fields and revision table. Select it from the list on the left.");
});

$("logo-file").addEventListener("change", async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  try {
    const data = new FormData();
    data.append("file", file);
    await api("/api/logo", { method: "POST", body: data });
    showLogo(`/api/logo?t=${Date.now()}`);
  } catch (err) {
    alert(err.message);
  } finally {
    e.target.value = "";
  }
});

$("import-file").addEventListener("change", async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const data = new FormData();
  data.append("file", file);
  data.append("template_id", currentId || "");
  const preview = await api("/api/import/preview", { method: "POST", body: data });
  pendingChanges = preview.changes;
  $("diff-summary").textContent = `${preview.imported_rows} rows imported, ${preview.changes.length} field changes, ${preview.unmatched} unmatched.`;
  $("diff-grid").querySelector("tbody").innerHTML = preview.changes
    .map(
      (c) =>
        `<tr><td>${c.pnpid}</td><td>${escapeHtml(c.tag)}</td><td>${escapeHtml(c.field)}</td><td>${escapeHtml(c.old)}</td><td>${escapeHtml(c.new)}</td></tr>`
    )
    .join("");
  $("diff-dialog").showModal();
});

$("diff-apply").addEventListener("click", async () => {
  const result = await api("/api/import/apply", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ changes: pendingChanges }),
  });
  $("diff-dialog").close();
  alert(`${result.applied} changes written to:\n${result.path}\n\n${result.note}`);
});

$("btn-header").addEventListener("click", () => {
  openHeaderDialog().catch((err) => alert(err.message));
});

$("header-apply").addEventListener("click", () => {
  applyHeaderToTemplate();
  renderTitleBlock(currentTemplate);
  const body = $("rev-table").querySelector("tbody");
  body.innerHTML = (currentTemplate.revision_table || [])
    .map(
      (r) =>
        `<tr><td>${escapeHtml(r.rev || "")}</td><td>${escapeHtml(r.date || "")}</td><td>${escapeHtml(r.desc || "")}</td><td>${escapeHtml(r.drawn || "")}</td></tr>`
    )
    .join("");
  $("header-dialog").close();
  persistCurrentTemplate().catch((err) => alert(err.message));
});

$("hdr-add-rev").addEventListener("click", () => {
  const tbody = $("rev-editor").querySelector("tbody");
  tbody.insertAdjacentHTML(
    "beforeend",
    `<tr>
      <td><input data-rev="rev" value="" /></td>
      <td><input data-rev="date" value="" /></td>
      <td><input data-rev="desc" value="" /></td>
      <td><input data-rev="drawn" value="" /></td>
    </tr>`
  );
});

boot().catch((err) => alert(err.message));
