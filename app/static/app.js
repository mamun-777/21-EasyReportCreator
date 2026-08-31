const i18n = {
  en: {
    lang: "Language",
    project: "Project",
    lists: "Lists",
    open: "Open",
    sample: "Open sample MN-P-RHN-PID-0001",
    empty: "No project open",
    header: "Header setup",
    columns: "Columns",
    logo: "Upload logo",
    save: "Save template",
    import: "Import Excel",
    export: "Export Excel",
    filter: "Filter rows…",
    select: "Select a list",
    sub: "Open a ProcessPower.dcf file, then choose a list.",
    foot: "Upload ProcessPower.dcf (SQLite). No AutoCAD licence required for listing.",
    uploading: "Uploading…",
    analyzing: "Analyzing project…",
    uploadFailed: "Upload failed.",
    errorOpenTitle: "Could not open file",
    errorOpenHint: "Choose ProcessPower.dcf from your AutoCAD Plant 3D project folder. It is usually in the same folder as Project.xml.",
    errorInvalidDcf: "This file is not a recognizable Plant 3D database.",
    errorInvalidSqlite: "The selected file is not a valid SQLite database.",
    errorGeneric: "Something went wrong. Please try again.",
    savedTemplate: "Template saved with header fields and revision table.",
    savedTemplateHint: "Select it from the list on the left.",
    columnsTitle: "Columns",
    columnsHelp: "Choose which columns appear in the preview and Excel export for this list.",
    saveTitle: "Save template",
    saveSummary: "Save the current header, revision table, and column layout as your company standard.",
    saveStandard: "Save as company standard",
    saveOverwrite: "Overwrite existing company standard",
    saveReset: "Reset list to factory default",
    stdBadge: "std",
    selectDcfOnly: "Please select a Plant 3D database file (.dcf).",
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
    columns: "Kolommen",
    logo: "Logo uploaden",
    save: "Sjabloon opslaan",
    import: "Excel importeren",
    export: "Excel exporteren",
    filter: "Filter rijen…",
    select: "Kies een lijst",
    sub: "Open een ProcessPower.dcf-bestand en kies daarna een lijst.",
    foot: "Upload ProcessPower.dcf (SQLite). Geen AutoCAD-licentie nodig voor lijsten.",
    uploading: "Uploaden…",
    analyzing: "Project analyseren…",
    uploadFailed: "Upload mislukt.",
    errorOpenTitle: "Bestand kan niet worden geopend",
    errorOpenHint: "Kies ProcessPower.dcf uit uw AutoCAD Plant 3D-projectmap. Het bestand staat meestal in dezelfde map als Project.xml.",
    errorInvalidDcf: "Dit bestand is geen herkenbare Plant 3D-database.",
    errorInvalidSqlite: "Het geselecteerde bestand is geen geldige SQLite-database.",
    errorGeneric: "Er is iets misgegaan. Probeer het opnieuw.",
    savedTemplate: "Sjabloon opgeslagen met koptekstvelden en revisietabel.",
    savedTemplateHint: "Selecteer het in de lijst links.",
    columnsTitle: "Kolommen",
    columnsHelp: "Kies welke kolommen in het voorbeeld en Excel-export verschijnen.",
    saveTitle: "Sjabloon opslaan",
    saveSummary: "Sla de huidige koptekst, revisietabel en kolomindeling op als bedrijfsstandaard.",
    saveStandard: "Opslaan als bedrijfsstandaard",
    saveOverwrite: "Bestaande bedrijfsstandaard overschrijven",
    saveReset: "Lijst terugzetten naar fabrieksstandaard",
    stdBadge: "std",
    selectDcfOnly: "Selecteer een Plant 3D-databasebestand (.dcf).",
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
let projectSession = 0;

const CATEGORY_LABELS = {
  standard: "Standard project fields",
  S88: "Custom properties (S88)",
  custom: "Other custom properties",
};

const $ = (id) => document.getElementById(id);

function normalizeDateValue(value) {
  if (!value) return "";
  const s = String(value).trim();
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
  const us = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (us) {
    return `${us[3]}-${us[1].padStart(2, "0")}-${us[2].padStart(2, "0")}`;
  }
  const eu = s.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})/);
  if (eu) {
    return `${eu[3]}-${eu[2].padStart(2, "0")}-${eu[1].padStart(2, "0")}`;
  }
  return s;
}

function destroyDatePicker(input) {
  if (input?._flatpickr) {
    clearDatePickerListeners(input._flatpickr);
    input._flatpickr.destroy();
  }
}

function positionFloatingCalendar(instance) {
  const cal = instance.calendarContainer;
  const anchor = instance.altInput || instance.input;
  if (!cal || !anchor) return;

  const rect = anchor.getBoundingClientRect();
  const calWidth = 252;
  const calHeight = cal.offsetHeight || 260;
  const gap = 4;
  const pad = 8;

  let top = rect.bottom + gap;
  let left = rect.left;

  if (top + calHeight > window.innerHeight - pad) {
    top = rect.top - calHeight - gap;
  }
  if (left + calWidth > window.innerWidth - pad) {
    left = window.innerWidth - calWidth - pad;
  }
  if (left < pad) left = pad;

  cal.style.position = "fixed";
  cal.style.top = `${Math.max(pad, top)}px`;
  cal.style.left = `${left}px`;
  cal.style.right = "auto";
  cal.style.width = `${calWidth}px`;
  cal.style.zIndex = "10000";
}

function bindDatePickerListeners(instance) {
  clearDatePickerListeners(instance);
  const reposition = () => positionFloatingCalendar(instance);
  instance._ercReposition = reposition;
  window.addEventListener("resize", reposition);
  instance._ercScrollParent = instance.input.closest(".header-grid, .rev-editor-wrap, .dialog-body");
  instance._ercScrollParent?.addEventListener("scroll", reposition, true);
}

function clearDatePickerListeners(instance) {
  const reposition = instance?._ercReposition;
  if (!reposition) return;
  window.removeEventListener("resize", reposition);
  instance._ercScrollParent?.removeEventListener("scroll", reposition, true);
  delete instance._ercReposition;
  delete instance._ercScrollParent;
}

function attachDatePicker(input, value) {
  if (!input || typeof flatpickr === "undefined") return null;
  destroyDatePicker(input);
  const normalized = normalizeDateValue(value ?? input.value);
  if (normalized) input.value = normalized;
  const dialog = input.closest("#header-dialog");
  const appendTarget = dialog?.querySelector(".dialog") || undefined;

  return flatpickr(input, {
    dateFormat: "Y-m-d",
    altInput: true,
    altFormat: "M j, Y",
    allowInput: true,
    disableMobile: true,
    appendTo: appendTarget,
    defaultDate: normalized || undefined,
    onOpen(_selectedDates, _dateStr, instance) {
      requestAnimationFrame(() => {
        positionFloatingCalendar(instance);
        bindDatePickerListeners(instance);
      });
    },
    onClose(_selectedDates, _dateStr, instance) {
      clearDatePickerListeners(instance);
    },
  });
}

function attachDatePickers(root = document) {
  root.querySelectorAll("input.date-input").forEach((input) => {
    attachDatePicker(input);
  });
}

function t(key) {
  return i18n[lang][key];
}

function applyLang() {
  $("lbl-project").textContent = t("project");
  $("lbl-lists").textContent = t("lists");
  $("btn-open").textContent = t("open");
  $("btn-header").textContent = t("header");
  $("btn-columns").textContent = t("columns");
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
    throw new Error(parseApiError(text) || res.statusText);
  }
  const type = res.headers.get("content-type") || "";
  if (type.includes("application/json")) return res.json();
  return res;
}

function parseApiError(input) {
  if (!input) return "";
  if (typeof input === "object") {
    if (typeof input.detail === "string") return input.detail;
    if (Array.isArray(input.detail)) {
      return input.detail.map((item) => item.msg || String(item)).join("\n");
    }
    return "";
  }
  try {
    const data = JSON.parse(input);
    return parseApiError(data);
  } catch {
    return String(input);
  }
}

function friendlyError(message) {
  const text = (message || "").trim();
  if (!text || text === "Internal Server Error") return t("errorGeneric");
  const lower = text.toLowerCase();
  if (lower.includes("not a valid sqlite") || lower.includes("not a valid database")) {
    return t("errorInvalidSqlite");
  }
  if (
    lower.includes("not a recognizable") ||
    lower.includes("not look like a plant 3d") ||
    lower.includes("no plant 3d project") ||
    lower.includes("no .dcf file")
  ) {
    return t("errorInvalidDcf");
  }
  if (lower.includes("please select a plant 3d database")) {
    return text;
  }
  return text;
}

let noticeTimer = null;

function hideNotice() {
  $("notice").classList.add("hidden");
  if (noticeTimer) {
    clearTimeout(noticeTimer);
    noticeTimer = null;
  }
}

function showNotice(type, title, message, { autoHideMs = 6000 } = {}) {
  const el = $("notice");
  el.className = `notice notice-${type}`;
  $("notice-title").textContent = title;
  $("notice-message").textContent = message;
  el.classList.remove("hidden");
  if (noticeTimer) clearTimeout(noticeTimer);
  if (autoHideMs > 0) {
    noticeTimer = setTimeout(hideNotice, autoHideMs);
  }
}

function showSuccessNotice(title, message) {
  showNotice("success", title, message);
}

function showErrorDialog(message, hint = "") {
  $("error-title").textContent = t("errorOpenTitle");
  $("error-message").textContent = friendlyError(message);
  $("error-hint").textContent = hint || t("errorOpenHint");
  $("error-dialog").showModal();
}

function notifyError(err, hint) {
  showErrorDialog(err?.message || t("errorGeneric"), hint);
}

function beginProjectSession() {
  projectSession += 1;
  return projectSession;
}

function isActiveSession(session) {
  return session === projectSession;
}

function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes <= 0) return "";
  const units = ["B", "KB", "MB", "GB"];
  let value = bytes;
  let unit = 0;
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024;
    unit += 1;
  }
  return `${value.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

function setUploadBusy(busy) {
  const row = document.querySelector(".open-row");
  const status = $("upload-status");
  const openBtn = $("btn-open");
  row?.classList.toggle("is-busy", busy);
  status.classList.toggle("hidden", !busy);
  status.setAttribute("aria-busy", busy ? "true" : "false");
  openBtn.disabled = busy;
}

function setUploadProgress(percent, phase, metaText = "") {
  const track = document.querySelector(".progress-track");
  const fill = $("upload-progress-fill");
  const label = $("upload-status-label");
  const meta = $("upload-status-meta");
  label.textContent = phase === "analyzing" ? t("analyzing") : t("uploading");
  meta.textContent = metaText;
  if (phase === "analyzing" || percent === null) {
    track.classList.add("indeterminate");
    track.setAttribute("aria-valuenow", "0");
    fill.style.width = "";
    return;
  }
  track.classList.remove("indeterminate");
  const pct = Math.max(0, Math.min(100, Math.round(percent * 100)));
  fill.style.width = `${pct}%`;
  track.setAttribute("aria-valuenow", String(pct));
}

function hideUploadProgress() {
  setUploadBusy(false);
  const track = document.querySelector(".progress-track");
  const openBtn = $("btn-open");
  track?.classList.remove("indeterminate");
  $("upload-progress-fill").style.width = "0%";
  $("upload-status-meta").textContent = "";
  if (openBtn) openBtn.disabled = false;
}

function projectFileLabel(project) {
  if (!project) return "";
  if (project.uploaded_filename) return project.uploaded_filename;
  if (project.dcf_path) return project.dcf_path.split(/[/\\]/).pop();
  return "";
}

function uploadProjectFile(file, onPhase) {
  return new Promise((resolve, reject) => {
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "/api/upload-project");
    xhr.timeout = 10 * 60 * 1000;

    xhr.upload.addEventListener("progress", (event) => {
      if (!event.lengthComputable) {
        onPhase?.("uploading", null, formatBytes(event.loaded));
        return;
      }
      const ratio = event.loaded / event.total;
      const meta = `${formatBytes(event.loaded)} / ${formatBytes(event.total)}`;
      if (ratio >= 1) {
        onPhase?.("analyzing", null, meta);
      } else {
        onPhase?.("uploading", ratio, meta);
      }
    });

    xhr.addEventListener("load", () => {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const data = xhr.responseText ? JSON.parse(xhr.responseText) : xhr.response;
          if (!data?.ok || !data.project) {
            reject(new Error("Invalid server response."));
            return;
          }
          resolve(data);
        } catch (err) {
          reject(new Error("Could not read server response."));
        }
        return;
      }
      let parsed = null;
      try {
        parsed = xhr.responseText ? JSON.parse(xhr.responseText) : xhr.response;
      } catch {
        parsed = xhr.responseText;
      }
      const message =
        parseApiError(parsed) ||
        parseApiError(xhr.responseText) ||
        xhr.statusText ||
        t("uploadFailed");
      reject(new Error(message));
    });

    xhr.addEventListener("error", () => reject(new Error(t("uploadFailed"))));
    xhr.addEventListener("abort", () => reject(new Error("Upload cancelled.")));
    xhr.addEventListener("timeout", () => reject(new Error("Upload timed out. Try again.")));

    const data = new FormData();
    data.append("file", file, file.name);
    onPhase?.("uploading", 0, formatBytes(file.size));
    xhr.send(data);
  });
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
    $("project-file-label").value = "";
    return;
  }
  $("project-file-label").value = projectFileLabel(p);
  const sourceNote =
    p.source === "upload"
      ? `<div class="muted">Uploaded database</div>`
      : `<div class="muted">${escapeHtml(p.project_dir || "")}</div>`;
  card.innerHTML = `
    <h3>${escapeHtml(p.name || "—")}</h3>
    <div>${escapeHtml(p.description || "")}</div>
    <div class="muted">${escapeHtml(p.standard || "")} · ${escapeHtml(p.status || "")}</div>
    <div class="muted">${escapeHtml(p.location || "")} ${p.location_code ? "(" + escapeHtml(p.location_code) + ")" : ""}</div>
    ${sourceNote}
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

function baseTemplateId(id = currentId) {
  return (id || "").replace(/_standard$/, "");
}

function standardTemplateId(id = currentId) {
  return `${baseTemplateId(id)}_standard`;
}

function renderTemplates() {
  const nav = $("template-nav");
  nav.innerHTML = templates
    .map((tpl) => {
      const label = lang === "nl" ? tpl.name_nl : tpl.name;
      const active = tpl.id === baseTemplateId(currentId) ? "active" : "";
      const badge = tpl.has_standard
        ? `<span class="std-badge">${escapeHtml(t("stdBadge"))}</span>`
        : "";
      return `<button type="button" class="${active}" data-id="${tpl.id}">${escapeHtml(label)}${badge}</button>`;
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

async function loadReport(id, session = projectSession) {
  if (!isActiveSession(session)) return;
  await ensureTemplates();
  if (!isActiveSession(session)) return;
  const payload = await api(`/api/report/${id}`);
  if (!isActiveSession(session)) return;
  currentId = payload.resolved_id || id;
  renderTemplates();
  document.querySelectorAll(".stat").forEach((el) => {
    el.classList.toggle("active", el.dataset.template === baseTemplateId(id));
  });
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
  tbody.querySelectorAll("input.date-input").forEach(destroyDatePicker);
  tbody.innerHTML = (rows || [])
    .map(
      (row) => `<tr>
        <td><input data-rev="rev" value="${escapeHtml(row.rev || "")}" /></td>
        <td><input data-rev="date" class="date-input" type="text" value="${escapeHtml(normalizeDateValue(row.date || ""))}" autocomplete="off" /></td>
        <td><input data-rev="desc" value="${escapeHtml(row.desc || "")}" /></td>
        <td><input data-rev="drawn" value="${escapeHtml(row.drawn || "")}" /></td>
      </tr>`
    )
    .join("");
  attachDatePickers(tbody);
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
  const dateInput = $("hdr-date");
  destroyDatePicker(dateInput);
  dateInput.value = normalizeDateValue(header.date || "");
  $("hdr-company").value = header.company || "";
  renderHeaderFieldGroups();
  const revisions = currentTemplate.revision_table?.length
    ? currentTemplate.revision_table
    : projectDetails.revisions || [];
  renderRevisionEditor(revisions);
  attachDatePicker(dateInput);
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

async function persistCurrentTemplate(template = currentTemplate, { overwrite = true } = {}) {
  if (!template?.id) return;
  await api("/api/templates", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ template, overwrite }),
  });
}

function columnCatalogueFromTemplate() {
  const byKey = new Map((currentTemplate?.columns || []).map((col) => [col.key, { ...col }]));
  const sample = currentRows[0] || {};
  for (const key of Object.keys(sample)) {
    if (key === "PnPID" || byKey.has(key)) continue;
    byKey.set(key, { key, header: key, width: 16, visible: false });
  }
  return [...byKey.values()];
}

function renderColumnChecks() {
  const container = $("column-checks");
  const cols = columnCatalogueFromTemplate();
  container.innerHTML = cols
    .map(
      (col) => `<label class="field-check">
        <input type="checkbox" data-key="${escapeHtml(col.key)}" data-header="${escapeHtml(col.header || col.key)}" ${col.visible !== false ? "checked" : ""} />
        <span>${escapeHtml(col.header || col.key)}<small>${escapeHtml(col.key)}</small></span>
      </label>`
    )
    .join("");
}

function openColumnsDialog() {
  if (!currentTemplate) return;
  $("columns-title").textContent = t("columnsTitle");
  $("columns-help").textContent = t("columnsHelp");
  renderColumnChecks();
  $("columns-dialog").showModal();
}

function applyColumnsToTemplate() {
  if (!currentTemplate) return;
  const selected = [...$("column-checks").querySelectorAll("input[type=checkbox]")];
  const columns = selected.map((input) => ({
    key: input.dataset.key,
    header: input.dataset.header || input.dataset.key,
    width: 16,
    visible: input.checked,
  }));
  const visibleKeys = columns.filter((c) => c.visible).map((c) => c.key);
  currentTemplate.columns = columns;
  if (!currentTemplate.sort?.length || currentTemplate.sort.every((k) => !visibleKeys.includes(k))) {
    currentTemplate.sort = visibleKeys.includes("Tag") ? ["Tag"] : visibleKeys.slice(0, 1);
  }
  renderGrid(currentRows);
}

function openSaveDialog() {
  if (!currentTemplate) return;
  const stdId = standardTemplateId();
  $("save-title").textContent = t("saveTitle");
  $("save-summary").textContent = t("saveSummary");
  $("save-standard-id").textContent = stdId;
  const tpl = templates.find((item) => item.id === baseTemplateId());
  const overwriteRadio = document.querySelector('input[name="save-mode"][value="overwrite"]');
  if (overwriteRadio) {
    overwriteRadio.disabled = !tpl?.has_standard;
    if (!tpl?.has_standard) overwriteRadio.checked = false;
  }
  document.querySelector('input[name="save-mode"][value="standard"]').checked = !tpl?.has_standard;
  $("save-dialog").showModal();
}

async function saveCompanyStandard({ overwrite = false } = {}) {
  const copy = structuredClone(currentTemplate);
  const stdId = standardTemplateId();
  copy.id = stdId;
  if (!copy.name.includes("(company standard)")) {
    copy.name = `${copy.name.replace(/ \(company standard\)$/, "")} (company standard)`;
  }
  if (!copy.name_nl.includes("(bedrijfsstandaard)")) {
    copy.name_nl = `${copy.name_nl.replace(/ \(bedrijfsstandaard\)$/, "")} (bedrijfsstandaard)`;
  }
  await persistCurrentTemplate(copy, { overwrite });
  templates = await api("/api/templates");
  await loadReport(baseTemplateId());
  showSuccessNotice(t("savedTemplate"), t("savedTemplateHint"));
}

async function resetToFactoryDefault() {
  const baseId = baseTemplateId();
  const factory = await api(`/api/templates/${baseId}?variant=base`);
  currentTemplate = structuredClone(factory);
  await loadReport(baseId);
  showSuccessNotice("Factory default restored", baseId);
}

async function applyProjectResult(project, session = projectSession) {
  if (!isActiveSession(session) || !project) return;
  projectDetails = null;
  renderProject(project);
  await ensureProjectDetails();
  if (!isActiveSession(session)) return;
  await ensureTemplates();
  if (!isActiveSession(session)) return;
  await loadReport(currentId || "valve_list", session);
}

async function openProject(path, session = projectSession) {
  const result = await api("/api/open-project", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ path }),
  });
  if (!isActiveSession(session)) return;
  await applyProjectResult(result.project, session);
}

async function openUploadedProject(file) {
  const session = beginProjectSession();
  setUploadBusy(true);
  try {
    const result = await uploadProjectFile(file, (phase, percent, meta) => {
      setUploadProgress(percent, phase, meta);
    });
    hideUploadProgress();
    await applyProjectResult(result.project, session);
  } catch (err) {
    hideUploadProgress();
    throw err;
  }
}

async function boot() {
  applyLang();
  await refreshLogo();
  await ensureTemplates();
  const session = projectSession;
  try {
    const sample = await api("/api/sample-path");
    if (!isActiveSession(session)) return;
    if (sample.exists) {
      await openProject(sample.path, session);
    } else {
      $("project-card").innerHTML = `<p class="muted">${t("empty")}</p>`;
    }
  } catch (err) {
    if (!isActiveSession(session)) return;
    $("project-card").innerHTML = `<p class="muted">${escapeHtml(err.message)}</p>`;
  }
}

$("template-nav").addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-id]");
  if (btn) loadReport(btn.dataset.id);
});

$("btn-open").addEventListener("click", () => {
  if ($("btn-open").disabled) return;
  $("project-file").click();
});

$("project-file").addEventListener("change", async (e) => {
  const file = e.target.files?.[0];
  e.target.value = "";
  if (!file) return;
  hideNotice();
  if (!file.name.toLowerCase().endsWith(".dcf")) {
    showErrorDialog(t("selectDcfOnly"));
    return;
  }
  try {
    await openUploadedProject(file);
    showSuccessNotice("Project opened", file.name);
  } catch (err) {
    notifyError(err);
  }
});

$("notice-close").addEventListener("click", hideNotice);

$("stats").addEventListener("click", (e) => {
  const card = e.target.closest("[data-template]");
  if (card) loadReport(card.dataset.template).catch((err) => notifyError(err));
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

$("btn-save-tpl").addEventListener("click", () => {
  if (!currentTemplate) return;
  openSaveDialog();
});

$("save-confirm").addEventListener("click", async () => {
  const mode = document.querySelector('input[name="save-mode"]:checked')?.value || "standard";
  $("save-dialog").close();
  try {
    if (mode === "reset") {
      await resetToFactoryDefault();
      return;
    }
    await saveCompanyStandard({ overwrite: mode === "overwrite" });
  } catch (err) {
    notifyError(err);
  }
});

$("btn-columns").addEventListener("click", () => openColumnsDialog());

$("columns-apply").addEventListener("click", () => {
  applyColumnsToTemplate();
  $("columns-dialog").close();
  persistCurrentTemplate(currentTemplate, { overwrite: true }).catch((err) => notifyError(err));
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
    notifyError(err);
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
  showSuccessNotice(
    "Import applied",
    `${result.applied} changes written to demo copy.\n${result.path || ""}`
  );
});

$("btn-header").addEventListener("click", () => {
  openHeaderDialog().catch((err) => notifyError(err));
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
  persistCurrentTemplate(currentTemplate, { overwrite: true }).catch((err) => notifyError(err));
});

$("hdr-add-rev").addEventListener("click", () => {
  const tbody = $("rev-editor").querySelector("tbody");
  tbody.insertAdjacentHTML(
    "beforeend",
    `<tr>
      <td><input data-rev="rev" value="" /></td>
      <td><input data-rev="date" class="date-input" type="text" value="" autocomplete="off" /></td>
      <td><input data-rev="desc" value="" /></td>
      <td><input data-rev="drawn" value="" /></td>
    </tr>`
  );
  attachDatePicker(tbody.lastElementChild.querySelector('input[data-rev="date"]'));
});

boot().catch((err) => notifyError(err));
