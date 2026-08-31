from __future__ import annotations

import sqlite3
import xml.etree.ElementTree as ET
from dataclasses import dataclass, field
from pathlib import Path


@dataclass
class ProjectInfo:
    name: str = ""
    description: str = ""
    number: str = ""
    location: str = ""
    location_code: str = ""
    status: str = ""
    standard: str = ""
    palette: str = ""
    s88_code: str = ""
    revision_date: str = ""
    revision_note: str = ""
    dcf_path: str = ""
    project_dir: str = ""
    drawing_count: int = 0
    counts: dict[str, int] = field(default_factory=dict)
    drawings: list[dict] = field(default_factory=list)


def find_project_dir(path: str | Path) -> Path:
    p = Path(path).resolve()
    if p.is_file():
        p = p.parent
    if (p / "Project.xml").exists() or (p / "ProcessPower.dcf").exists():
        return p
    if any(p.glob("*.dcf")):
        return p
    raise FileNotFoundError(
        "No Plant 3D project database found. Select ProcessPower.dcf from your project folder."
    )


def find_dcf(project_dir: Path) -> Path:
    dcf = project_dir / "ProcessPower.dcf"
    if dcf.exists():
        return dcf
    matches = list(project_dir.glob("*.dcf"))
    if not matches:
        raise FileNotFoundError(f"No .dcf file in {project_dir}")
    preferred = [m for m in matches if m.name.lower() == "processpower.dcf"]
    return preferred[0] if preferred else matches[0]


def validate_dcf(dcf_path: Path) -> None:
    """Raise ValueError if the file is not a readable Plant 3D SQLite database."""
    path = Path(dcf_path).resolve()
    if not path.is_file():
        raise FileNotFoundError(f"DCF file not found: {path}")
    if path.suffix.lower() != ".dcf":
        raise ValueError("Please select a Plant 3D database file (.dcf).")
    try:
        with connect(path) as con:
            if not table_exists(con, "EngineeringItems") and not table_exists(con, "PnPProject"):
                raise ValueError(
                    "This file does not look like a Plant 3D ProcessPower database."
                )
    except sqlite3.DatabaseError as exc:
        raise ValueError("The selected file is not a valid SQLite database.") from exc


def connect(dcf_path: Path) -> sqlite3.Connection:
    path = Path(dcf_path).resolve()
    uri = f"file:{path.as_posix()}?mode=ro"
    con = sqlite3.connect(uri, uri=True)
    con.row_factory = sqlite3.Row
    return con


def connect_rw(dcf_path: Path) -> sqlite3.Connection:
    con = sqlite3.connect(Path(dcf_path).resolve())
    con.row_factory = sqlite3.Row
    return con


def table_exists(con: sqlite3.Connection, name: str) -> bool:
    row = con.execute(
        "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?", (name,)
    ).fetchone()
    return row is not None


def table_count(con: sqlite3.Connection, name: str) -> int:
    if not table_exists(con, name):
        return 0
    return int(con.execute(f"SELECT COUNT(*) FROM [{name}]").fetchone()[0])


def _xml_text(project_dir: Path) -> tuple[str, str]:
    xml = project_dir / "Project.xml"
    if not xml.exists():
        return "", ""
    root = ET.parse(xml).getroot()
    name = (root.findtext("ProjectName") or "").strip()
    desc = (root.findtext("ProjectDescription") or "").strip()
    return name, desc


def load_project(path: str | Path, *, include_drawings: bool = True) -> tuple[ProjectInfo, Path]:
    project_dir = find_project_dir(path)
    dcf_path = find_dcf(project_dir)
    info = ProjectInfo(dcf_path=str(dcf_path), project_dir=str(project_dir))
    xml_name, xml_desc = _xml_text(project_dir)
    info.name = xml_name
    info.description = xml_desc

    with connect(dcf_path) as con:
        if table_exists(con, "PnPProject"):
            row = con.execute("SELECT * FROM PnPProject LIMIT 1").fetchone()
            if row:
                d = dict(row)
                info.name = d.get("Project_Name") or info.name
                info.description = d.get("Project_Description") or info.description
                info.number = d.get("Project_Number") or info.name
                info.location = d.get("S88_Locatie") or ""
                info.location_code = d.get("S88_Locatiecode") or ""
                info.status = d.get("S88_Projectstatus") or ""
                info.standard = d.get("ToolPaletteGroupName") or d.get("Project_Standard") or ""
                info.palette = d.get("ToolPaletteGroupName") or ""
                info.s88_code = d.get("S88_Projectcode") or ""
                info.revision_date = d.get("Projectrevisie_Revisiedatum 0") or ""
                info.revision_note = d.get("Projectrevisie_Gewijzigd 0") or ""

        info.counts = {
            "drawings": table_count(con, "PnPDrawings"),
            "equipment": table_count(con, "Equipment"),
            "hand_valves": table_count(con, "HandValves"),
            "control_valves": table_count(con, "Gestuurdeafsluiters"),
            "instruments": table_count(con, "Instrumentation"),
            "pipe_lines": table_count(con, "PipeLines"),
            "line_groups": table_count(con, "PipeLineGroup"),
            "engineering_items": table_count(con, "EngineeringItems"),
        }
        info.drawing_count = info.counts["drawings"]

        if include_drawings and table_exists(con, "PnPDrawings"):
            rows = con.execute(
                """
                SELECT PnID, [Dwg Name] AS DwgName, Title,
                       Kader_Inh1, Kader_Inh2, Kader_Procesopstal,
                       Kader_Wijziging, [Kader_Stempel PID Status] AS Status,
                       [Kader_Getekend door] AS DrawnBy,
                       [Kader_Datum getekend] AS DrawnDate
                FROM PnPDrawings
                ORDER BY PnID
                """
            ).fetchall()
            info.drawings = [dict(r) for r in rows]

    return info, dcf_path
