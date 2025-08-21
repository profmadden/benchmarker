# Benchmarker PHP Viewer

This connects a **PHP page** to a **MySQL database** (`suites_db`) using **PDO** and displays the latest 200 benchmark results in a simple HTML table.

---

## What the PHP code does

- **Database connection**
  - Uses PHP **PDO** with DSN:
    ```php
    new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass);
    ```
  - Host: `127.0.0.1` (localhost)
  - Port: `3306` (default XAMPP MySQL port)
  - User: `root`
  - Password: empty by default in XAMPP or sometimes `root`
  - Database: `suites_db`

- **Query**
  - Selects the latest 200 rows from the `result` table
  - Joins with `tool`, `toolrelease`, `benchmarksuite`, and `benchmark` tables to display human-readable names

- **Display**
  - Renders results in an HTML `<table>` with columns:
    - `Result ID`, `Date`, `Tool`, `Release`, `Suite`, `Benchmark`, `FOM1–FOM4`, and `URL`
  - Adds simple CSS for clean styling and hover effects

---

## How to connect PHP to MySQL using XAMPP (Windows)

1. **Install XAMPP**
   - Download and install from [apachefriends.org](https://www.apachefriends.org/).
   - Launch the **XAMPP Control Panel**.

2. **Start services**
   - Start **Apache** (for PHP) and **MySQL** (mysqli by deafult in php admin).
   - Or can use mySqlWorkbench or normal mysql to this php (Make sure it run on port 3306)

3. **Create the database**
   - Go to <http://localhost/phpmyadmin>.
   - Create a schema named `suites_db`.
   - Import `schema.sql` (and `seed.sql` if you want test data).

4. **Place PHP files**
   -  ``` C:\xampp\htdocs\benchmarker\ ```
     Example:
     ```
     C:\xampp\htdocs\benchmarker\index.php
     C:\xampp\htdocs\benchmarker\config.php
     ```

5. **Configure database connection**
   - Edit `config.php` with your DB details:
     ```php
     $host = '127.0.0.1';
     $port = '3306';   // check if MySQL runs on 3306 or 3307 in XAMPP
     $user = 'root';
     $pass = '';       // empty by default in XAMPP
     $db   = 'suites_db';
     ``
