# Benchmarker DB — Setup & Usage

This repository contains the MySQL schema and seed data for the `suites_db` database used to store benchmark suites, tools, and results.

## Requirements
- **MySQL 8.0+** (LTS 8.4 works)
- A MySQL user with privileges (examples use `root`)
- Repo cloned locally (so paths like `db/schema.sql` and `db/seed/minimal_seed.sql` exist)

---

## Quick Start

### Option A — MySQL Workbench
1. Open **MySQL Workbench**.  
2. **File → Open SQL Script…** → select `db/schema.sql` → **Execute**.  
3. **File → Open SQL Script…** → select `db/seed/minimal_seed.sql` → **Execute**.

### Option B — Command line (Windows, `cmd.exe`)
From the repo root (folder that contains `db\schema.sql`), run:
```bat
"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p -e "CREATE DATABASE IF NOT EXISTS suites_db"

"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p suites_db < db\schema.sql

"C:\Program Files\MySQL\MySQL Server 8.4\bin\mysql.exe" -u root -p suites_db < db\seed\minimal_seed.sql
```

### Option C — Command line (macOS/Linux)
``` 
mysql -u root -p -e 'CREATE DATABASE IF NOT EXISTS suites_db'
mysql -u root -p suites_db < db/schema.sql
mysql -u root -p suites_db < db/seed/minimal_seed.sql
```
