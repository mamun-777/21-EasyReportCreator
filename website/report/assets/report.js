const $ = (id) => document.getElementById(id);

const TEMPLATE_COUNT_KEY = {
  drawing_list: "drawings",
  equipment_list: "equipment",
  valve_list: "hand_valves",
  control_valve_list: "control_valves",
  instrument_list: "instruments",
  line_list: "pipe_lines",
  line_summary: "line_groups",
  component_list: "components",
};

const DEFAULT_HEADER_KEYS = [
  "Project_Name",
  "Project_Description",
  "Project_Number",
  "S88_Projectstatus",
  "S88_Locatie",
];

let templates = [];
let currentId = null;
let currentTemplate = null;
let currentRows = [];
let project = null;
let projectDetails = null;
let companyProfile = null;
let currentUser = null;
let projectSession = 0;
let noticeTimer = null;

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

function baseTemplateId(id = currentId) {
  return String(id || "").replace(/_standard$/, "");
}

function standardTemplateId(id = currentId) {
  return `${baseTemplateId(id)}_standard`;
}

function beginProjectSession() {
  projectSession += 1;
  return projectSession;
}

function isActiveSession(session) {
  return session === projectSession;
}

function hideNotice() {
  $("notice")?.classList.add("hidden");
  if (noticeTimer) {
    clearTimeout(noticeTimer);
    noticeTimer = null;
  }
}

function showNotice(type, title, message, { autoHideMs = 7000 } = {}) {
  const el = $("notice");
  if (!el) return;
  el.className = `notice notice-${type === "success" ? "success" : type === "error" ? "info" : "info"}`;
  if (type === "error") el.classList.add("notice-error");
  $("notice-title").textContent = title;
  $("notice-message").textContent = message;
  el.classList.remove("hidden");
  if (noticeTimer) clearTimeout(noticeTimer);
  if (autoHideMs > 0) {
    noticeTimer = setTimeout(hideNotice, autoHideMs);
  }
}

function showSuccess(title, message) {
  showNotice("success", title, message);
}

function showError(title, message) {
  showNotice("error", title, message, { autoHideMs: 10000 });
}

function showErrorDialog(title, message) {
  $("dialog-notice-title").textContent = title;
  $("dialog-notice-message").textContent = message;
  $("notice-dialog").showModal();
}

async function api(action, options = {}) {
  const url = `api.php?action=${encodeURIComponent(action)}${options.query || ""}`;
  const res = await fetch(url, options.fetch || undefined);
  if (res.status === 401) {
    location.href = "login.php";
    throw new Error("Please sign in to continue.");
  }
  if (options.blob) {
    if (!res.ok) {
      let detail = res.statusText;
      try {
        const data = await res.json();
        detail = data.detail || detail;
      } catch {
        /* ignore */
      }
      throw new Error(detail || "Request failed");
    }
    return res.blob();
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.detail || res.statusText || "Request failed");
  }
  return data;
}

function setCompanyActionsEnabled(enabled) {
  // Export needs a loaded project; header/logo/save are company-level (boot enables those).
  const exportBtn = $("btn-export");
  if (exportBtn) exportBtn.disabled = !enabled;
  if (enabled) {
    $("btn-header").disabled = false;
    $("btn-save").disabled = false;
    $("logo-file").disabled = false;
    $("logo-label").classList.remove("disabled");
  }
}

function setListActionsEnabled(enabled) {
  $("btn-columns").disabled = !enabled;
}

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
  const probe = await fetch(`api.php?action=logo&t=${Date.now()}`);
  if (probe.ok) {
    showLogo(`api.php?action=logo&t=${Date.now()}`);
  } else {
    showLogo(null);
  }
}

function countForTemplate(templateId, counts) {
  const key = TEMPLATE_COUNT_KEY[baseTemplateId(templateId)];
  if (!key || !counts) return null;
  const n = counts[key];
  return typeof n === "number" ? n : null;
}

function updateEmptyState() {
  const empty = $("empty-state");
  const grid = $("grid");
  const sheet = document.querySelector(".sheet");
  const hasPreview = Boolean(project && currentTemplate);
  if (sheet) sheet.classList.toggle("has-preview", hasPreview);
  if (!empty || !grid) return;
  if (!project) {
    empty.hidden = false;
    grid.hidden = true;
    empty.querySelector(".empty-title").textContent = "Start with your project database";
    empty.querySelector(".empty-copy").textContent =
      "Upload ProcessPower.dcf, pick a list, adjust columns if needed, then export Excel.";
    $("empty-upload-btn").hidden = false;
    $("empty-upload-btn").textContent = "Upload .dcf";
    return;
  }
  if (!currentTemplate) {
    empty.hidden = false;
    grid.hidden = true;
    empty.querySelector(".empty-title").textContent = "Choose a list";
    empty.querySelector(".empty-copy").textContent =
      "Select a list in the sidebar to preview rows, then set columns or export.";
    $("empty-upload-btn").hidden = true;
    return;
  }
  empty.hidden = true;
  grid.hidden = false;
}

function updateChrome() {
  updateEmptyState();
}

function renderTemplates() {
  const nav = $("template-nav");
  const activeBase = baseTemplateId(currentId);
  const counts = project?.counts || {};
  const hasProject = Boolean(project);
  nav.innerHTML = templates
    .map((t) => {
      const active = t.id === activeBase ? "active" : "";
      const badge = t.has_standard ? '<span class="badge">std</span>' : "";
      const count = countForTemplate(t.id, counts);
      const countHtml =
        count === null
          ? ""
          : `<span class="nav-count" title="Items in project">${count.toLocaleString("en-GB")}</span>`;
      const disabled = hasProject ? "" : "disabled";
      return `<button type="button" class="nav-item ${active}" data-id="${escapeHtml(t.id)}" ${disabled}>
        <span class="nav-row">
          <span class="name">${escapeHtml(t.name)}${badge}</span>
          ${countHtml}
        </span>
        <span class="desc">${escapeHtml(t.description || "")}</span>
      </button>`;
    })
    .join("");
  nav.querySelectorAll(".nav-item").forEach((btn) => {
    btn.addEventListener("click", () => {
      loadReport(btn.dataset.id).catch((err) => showError("Could not load list", err.message));
    });
  });
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
  if (header.company) {
    parts.push(`<div><dt>Company</dt><dd>${escapeHtml(header.company)}</dd></div>`);
  }
  $("tb-fields").innerHTML = parts.join("");
}

function renderRevisionTable(template) {
  const body = $("rev-table").querySelector("tbody");
  body.innerHTML = (template?.revision_table || [])
    .map(
      (r) =>
        `<tr><td>${escapeHtml(r.rev || "")}</td><td>${escapeHtml(r.date || "")}</td><td>${escapeHtml(r.desc || "")}</td><td>${escapeHtml(r.drawn || "")}</td></tr>`
    )
    .join("");
}

function visibleColumns() {
  return (currentTemplate?.columns || []).filter((c) => c.visible !== false);
}

function headerFor(col) {
  return col.header_en || col.header || col.key;
}

function renderGrid(filter = "") {
  const cols = visibleColumns();
  const thead = $("grid").querySelector("thead");
  const tbody = $("grid").querySelector("tbody");
  thead.innerHTML = `<tr>${cols.map((c) => `<th>${escapeHtml(headerFor(c))}</th>`).join("")}</tr>`;
  const q = filter.trim().toLowerCase();
  const rows = !q
    ? currentRows
    : currentRows.filter((row) => cols.some((c) => String(row[c.key] ?? "").toLowerCase().includes(q)));
  tbody.innerHTML = rows
    .map(
      (row) =>
        `<tr>${cols
          .map((c) => {
            const val = String(row[c.key] ?? "");
            return `<td title="${escapeHtml(val)}">${escapeHtml(val)}</td>`;
          })
          .join("")}</tr>`
    )
    .join("");
  $("row-count").textContent = `${rows.length} / ${currentRows.length}`;
  updateEmptyState();
}

function renderProjectMeta(proj, filename) {
  const card = $("project-card");
  if (!card) return;
  if (!proj) {
    card.classList.add("is-empty");
    card.innerHTML = `<p class="muted" id="project-meta">No project open. Upload ProcessPower.dcf from your Plant 3D project folder. Plant 3D does not need to be running.</p>`;
    updateChrome();
    return;
  }
  card.classList.remove("is-empty");
  const title = proj.name || proj.number || "Uploaded project";
  const desc = proj.description || proj.number || "";
  card.innerHTML = `<h3>${escapeHtml(title)}</h3>
    ${desc ? `<span class="project-desc">${escapeHtml(desc)}</span>` : ""}
    <span class="project-file">${escapeHtml(filename || "ProcessPower.dcf")}</span>`;
  updateChrome();
}

async function persistWorkingTemplate(template = currentTemplate) {
  if (!template?.id) return;
  await api("apply_template", {
    fetch: {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ template }),
    },
  });
}

async function ensureProjectDetails() {
  if (!projectDetails) {
    projectDetails = await api("details");
  }
  return projectDetails;
}

function selectedHeaderKeys() {
  const fields = companyProfile?.header?.fields || currentTemplate?.header?.fields || [];
  if (fields.length) {
    return new Set(fields.map((f) => f.key));
  }
  return new Set(DEFAULT_HEADER_KEYS);
}

function renderHeaderFieldGroups() {
  const container = $("header-field-groups");
  if (!projectDetails?.catalogue?.length) {
    container.innerHTML = `<p class="muted">Upload a project first so Project Details values can be previewed.</p>`;
    return;
  }
  const selected = selectedHeaderKeys();
  const groups = {};
  for (const item of projectDetails.catalogue) {
    groups[item.category] = groups[item.category] || [];
    groups[item.category].push(item);
  }
  container.innerHTML = Object.entries(groups)
    .map(([, items]) => {
      const title = items[0]?.category_label || "Fields";
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
        <td><input data-rev="date" type="date" value="${escapeHtml(normalizeDateValue(row.date || ""))}" /></td>
        <td><input data-rev="desc" value="${escapeHtml(row.desc || "")}" /></td>
        <td><input data-rev="drawn" value="${escapeHtml(row.drawn || "")}" /></td>
      </tr>`
    )
    .join("");
}

function readRevisionEditor() {
  return [...$("rev-editor").querySelectorAll("tbody tr")]
    .map((tr) => {
      const item = {};
      tr.querySelectorAll("input[data-rev]").forEach((input) => {
        item[input.dataset.rev] = input.value.trim();
      });
      return item;
    })
    .filter((row) => row.rev || row.date || row.desc || row.drawn);
}

function normalizeDateValue(value) {
  if (!value) return "";
  const s = String(value).trim();
  if (/^\d{4}-\d{2}-\d{2}/.test(s)) return s.slice(0, 10);
  const us = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})/);
  if (us) return `${us[3]}-${us[1].padStart(2, "0")}-${us[2].padStart(2, "0")}`;
  const eu = s.match(/^(\d{1,2})[./-](\d{1,2})[./-](\d{4})/);
  if (eu) return `${eu[3]}-${eu[2].padStart(2, "0")}-${eu[1].padStart(2, "0")}`;
  return "";
}

async function openHeaderDialog() {
  if (!project) {
    showError("Upload a project", "Upload ProcessPower.dcf before editing the company header.");
    return;
  }
  await ensureProjectDetails();
  const profileHeader = companyProfile?.header || {};
  const listHeader = currentTemplate?.header || {};
  $("hdr-company").value = profileHeader.company || listHeader.company || currentUser?.company_name || "";
  $("hdr-title").value = listHeader.title || profileHeader.title || "";
  $("hdr-doc").value = listHeader.document_number || "";
  $("hdr-rev").value = listHeader.revision || profileHeader.revision || "";
  $("hdr-date").value = normalizeDateValue(listHeader.date || profileHeader.date || "");
  renderHeaderFieldGroups();
  const revisions =
    currentTemplate?.revision_table?.length
      ? currentTemplate.revision_table
      : profileHeader.revision_table?.length
        ? profileHeader.revision_table
        : projectDetails.revisions || [];
  renderRevisionEditor(revisions);
  $("header-dialog").showModal();
}

async function applyHeaderToAllLists() {
  const fields = [...$("header-field-groups").querySelectorAll("input[type=checkbox]:checked")].map((input) => ({
    key: input.dataset.key,
    label: input.dataset.label,
  }));
  for (const field of fields) {
    field.value = projectDetails?.values?.[field.key] || "";
  }
  const revisionTable = readRevisionEditor();
  const company = $("hdr-company").value.trim();
  const payload = {
    company_name: company || currentUser?.company_name || "",
    header: {
      company,
      fields,
      revision_table: revisionTable,
      title: $("hdr-title").value.trim(),
      revision: $("hdr-rev").value.trim(),
      date: $("hdr-date").value,
      document_number: $("hdr-doc").value.trim(),
    },
  };
  const data = await api("profile", {
    fetch: {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload),
    },
  });
  companyProfile = data.profile;
  if (currentTemplate) {
    currentTemplate.header = {
      ...(currentTemplate.header || {}),
      company: payload.header.company,
      fields: payload.header.fields,
      revision: payload.header.revision || currentTemplate.header?.revision,
      date: payload.header.date || currentTemplate.header?.date,
    };
    if (payload.header.title) currentTemplate.header.title = payload.header.title;
    if (payload.header.document_number) currentTemplate.header.document_number = payload.header.document_number;
    currentTemplate.revision_table = revisionTable;
    await persistWorkingTemplate(currentTemplate);
    renderTitleBlock(currentTemplate);
    renderRevisionTable(currentTemplate);
  }
  if (company) {
    $("company-chip").textContent = company;
  }
  showSuccess("Header applied", "Company header defaults now apply to every list.");
}

function columnCatalogueFromTemplate(extraProps = []) {
  const byKey = new Map((currentTemplate?.columns || []).map((col) => [col.key, { ...col }]));
  const sample = currentRows[0] || {};
  for (const key of Object.keys(sample)) {
    if (key === "PnPID" || byKey.has(key)) continue;
    byKey.set(key, { key, header: key, width: 16, visible: false, from_row: true });
  }
  for (const prop of extraProps) {
    const key = prop.key;
    if (!key || key === "PnPID") continue;
    if (!byKey.has(key)) {
      byKey.set(key, {
        key,
        header: prop.label || prop.header || key,
        header_en: prop.label || key,
        width: 16,
        visible: false,
        from_catalogue: true,
      });
    } else {
      const cur = byKey.get(key);
      if (!cur.header || cur.header === key) {
        cur.header = prop.label || cur.header;
        cur.header_en = prop.label || cur.header_en;
      }
      cur.from_catalogue = true;
      byKey.set(key, cur);
    }
  }
  return [...byKey.values()].sort((a, b) =>
    String(a.header || a.key).localeCompare(String(b.header || b.key), undefined, { sensitivity: "base" })
  );
}

function renderColumnChecks(extraProps = []) {
  const container = $("column-checks");
  const cols = columnCatalogueFromTemplate(extraProps);
  const catNote = extraProps.length
    ? `<p class="muted" style="margin:0 0 .75rem">Project catalogue: <strong>${extraProps.length}</strong> properties available for this list.</p>`
    : "";
  container.innerHTML =
    catNote +
    cols
      .map(
        (col) => `<label class="field-check">
        <input type="checkbox" data-key="${escapeHtml(col.key)}" data-header="${escapeHtml(col.header || col.key)}" ${col.visible !== false ? "checked" : ""} />
        <span>${escapeHtml(col.header_en || col.header || col.key)}<small>${escapeHtml(col.key)}${col.from_catalogue ? " · catalogue" : ""}</small></span>
      </label>`
      )
      .join("");
}

async function openColumnsDialog() {
  if (!currentTemplate) return;
  renderColumnChecks();
  $("columns-dialog").showModal();
  try {
    const source = currentTemplate.source || "";
    const tid = currentTemplate.id || "";
    const query = source
      ? `&source=${encodeURIComponent(source)}`
      : `&template_id=${encodeURIComponent(tid)}`;
    const cat = await api("property_catalogue", { query });
    const props = cat.properties || [];
    if (currentTemplate) {
      currentTemplate.catalogue_count = cat.count;
      currentTemplate.catalogue_class = cat.class_name;
    }
    renderColumnChecks(props);
  } catch (err) {
    showError("Property catalogue", err.message || String(err));
  }
}

function applyColumnsToTemplate() {
  if (!currentTemplate) return;
  const selected = [...$("column-checks").querySelectorAll("input[type=checkbox]")];
  const columns = selected.map((input) => {
    const existing = (currentTemplate.columns || []).find((c) => c.key === input.dataset.key) || {};
    return {
      ...existing,
      key: input.dataset.key,
      header: existing.header || input.dataset.header || input.dataset.key,
      width: existing.width || 16,
      visible: input.checked,
    };
  });
  const visibleKeys = columns.filter((c) => c.visible).map((c) => c.key);
  currentTemplate.columns = columns;
  if (!currentTemplate.sort?.length || currentTemplate.sort.every((k) => !visibleKeys.includes(k))) {
    currentTemplate.sort = visibleKeys.includes("Tag") ? ["Tag"] : visibleKeys.slice(0, 1);
  }
}

function openSaveDialog() {
  if (!currentTemplate && !$("save-company-header")?.checked) {
    showError("Nothing to save", "Open a list or edit the company header first.");
    return;
  }
  $("save-standard-id").textContent = currentId ? `(${standardTemplateId()})` : "";
  const tpl = templates.find((item) => item.id === baseTemplateId());
  const overwriteRadio = document.querySelector('input[name="save-mode"][value="overwrite"]');
  if (overwriteRadio) overwriteRadio.disabled = !tpl?.has_standard;
  if (tpl?.has_standard && overwriteRadio) {
    overwriteRadio.checked = true;
  } else {
    document.querySelector('input[name="save-mode"][value="standard"]').checked = true;
  }
  $("save-dialog").showModal();
}

async function saveCompanyHeaderOnly() {
  if (!companyProfile) {
    const data = await api("profile");
    companyProfile = data.profile;
  }
  if (currentTemplate?.header) {
    await api("profile", {
      fetch: {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          company_name: currentTemplate.header.company || companyProfile.company_name,
          header: {
            company: currentTemplate.header.company || "",
            fields: currentTemplate.header.fields || companyProfile.header.fields,
            revision_table: currentTemplate.revision_table || companyProfile.header.revision_table || [],
          },
        }),
      },
    }).then((data) => {
      companyProfile = data.profile;
    });
  }
  showSuccess("Company profile saved", "Header defaults apply to all lists.");
}

async function saveCompanyStandard({ overwrite = false } = {}) {
  if (!currentTemplate) {
    await saveCompanyHeaderOnly();
    return;
  }
  const copy = structuredClone(currentTemplate);
  await api("save_template", {
    fetch: {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        mode: overwrite ? "overwrite" : "standard",
        base_id: baseTemplateId(),
        template: copy,
        overwrite,
        save_company_header: $("save-company-header").checked,
      }),
    },
  });
  const data = await api("templates");
  templates = data.templates || [];
  const profile = await api("profile");
  companyProfile = profile.profile;
  await loadReport(baseTemplateId());
  showSuccess("Profile saved", "Company header defaults and this list standard are stored for your account.");
}

async function resetToFactoryDefault() {
  const baseId = baseTemplateId();
  await api("save_template", {
    fetch: {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ mode: "reset", base_id: baseId }),
    },
  });
  const data = await api("templates");
  templates = data.templates || [];
  await loadReport(baseId);
  showSuccess("Factory default restored", baseId);
}

function openExportDialog() {
  if (!currentId || !project) {
    showError("Nothing to export", "Upload a project and open a list first.");
    return;
  }
  const exp = companyProfile?.export || {};
  $("exp-logo").checked = exp.include_logo !== false;
  $("exp-revision").checked = exp.include_revision !== false;
  $("exp-pnpid").checked = exp.include_pnpid !== false;
  $("exp-remember").checked = exp.remember !== false;
  $("export-dialog").showModal();
}

async function confirmExport() {
  const exportOpts = {
    include_logo: $("exp-logo").checked,
    include_revision: $("exp-revision").checked,
    include_pnpid: $("exp-pnpid").checked,
    remember: $("exp-remember").checked,
  };
  if (exportOpts.remember) {
    const data = await api("profile", {
      fetch: {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ export: exportOpts }),
      },
    });
    companyProfile = data.profile;
  }
  const q = `&template_id=${encodeURIComponent(currentId)}&include_logo=${exportOpts.include_logo ? 1 : 0}&include_revision=${exportOpts.include_revision ? 1 : 0}&include_pnpid=${exportOpts.include_pnpid ? 1 : 0}`;
  const blob = await api("export", { query: q, blob: true });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${(project?.number || "project")}_${baseTemplateId()}.xlsx`;
  a.click();
  URL.revokeObjectURL(url);
  showSuccess("Excel exported", a.download);
}

async function loadReport(templateId, session = projectSession) {
  if (!project) {
    showError("No project", "Upload ProcessPower.dcf first.");
    return;
  }
  if (!isActiveSession(session)) return;
  const data = await api("report", { query: `&template_id=${encodeURIComponent(templateId)}` });
  if (!isActiveSession(session)) return;
  currentId = data.resolved_id || templateId;
  currentTemplate = data.template;
  currentRows = data.rows || [];
  project = data.project;
  if (data.profile) companyProfile = data.profile;
  if (data.has_logo) {
    showLogo(`api.php?action=logo&t=${Date.now()}`);
  }
  $("report-title").textContent = currentTemplate.name || templateId;
  $("report-sub").textContent = `${data.row_count} rows · ${currentTemplate.description || ""}`;
  setCompanyActionsEnabled(true);
  setListActionsEnabled(true);
  renderTemplates();
  renderTitleBlock(currentTemplate);
  renderRevisionTable(currentTemplate);
  renderGrid($("search").value);
  updateChrome();
}

function ensureUploadStatusStructure() {
  const status = $("upload-status");
  if ($("upload-progress-fill") && $("upload-status-label")) return status;
  status.innerHTML = `<div class="upload-status-head">
      <span id="upload-status-label">Uploading…</span>
      <span id="upload-status-meta" class="muted"></span>
    </div>
    <div class="progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100">
      <div class="progress-fill" id="upload-progress-fill"></div>
    </div>`;
  return status;
}

function setUploadProgress(percent, label, meta = "") {
  const status = ensureUploadStatusStructure();
  status.hidden = false;
  status.classList.add("busy");
  $("upload-progress-fill").style.width = `${Math.max(0, Math.min(100, percent))}%`;
  $("upload-status-label").textContent = label;
  $("upload-status-meta").textContent = meta;
  status.setAttribute("aria-busy", "true");
}

function clearUploadProgress() {
  const status = $("upload-status");
  if (!status) return;
  status.classList.remove("busy");
  status.setAttribute("aria-busy", "false");
  status.hidden = true;
  const fill = $("upload-progress-fill");
  if (fill) fill.style.width = "0%";
}

function uploadDcf(file) {
  return new Promise((resolve, reject) => {
    const session = beginProjectSession();
    setUploadProgress(0, "Uploading…", file.name);
    $("btn-upload").textContent = "Uploading…";
    $("dcf-file").disabled = true;

    const body = new FormData();
    body.append("file", file);
    const xhr = new XMLHttpRequest();
    xhr.open("POST", "api.php?action=upload");
    xhr.responseType = "json";
    xhr.upload.onprogress = (e) => {
      if (!e.lengthComputable) return;
      const pct = Math.round((e.loaded / e.total) * 85);
      setUploadProgress(pct, "Uploading…", `${Math.round(e.loaded / 1024)} / ${Math.round(e.total / 1024)} KB`);
    };
    xhr.onload = async () => {
      try {
        setUploadProgress(90, "Analysing project…", file.name);
        const data = xhr.response || {};
        if (xhr.status === 401) {
          location.href = "login.php";
          return;
        }
        if (xhr.status >= 400 || data.ok === false) {
          throw new Error(data.detail || `Upload failed (${xhr.status})`);
        }
        if (!isActiveSession(session)) return resolve();
        project = data.project;
        projectDetails = null;
        renderProjectMeta(project, data.uploaded_filename || file.name);
        renderTemplates();
        await refreshLogo();
        clearUploadProgress();
        $("btn-upload").textContent = "Upload .dcf";
        $("dcf-file").disabled = false;
        setCompanyActionsEnabled(true);
        showSuccess("Project ready", data.uploaded_filename || file.name);
        await loadReport(currentId || "valve_list", session);
        resolve();
      } catch (err) {
        clearUploadProgress();
        $("btn-upload").textContent = "Upload .dcf";
        $("dcf-file").disabled = false;
        reject(err);
      }
    };
    xhr.onerror = () => {
      clearUploadProgress();
      $("btn-upload").textContent = "Upload .dcf";
      $("dcf-file").disabled = false;
      reject(new Error("Network error during upload."));
    };
    xhr.send(body);
  });
}

async function boot() {
  const me = await api("me");
  if (!me.authenticated) {
    location.href = "login.php";
    return;
  }
  currentUser = me.user;
  companyProfile = me.profile;
  $("company-chip").textContent = currentUser.company_name;
  $("user-label").textContent = currentUser.display_name;
  if (me.has_logo) await refreshLogo();

  const data = await api("templates");
  templates = data.templates || [];
  renderTemplates();
  setCompanyActionsEnabled(false);
  setListActionsEnabled(false);

  // Header / logo / save are company-level — enable once signed in.
  $("btn-header").disabled = false;
  $("btn-save").disabled = false;
  $("logo-file").disabled = false;
  $("logo-label").classList.remove("disabled");

  try {
    const proj = await api("project");
    project = proj.project;
    renderProjectMeta(project, proj.uploaded_filename);
    renderTemplates();
    setCompanyActionsEnabled(true);
    clearUploadProgress();
    await loadReport(currentId || "valve_list");
  } catch {
    renderProjectMeta(null);
    updateChrome();
  }
}

$("notice-close")?.addEventListener("click", hideNotice);

function acceptDcfFile(file) {
  if (!file) return;
  if (!/\.dcf$/i.test(file.name)) {
    showError("Invalid file", "Please select a Plant 3D database file (.dcf).");
    return;
  }
  uploadDcf(file).catch((err) => showError("Upload failed", err.message));
}

$("dcf-file").addEventListener("change", (e) => {
  const file = e.target.files?.[0];
  acceptDcfFile(file);
  e.target.value = "";
});

$("empty-upload-btn")?.addEventListener("click", () => {
  $("dcf-file")?.click();
});

const uploadZone = $("upload-zone");
if (uploadZone) {
  ["dragenter", "dragover"].forEach((evt) => {
    uploadZone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadZone.classList.add("dragover");
    });
  });
  ["dragleave", "drop"].forEach((evt) => {
    uploadZone.addEventListener(evt, (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadZone.classList.remove("dragover");
    });
  });
  uploadZone.addEventListener("drop", (e) => {
    const file = e.dataTransfer?.files?.[0];
    acceptDcfFile(file);
  });
}

$("btn-header").addEventListener("click", () => {
  openHeaderDialog().catch((err) => showError("Header setup", err.message));
});

$("header-apply").addEventListener("click", () => {
  applyHeaderToAllLists()
    .then(() => $("header-dialog").close())
    .catch((err) => showError("Could not apply header", err.message));
});

$("hdr-add-rev").addEventListener("click", () => {
  $("rev-editor").querySelector("tbody").insertAdjacentHTML(
    "beforeend",
    `<tr>
      <td><input data-rev="rev" value="" /></td>
      <td><input data-rev="date" type="date" value="" /></td>
      <td><input data-rev="desc" value="" /></td>
      <td><input data-rev="drawn" value="" /></td>
    </tr>`
  );
});

$("btn-columns").addEventListener("click", () => openColumnsDialog());

$("columns-apply").addEventListener("click", async () => {
  applyColumnsToTemplate();
  $("columns-dialog").close();
  try {
    await persistWorkingTemplate(currentTemplate);
    await loadReport(baseTemplateId() || currentId);
    showSuccess("Columns updated", "Applied to this list only.");
  } catch (err) {
    showError("Could not apply columns", err.message);
  }
});

$("btn-save").addEventListener("click", () => openSaveDialog());

$("save-confirm").addEventListener("click", async () => {
  const mode = document.querySelector('input[name="save-mode"]:checked')?.value || "standard";
  $("save-dialog").close();
  try {
    if (mode === "reset") {
      await resetToFactoryDefault();
      return;
    }
    if (mode === "header_only") {
      await saveCompanyHeaderOnly();
      return;
    }
    await saveCompanyStandard({ overwrite: mode === "overwrite" });
  } catch (err) {
    showError("Save failed", err.message);
  }
});

$("logo-file").addEventListener("change", async (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  try {
    const data = new FormData();
    data.append("file", file);
    const res = await fetch("api.php?action=logo", { method: "POST", body: data });
    if (res.status === 401) {
      location.href = "login.php";
      return;
    }
    const json = await res.json().catch(() => ({}));
    if (!res.ok || json.ok === false) throw new Error(json.detail || "Logo upload failed");
    showLogo(json.url || `api.php?action=logo&t=${Date.now()}`);
    showSuccess("Logo uploaded", "Your company logo appears on every list and Excel export.");
  } catch (err) {
    showError("Logo upload failed", err.message);
  } finally {
    e.target.value = "";
  }
});

$("btn-export").addEventListener("click", () => openExportDialog());

$("export-confirm").addEventListener("click", () => {
  confirmExport()
    .then(() => $("export-dialog").close())
    .catch((err) => showError("Export failed", err.message));
});

$("btn-logout").addEventListener("click", async () => {
  try {
    await api("logout", { fetch: { method: "POST" } });
  } catch {
    /* still leave */
  }
  location.href = "login.php";
});

$("search").addEventListener("input", (e) => renderGrid(e.target.value));

boot().catch((err) => {
  showErrorDialog("Could not start", err.message);
});
