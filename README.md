# Benchmarker

A comprehensive placement benchmarking system for Electronic Design Automation (EDA) tools, designed to store, manage, and compare benchmark results.  A primary focus is on circuit placement, but the system has been designed to be generic; we would hope that the framework can be reused for other topic areas.

## Overview

The Benchmarker project provides a centralized platform for the EDA research community to systematically collect, store, and compare placement algorithm performance across standardized test cases. It supports well-known benchmark suites from academic contests and provides both web-based and API interfaces for result management.

## Features

- **Database-driven benchmark repository** with MySQL backend
- **Support for multiple benchmark suites**: ICCAD04, ISPD98, ISPD2020, GSRC
- **Multiple Figures of Merit (FOM)** tracking (HPWL, runtime, quality metrics)
- **CSV-based data format** for easy version control and updates
- **Token-based authentication** for secure API access
- **Web interface** for viewing and managing benchmark data
- **RESTful API** for automated result submission

## Project Structure

```
benchmarker/
├── data/                   # Benchmark data in CSV format
│   ├── iccad04/            # ICCAD04 benchmark suite results
│   ├── ispd98/             # ISPD98 benchmark suite results
│   ├── ispd2020/           # ISPD2020 benchmark suite results
│   └── gsrc-floorplan/     # GSRC floorplanning benchmarks
├── db/                     # Database schema and seed data
│   ├── schema.sql          # MySQL database schema
│   └── seed/               # Initial database data
├── doc/                    # Documentation
│   └── formats.md          # CSV file format specification
├── scripts/                # Database management scripts
│   └── insert.php          # CSV to database import script
├── web/                    # Legacy web interface
├── webTest/                # Main web application
│   ├── api/                # RESTful API endpoints
│   ├── lib/                # Helper functions
│   └── pages/              # Web interface pages
└── README.md               # This file
```

## Quick Start

### Prerequisites

- **MySQL 8.0+** (LTS 8.4 recommended)
- **PHP 7.4+** with PDO MySQL extension
- **Apache/Nginx** web server (or XAMPP for development)

### Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd benchmarker
   ```

2. **Set up the database**
   
   Using MySQL Workbench:
   - Open `db/schema.sql` and execute
   - Open `db/seed/minimal_seed.sql` and execute
   
   Using command line:
   ```bash
   mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS suites_db"
   mysql -u root -p suites_db < db/schema.sql
   mysql -u root -p suites_db < db/seed/minimal_seed.sql
   ```

3. **Configure database connection**
   
   Edit `webTest/config.php`:
   ```php
   $host = '127.0.0.1';
   $port = '3306';
   $user = 'root';
   $pass = 'your_password';
   $db   = 'suites_db';
   ```

4. **Set up web server**
   
   For XAMPP (Windows):
   - Copy project to `C:\xampp\htdocs\benchmarker\`
   - Start Apache and MySQL services
   - Access via `http://localhost/benchmarker/webTest/`

## Usage

### CSV Interface

Our framework is migrating towards the use of comma separated variable (CSV) files to upload information into the database.  In practice, we use Google Sheets to store information in spreadsheet format, download this into CSV files, and then use the ``insert'' PHP script to load the database.

* [ICCAD04 Google Sheet](https://docs.google.com/spreadsheets/d/1WwkqwLQ7bkf4Jfz7gCxdItthnzfNtKJZiEt9CTxrEWY/edit?usp=sharing)


### Web Interface

Access the web interface at `http://localhost/benchmarker/webTest/` to:

- **View benchmark results** in organized tables
- **Add/edit benchmark suites** and individual benchmarks
- **Manage tools and releases**
- **Submit new results** through web forms
- **Flag suspicious results** for review

### API Usage

The RESTful API allows programmatic submission of benchmark results.

#### Authentication

Set the API token as an environment variable:
```bash
export API_BMUPLOAD_TOKEN="your_secure_token_here"
```

#### Submit Results

```bash
curl -X POST http://localhost/benchmarker/webTest/api/bmupload.php \
  -H "Authorization: Bearer your_secure_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "suite": "iccad04",
    "suite_variation": "default",
    "benchmark": "ibm01",
    "tool": "YourTool",
    "tool_version": "1.0",
    "primary_fom": 1234567.89,
    "artifact_url": "https://example.com/results.tar.gz"
  }'
```

### CSV Data Format

The system uses CSV files for bulk data import. See `doc/formats.md` for detailed format specification.

#### Import CSV Data

```bash
cd data
php ../scripts/insert.php suite_name.csv
```

#### CSV Row Types

- `addsuite`: Define a new benchmark suite
- `addbenchmark`: Add a benchmark to a suite
- `tool`: Define a placement tool
- `release`: Add a tool release/version
- `result`: Submit benchmark results
- `publication`: Add related publications

## Supported Benchmark Suites

### ICCAD04
Mixed-size placement benchmarks featuring IBM industrial circuits with various cell sizes and complexities.

### ISPD98
Classical partitioning benchmarks used for evaluating circuit partitioning algorithms.

### ISPD2020
Modern placement contest benchmarks with contemporary design challenges.

### GSRC
Floorplanning benchmarks for evaluating floorplan optimization algorithms.

## Database Schema

The system uses a normalized MySQL schema with the following main tables:

- `benchmarksuite`: Benchmark suite definitions
- `benchmark`: Individual benchmarks within suites
- `tool`: Placement tools and algorithms
- `toolrelease`: Specific versions/releases of tools
- `result`: Benchmark execution results with FOMs
- `flag_records`: Quality control flags for suspicious results

## Contributing

1. **Adding new benchmark suites**: Create CSV files following the format in `doc/formats.md`
2. **Submitting results**: Use the web interface or API endpoints
3. **Reporting issues**: Use the flagging system for suspicious results
4. **Code contributions**: Follow the existing PHP coding style

## File Formats

Detailed information about CSV file formats and data structure is available in `doc/formats.md`.

## Development

### Local Development with XAMPP

1. Install XAMPP from [apachefriends.org](https://www.apachefriends.org/)
2. Start Apache and MySQL services
3. Place project in `C:\xampp\htdocs\benchmarker\`
4. Configure database connection in `webTest/config.php`
5. Import database schema and seed data
6. Access via `http://localhost/benchmarker/webTest/`

### API Testing

Test API endpoints using the test environment:
```bash
curl http://localhost/benchmarker/webTest/api/test_env.php
```

## License

This project is designed for academic and research use in the EDA community.

## References

- Bustany et al. - "Still benchmarking after all these years" (2021)
- ISPD placement contests and benchmark suites
- ICCAD design automation conferences

## Support

For questions about benchmark formats, data submission, or technical issues, please refer to the documentation in the `doc/` directory or check the existing data examples in the `data/` directory.
