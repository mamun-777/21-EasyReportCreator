from __future__ import annotations

import shutil
from datetime import date
from io import BytesIO
from pathlib import Path
from typing import Any

from openpyxl import Workbook, load_workbook
from openpyxl.drawing.image import Image as XLImage
from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
from openpyxl.utils import get_column_letter

NAVY = "0B4F6C"
TEAL = "0D7377"
ACCENT = "01BAEF"
HEADER_BG = "073B4C"
ROW_ALT = "E8F4F8"
THIN = Border(
    left=Side(style="thin", color="C5D5DE"),
    right=Side(style="thin", color="C5D5DE"),
    top=Side(style="thin", color="C5D5DE"),
    bottom=Side(style="thin", color="C5D5DE"),
)


def export_workbook(
    rows: list[dict[str, Any]],
    template: dict[str, Any],
    project: dict[str, Any],
    logo_path: Path | None = None,
) -> bytes:
    columns = [c for c in template.get("columns", []) if c.get("visible", True)]
    headers = [c.get("header") or c["key"] for c in columns]
    keys = [c["key"] for c in columns]
    include_id = template.get("include_pnpid", True)

    wb = Workbook()
    ws = wb.active
    ws.title = (template.get("name") or "List")[:31]

    header = template.get("header") or {}
    revisions = template.get("revision_table") or []
    title = header.get("title") or template.get("name") or "Plant 3D List"
    doc_no = header.get("document_number") or f"{project.get('number', '')}-{template.get('id', 'LST')}"
    rev = header.get("revision") or (revisions[0]["rev"] if revisions else "A")

    ws.merge_cells("A1:C3")
    logo_cell = ws["A1"]
    logo_cell.alignment = Alignment(horizontal="center", vertical="center")
    if logo_path and logo_path.exists():
        img = XLImage(str(logo_path))
        img.width = 140
        img.height = 48
        ws.add_image(img, "A1")
    else:
        logo_cell.value = header.get("company") or "COMPANY LOGO"
        logo_cell.font = Font(name="Calibri", bold=True, size=14, color=NAVY)

    last_col = max(len(keys) + (1 if include_id else 0), 8)
    last_letter = get_column_letter(last_col)

    ws.merge_cells(f"D1:{last_letter}1")
    ws["D1"].value = title
    ws["D1"].font = Font(name="Calibri", bold=True, size=18, color=HEADER_BG)
    ws["D1"].alignment = Alignment(horizontal="left", vertical="center")

    meta: list[tuple[str, str]] = []
    fields = header.get("fields") or []
    if fields:
        for field in fields:
            label = field.get("label") or field.get("key") or ""
            value = field.get("value") or ""
            if label:
                meta.append((label, value))
    else:
        meta.extend(
            [
                ("Project", project.get("name") or ""),
                ("Description", project.get("description") or ""),
                ("Status", project.get("status") or ""),
                ("Standard", project.get("standard") or ""),
            ]
        )
    meta.extend(
        [
            ("Document", doc_no),
            ("Revision", rev),
            ("Date", header.get("date") or date.today().isoformat()),
        ]
    )
    for i, (label, value) in enumerate(meta, start=2):
        cell_l = ws.cell(i, 4, label)
        cell_l.font = Font(name="Calibri", bold=True, size=9, color="5A6A75")
        ws.merge_cells(start_row=i, start_column=5, end_row=i, end_column=min(8, last_col))
        cell_v = ws.cell(i, 5, value)
        cell_v.font = Font(name="Calibri", size=10, color=HEADER_BG)

    rev_start_col = min(9, last_col)
    ws.cell(2, rev_start_col, "REVISION").font = Font(name="Calibri", bold=True, size=9, color="FFFFFF")
    ws.cell(2, rev_start_col).fill = PatternFill("solid", fgColor=NAVY)
    for col, name in enumerate(["Rev", "Date", "Description", "Drawn", "Checked", "Approved"], start=rev_start_col):
        if col > last_col:
            break
        cell = ws.cell(3, col, name)
        cell.font = Font(name="Calibri", bold=True, size=8, color="FFFFFF")
        cell.fill = PatternFill("solid", fgColor=TEAL)
        cell.alignment = Alignment(horizontal="center")
    for r_i, rev_row in enumerate(revisions[:4], start=4):
        values = [
            rev_row.get("rev", ""),
            rev_row.get("date", ""),
            rev_row.get("desc", ""),
            rev_row.get("drawn", ""),
            rev_row.get("checked", ""),
            rev_row.get("approved", ""),
        ]
        for c_i, value in enumerate(values):
            col = rev_start_col + c_i
            if col > last_col:
                break
            cell = ws.cell(r_i, col, value)
            cell.font = Font(name="Calibri", size=8)
            cell.border = THIN

    header_row = 9
    fill = PatternFill("solid", fgColor=HEADER_BG)
    font = Font(name="Calibri", bold=True, color="FFFFFF", size=10)
    for idx, header_name in enumerate(headers, start=1):
        cell = ws.cell(header_row, idx, header_name)
        cell.fill = fill
        cell.font = font
        cell.alignment = Alignment(horizontal="center", wrap_text=True, vertical="center")
        cell.border = THIN
    if include_id:
        cell = ws.cell(header_row, len(headers) + 1, "PnPID")
        cell.fill = fill
        cell.font = font
        cell.border = THIN

    alt = PatternFill("solid", fgColor=ROW_ALT)
    for r_idx, row in enumerate(rows, start=header_row + 1):
        for c_idx, key in enumerate(keys, start=1):
            cell = ws.cell(r_idx, c_idx, row.get(key, ""))
            cell.font = Font(name="Calibri", size=9)
            cell.border = THIN
            cell.alignment = Alignment(vertical="center")
            if (r_idx - header_row) % 2 == 0:
                cell.fill = alt
        if include_id:
            cell = ws.cell(r_idx, len(headers) + 1, row.get("PnPID", ""))
            cell.font = Font(name="Calibri", size=8, color="8A9BA8")
            cell.border = THIN

    ws.auto_filter.ref = f"A{header_row}:{get_column_letter(len(keys) + (1 if include_id else 0))}{header_row + max(len(rows), 1)}"
    ws.freeze_panes = f"A{header_row + 1}"
    ws.row_dimensions[header_row].height = 28
    ws.row_dimensions[1].height = 22

    for idx, col in enumerate(columns, start=1):
        width = col.get("width") or max(12, min(36, len(col.get("header") or col["key"]) + 4))
        ws.column_dimensions[get_column_letter(idx)].width = width
    if include_id:
        ws.column_dimensions[get_column_letter(len(keys) + 1)].width = 10

    ws.print_title_rows = f"1:{header_row}"
    ws.page_setup.orientation = "landscape"
    ws.page_setup.fitToPage = True
    ws.page_setup.fitToWidth = 1
    ws.page_setup.fitToHeight = 0
    ws.page_setup.paperSize = ws.PAPERSIZE_A3
    ws.oddFooter.left.text = f"&8{doc_no}  Rev {rev}"
    ws.oddFooter.right.text = "&8Page &P of &N"

    meta_sheet = wb.create_sheet("_EasyReportCreator")
    meta_sheet["A1"] = "template_id"
    meta_sheet["B1"] = template.get("id", "")
    meta_sheet["A2"] = "source"
    meta_sheet["B2"] = template.get("source", "")
    meta_sheet["A3"] = "project"
    meta_sheet["B3"] = project.get("name", "")
    meta_sheet.sheet_state = "hidden"

    buf = BytesIO()
    wb.save(buf)
    return buf.getvalue()


def read_imported_excel(content: bytes) -> tuple[list[dict[str, Any]], dict[str, str]]:
    wb = load_workbook(BytesIO(content), data_only=True)
    meta = {}
    meta_name = next((n for n in ("_EasyReportCreator", "_PlantList") if n in wb.sheetnames), None)
    if meta_name:
        ms = wb[meta_name]
        for row in ms.iter_rows(min_row=1, max_col=2, values_only=True):
            if row[0]:
                meta[str(row[0])] = str(row[1] or "")
    ws = wb[wb.sheetnames[0]]
    header_row = 1
    for r in range(1, 20):
        values = [ws.cell(r, c).value for c in range(1, 12)]
        if any(v == "PnPID" for v in values) or (values[0] and str(values[0]).lower() in {"tag", "pnid"}):
            header_row = r
            break
    headers = []
    col = 1
    while True:
        value = ws.cell(header_row, col).value
        if value is None and col > 1:
            break
        headers.append(str(value) if value is not None else f"col{col}")
        col += 1
        if col > 80:
            break
    rows = []
    for r in range(header_row + 1, ws.max_row + 1):
        item = {}
        empty = True
        for c, header in enumerate(headers, start=1):
            value = ws.cell(r, c).value
            if value not in (None, ""):
                empty = False
            item[header] = "" if value is None else value
        if not empty:
            rows.append(item)
    return rows, meta


def apply_changes_to_copy(
    source_dcf: Path,
    target_dcf: Path,
    changes: list[dict[str, Any]],
) -> Path:
    shutil.copy2(source_dcf, target_dcf)
    import sqlite3

    con = sqlite3.connect(target_dcf)
    try:
        for change in changes:
            con.execute(
                f"UPDATE EngineeringItems SET [{change['column']}] = ? WHERE PnPID = ?",
                (change["new"], change["pnpid"]),
            )
        con.commit()
    finally:
        con.close()
    return target_dcf
