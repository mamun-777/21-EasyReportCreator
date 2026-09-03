const $ = (id) => document.getElementById(id);

let templates = [];
let currentId = null;
let currentTemplate = null;
let currentRows = [];
let project = null;

function notice(title, message) {
  $("notice-title").textContent = title;
  $("notice-message").textContent = message;
  $("notice-dialog").showModal();
}

async function api(action, options = {}) {
  const url = `api.php?action=${encodeURIComponent(action)}${options.query || ""}`;
  const res = await fetch(url, options.fetch || undefined);
  if (options.blob) {
    if (!res.ok) {
      const text = await res.text();
      throw new Error(text || res.statusText);
    }
    return res.blob();
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.detail || res.statusText || "Request failed");
  }
  return data;
}

function renderTemplates() {
  const nav = $("template-nav");
  nav.innerHTML = templates
    .map(
      (t) => `<button type="button" class="nav-item ${t.id === currentId ? "active" : ""}" data-id="${t.id}">
        <span class="name">${escapeHtml(t.name)}${t.has_standard ? '<span class="badge">std</span>' : ""}</span>
        <span class="desc">${escapeHtml(t.description || "")}</span>
      </button>`
    )
    .join("");
  nav.querySelectorAll(".nav-item").forEach((btn) => {
    btn.addEventListener("click", () => loadReport(btn.dataset.id).catch((err) => notice("Could not load list", err.message)));
  });
}

function renderStats(counts) {
  const map = [
    ["drawings", "Drawings"],
    ["equipment", "Equipment"],
    ["hand_valves", "Hand valves"],
    ["control_valves", "Control valves"],
    ["instruments", "Instruments"],
    ["pipe_lines", "Pipe lines"],
    ["engineering_items", "Eng. items"],
  ];
  $("stats").innerHTML = map
    .map(
      ([key, label]) => `<div class="stat"><div class="n">${counts?.[key] ?? "—"}</div><div class="l">${label}</div></div>`
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
    .map((row) => `<tr>${cols.map((c) => `<td>${escapeHtml(String(row[c.key] ?? ""))}</td>`).join("")}</tr>`)
    .join("");
  $("row-count").textContent = `${rows.length} row${rows.length === 1 ? "" : "s"}`;
}

async function boot() {
  const data = await api("templates");
  templates = data.templates || [];
  renderTemplates();
  try {
    const proj = await api("project");
    project = proj.project;
    $("project-meta").innerHTML = `<strong>${escapeHtml(project.name || project.number || "Uploaded project")}</strong><br>${escapeHtml(proj.uploaded_filename || "")}`;
    renderStats(project.counts || {});
    $("btn-export").disabled = !currentId;
  } catch {
    renderStats({});
  }
}

async function uploadDcf(file) {
  const status = $("upload-status");
  status.hidden = false;
  status.textContent = "Uploading and analysing…";
  const body = new FormData();
  body.append("file", file);
  body.append("action", "upload");
  const res = await fetch("api.php?action=upload", { method: "POST", body });
  const data = await res.json().catch(() => ({}));
  if (!res.ok || data.ok === false) {
    throw new Error(data.detail || "Upload failed");
  }
  project = data.project;
  status.innerHTML = `<strong>Ready</strong> — ${escapeHtml(data.uploaded_filename || file.name)}`;
  $("project-meta").innerHTML = `<strong>${escapeHtml(project.name || project.number || "Uploaded project")}</strong><br>${escapeHtml(data.uploaded_filename || file.name)}`;
  renderStats(project.counts || {});
  if (currentId) {
    await loadReport(currentId);
  }
}

async function loadReport(templateId) {
  currentId = templateId;
  renderTemplates();
  const data = await api("report", { query: `&template_id=${encodeURIComponent(templateId)}` });
  currentTemplate = data.template;
  currentRows = data.rows || [];
  project = data.project;
  $("report-title").textContent = currentTemplate.name || templateId;
  $("report-sub").textContent = `${data.row_count} rows · ${currentTemplate.description || ""}`;
  $("btn-export").disabled = false;
  renderStats(project.counts || {});
  renderGrid($("search").value);
}

async function exportExcel() {
  if (!currentId) return;
  const blob = await api("export", {
    query: `&template_id=${encodeURIComponent(currentId)}`,
    blob: true,
  });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${(project?.number || "project")}_${currentId}.xlsx`;
  a.click();
  URL.revokeObjectURL(url);
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;");
}

$("dcf-file").addEventListener("change", (e) => {
  const file = e.target.files?.[0];
  if (!file) return;
  uploadDcf(file).catch((err) => notice("Upload failed", err.message));
  e.target.value = "";
});

$("btn-export").addEventListener("click", () => {
  exportExcel().catch((err) => notice("Export failed", err.message));
});

$("search").addEventListener("input", (e) => renderGrid(e.target.value));

boot().catch((err) => notice("Could not start", err.message));
