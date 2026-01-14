# usage 
# Make sure to give the viewer access to service account (sheets-csv-exporter-283@benchmarker-481203.iam.gserviceaccount.com)  to google sheet first 
# python download_csvs.py "<GOOGLE SHEEET URL>" --commit

import argparse
import csv
import re
import sys
import os
import subprocess
import tempfile
from pathlib import Path
from typing import List, Any

from google.oauth2 import service_account
from googleapiclient.discovery import build

SERVICE_ACCOUNT_FILE = "service_account.json"
SCOPES = ["https://www.googleapis.com/auth/spreadsheets.readonly"]
VALUE_RENDER_OPTION = "FORMATTED_VALUE"


# ----------------- utilities -----------------

def extract_spreadsheet_id(url: str) -> str:
    m = re.search(r"/spreadsheets/d/([a-zA-Z0-9-_]+)", url)
    if not m:
        raise ValueError("Invalid Google Sheets URL")
    return m.group(1)


def sanitize(name: str) -> str:
    name = name.strip()
    name = re.sub(r"[\\/:*?\"<>|]+", "_", name)
    name = re.sub(r"\s+", " ", name)
    return name[:150] if len(name) > 150 else name


def ensure_dir(p: Path) -> None:
    os.makedirs(str(p), exist_ok=True)


def safe_cwd() -> Path:
    try:
        return Path(os.getcwd())
    except Exception:
        return Path.home()


def resolve_out_dir(out_dir_arg: str) -> Path:
    p = Path(out_dir_arg)
    if not p.is_absolute():
        p = safe_cwd() / p

    try:
        ensure_dir(p)
        return p
    except Exception:
        fallback = Path(tempfile.gettempdir()) / "download_csvs" / p.name
        ensure_dir(fallback)
        print(
            f"WARNING: Cannot create {p}. Using fallback {fallback}",
            file=sys.stderr,
        )
        return fallback


def write_csv(rows: List[List[Any]], out_path: Path) -> None:
    ensure_dir(out_path.parent)
    with out_path.open("w", newline="", encoding="utf-8") as f:
        csv.writer(f).writerows(rows)


# ----------------- git helpers -----------------

def is_git_repo(path: Path) -> bool:
    try:
        subprocess.run(
            ["git", "rev-parse", "--is-inside-work-tree"],
            cwd=path,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
            check=True,
        )
        return True
    except Exception:
        return False


def git_commit_changes(repo_path: Path, commit_msg: str) -> None:
    try:
        subprocess.run(["git", "add", "."], cwd=repo_path, check=True)

        status = subprocess.run(
            ["git", "status", "--porcelain"],
            cwd=repo_path,
            capture_output=True,
            text=True,
        )

        if not status.stdout.strip():
            print("No Git changes to commit.")
            return

        subprocess.run(
            ["git", "commit", "-m", commit_msg],
            cwd=repo_path,
            check=True,
        )
        print("Git commit created.")

    except Exception as e:
        print(f"WARNING: Git commit failed: {e}", file=sys.stderr)


# ----------------- core logic -----------------

def download_csvs(sheet_url: str, out_dir: Path, auto_commit: bool) -> None:
    spreadsheet_id = extract_spreadsheet_id(sheet_url)

    if not Path(SERVICE_ACCOUNT_FILE).exists():
        raise FileNotFoundError(
            f"Missing {SERVICE_ACCOUNT_FILE}. Place it next to this script."
        )

    creds = service_account.Credentials.from_service_account_file(
        SERVICE_ACCOUNT_FILE, scopes=SCOPES
    )
    service = build("sheets", "v4", credentials=creds)

    meta = service.spreadsheets().get(spreadsheetId=spreadsheet_id).execute()
    sheets = meta.get("sheets", [])

    tab_titles = [
        s["properties"]["title"]
        for s in sheets
        if "properties" in s and "title" in s["properties"]
    ]
    if not tab_titles:
        raise RuntimeError("No sheets found")

    first_tab = tab_titles[0]
    first_safe = sanitize(first_tab)

    print(f"Spreadsheet ID: {spreadsheet_id}")
    print(f"First sheet: {first_tab}")
    print(f"Total sheets: {len(tab_titles)}")
    print(f"Writing CSVs under: {out_dir}")

    def fetch(tab: str):
        resp = (
            service.spreadsheets()
            .values()
            .get(
                spreadsheetId=spreadsheet_id,
                range=tab,
                valueRenderOption=VALUE_RENDER_OPTION,
            )
            .execute()
        )
        return resp.get("values", [])

    # First sheet
    write_csv(fetch(first_tab), out_dir / f"{first_safe}.csv")

    # Remaining sheets
    for tab in tab_titles[1:]:
        tab_safe = sanitize(tab)
        out_path = out_dir / first_safe / f"{tab_safe}.csv"
        write_csv(fetch(tab), out_path)

    print("CSV download complete.")

    # Git commit (if requested)
    if auto_commit:
        repo_root = out_dir
        while repo_root != repo_root.parent:
            if is_git_repo(repo_root):
                break
            repo_root = repo_root.parent
        else:
            print("Not inside a Git repository. Skipping commit.")
            return

        git_commit_changes(
            repo_root,
            f"Update CSV exports from Google Sheets ({spreadsheet_id})",
        )


# ----------------- CLI -----------------

def main():
    parser = argparse.ArgumentParser(
        description="Download Google Sheets tabs as CSV and optionally commit to Git"
    )
    parser.add_argument("url", help="Google Sheets URL")
    parser.add_argument("--out-dir", default="data", help="Output directory")
    parser.add_argument(
        "--commit",
        action="store_true",
        help="Automatically commit changes to git",
    )
    args = parser.parse_args()

    try:
        out_dir = resolve_out_dir(args.out_dir)
        download_csvs(args.url, out_dir, args.commit)
    except Exception as e:
        print(f"ERROR: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
