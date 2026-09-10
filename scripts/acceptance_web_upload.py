"""Acceptance: register → upload MN-P-RHN-PID-0001 → 8 list counts → Excel export → logout."""
from __future__ import annotations

import json
import sys
import tempfile
import time
import urllib.error
import urllib.request
from http.cookiejar import CookieJar
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
DCF = ROOT / "samples" / "MN-P-RHN-PID-0001" / "ProcessPower.dcf"
BASE = sys.argv[1] if len(sys.argv) > 1 else "https://easyreportcreator.com/report"

EXPECTED = {
    "drawing_list": 30,
    "equipment_list": 80,
    "valve_list": 344,
    "control_valve_list": 43,
    "instrument_list": 126,
    "line_list": 611,
    "line_summary": 386,
    "component_list": 897,
}


class Api:
    def __init__(self, base: str) -> None:
        self.base = base.rstrip("/")
        self.jar = CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def json(self, action: str, method: str = "GET", payload: dict | None = None, query: str = "") -> dict:
        url = f"{self.base}/api.php?action={action}{query}"
        data = None
        headers = {"Accept": "application/json"}
        if payload is not None:
            data = json.dumps(payload).encode("utf-8")
            headers["Content-Type"] = "application/json; charset=utf-8"
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        with self.opener.open(req, timeout=180) as res:
            body = res.read().decode("utf-8")
        obj = json.loads(body)
        if obj.get("ok") is False:
            raise RuntimeError(f"{action}: {obj.get('detail')}")
        return obj

    def upload(self, path: Path) -> dict:
        boundary = f"----erc{int(time.time())}"
        raw = path.read_bytes()
        parts = []
        parts.append(f"--{boundary}\r\n".encode())
        parts.append(
            b'Content-Disposition: form-data; name="file"; filename="ProcessPower.dcf"\r\n'
            b"Content-Type: application/octet-stream\r\n\r\n"
        )
        parts.append(raw)
        parts.append(f"\r\n--{boundary}--\r\n".encode())
        body = b"".join(parts)
        url = f"{self.base}/api.php?action=upload"
        req = urllib.request.Request(
            url,
            data=body,
            headers={
                "Content-Type": f"multipart/form-data; boundary={boundary}",
                "Accept": "application/json",
            },
            method="POST",
        )
        with self.opener.open(req, timeout=180) as res:
            obj = json.loads(res.read().decode("utf-8"))
        if obj.get("ok") is False:
            raise RuntimeError(f"upload: {obj.get('detail')}")
        return obj

    def export(self, template_id: str) -> bytes:
        url = (
            f"{self.base}/api.php?action=export&template_id={template_id}"
            "&include_logo=0&include_revision=1&include_pnpid=1"
        )
        req = urllib.request.Request(url, method="GET")
        with self.opener.open(req, timeout=180) as res:
            data = res.read()
        if len(data) < 1000:
            raise RuntimeError(f"export too small ({len(data)} bytes)")
        return data


def main() -> int:
    if not DCF.is_file():
        print(f"Missing DCF: {DCF}", file=sys.stderr)
        return 2
    ts = time.strftime("%Y%m%d%H%M%S")
    email = f"accept+{ts}@example.com"
    password = f"Accept-{ts}-Ok9"
    api = Api(BASE)
    print(f"Base: {BASE}")
    print(f"Register {email}")
    api.json(
        "register",
        method="POST",
        payload={
            "company_name": f"Acceptance {ts}",
            "display_name": "Acceptance Bot",
            "email": email,
            "password": password,
        },
    )
    print("Upload ProcessPower.dcf ...")
    uploaded = api.upload(DCF)
    proj = uploaded.get("project") or {}
    print(f"Project: {proj.get('name')} / {proj.get('number')}")

    failed: list[str] = []
    for tid, exp in EXPECTED.items():
        print(f"Report {tid} ...")
        report = api.json("report", query=f"&template_id={tid}")
        got = int(report.get("row_count") or 0)
        if got != exp:
            print(f"  FAIL expected {exp} got {got}")
            failed.append(tid)
        else:
            print(f"  OK {got}")

    print("Export valve_list ...")
    xlsx = api.export("valve_list")
    out = Path(tempfile.gettempdir()) / f"erc-accept-valve-{ts}.xlsx"
    out.write_bytes(xlsx)
    print(f"Excel OK: {out} ({len(xlsx)} bytes)")

    print("Logout ...")
    api.json("logout", method="POST", payload={})

    if failed:
        print("ACCEPTANCE FAILED:", ", ".join(failed), file=sys.stderr)
        return 1
    print("ACCEPTANCE PASSED (8 lists + Excel export)")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except urllib.error.HTTPError as e:
        detail = e.read().decode("utf-8", "replace")
        print(f"HTTP {e.code}: {detail}", file=sys.stderr)
        raise SystemExit(1)
