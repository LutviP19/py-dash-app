# Python Dash App (PHP + HTMX + Python Dash + PostgreSQL)

Analytics dashboard that integrates a lightweight **PHP frontend** shell with a reactive **Python Dash** analytics engine powered by a **PostgreSQL** database. 

The application utilizes **htmx** for asynchronous loading and features a **zero-latency client-side cross-filtering** mechanism: clicking a segment on the Donut Chart instantly updates the detailed transaction ledger table without firing additional database queries.

## Preview
![Dashboard](/assets/Screenshot_Dashboard.png)
![CRUD](/assets/Screenshot_CRUD.png)
![Confirm Delete](/assets/Screenshot_Confirm_Delete.png)

## 🏗️ Architecture Overview

- **Frontend Shell (`PHP 8.5 + Tailwind CSS`)**: Renders the corporate framework UI. Uses htmx to automatically fetch the dashboard fragment on page load.
- **Asynchronous Proxy (`htmx`)**: Manages non-blocking DOM swapping and passes session context parameters (`user_id`, `date_range`) securely down via iframe query strings.
- **Data Visualization Engine (`Python Dash + Plotly Express`)**: Captures URL context variables using `dcc.Location`, queries the database safely via parameterized SQLAlchemy connections, and builds responsive components.
- **Client-Side Cache (`dcc.Store`)**: Caches the database payload directly in the browser's memory, driving rapid, zero-database-hit table filtering upon user graph interactions.
- **Database Layer (`PostgreSQL 16`)**: Houses transaction ledgers optimized with composite lookup indexing structures.

---

## 📂 Project Structure

```text
py-dash-app/
├── .env                       # Production secrets (DB credentials, HMAC key, CSP origins)
├── .env.example               # Template environment file for safe version control
├── .gitignore                 # Excludes .env, .venv, __pycache__, and DB volumes
├── docker-compose.yml         # Multi-container orchestration (Postgres, Dash + Gunicorn, PHP)
├── README.md                  # Full setup, security hardening, and architecture documentation
├── schema.sql                 # PostgreSQL table DDL, composite index, and seed data
├── php/
│   ├── index.php              # Main Tailwind/htmx portal, filter form, auto-resizing iframe & CRUD modal
│   ├── dash-fragment.php      # Asynchronous htmx iframe loader with HMAC signing & cache-busting
│   └── crud-handler.php       # Session-scoped & CSRF-protected Insert/Update/Delete backend
└── python/
    ├── .venv/                 # Local isolated Python virtual environment (ignored by Git)
    ├── app.py                 # Dash microservice (Donut chart, cross-filtering, pagination, ResizeObserver)
    └── requirements.txt       # Python dependencies (dash, pandas, plotly, sqlalchemy, psycopg2, gunicorn)

```

---

## ⚡ Quick Start with Docker Compose (Recommended)

Ensure you have **Docker** and **Docker Compose** installed on your system.

### 1. Position Files
Arrange your code scripts according to the folder architecture shown above.

### 2. Configure Local Networking Bounds
Open `php/dash-fragment.php` and verify that the target address points correctly to your physical machine's IP address or host mapping rule instead of internal Docker network tags (since your host browser needs to parse the frame block directly):
```php
$dashTargetUrl = "http://YOUR_MACHINE_IP:8050/?user_id={$userId}&date_range={$dateRange}";
```

### 3. Spin Up the Container Infrastructure
From the root directory of your project, execute:
```bash
docker-compose up -d
```

Docker will automatically create the PostgreSQL volume storage, run the schema modifications, install python package matrices, and bring the services online.

### 4. Verification Check
- **PHP Frontend:** Access `http://localhost` or `http://localhost/index.php` 
- **Dash Engine (Standalone):** Monitor directly at `http://localhost:8050`
- **PostgreSQL Database:** Running locally on port `5432`

---

## 🛠️ Manual Development Setup (Without Docker)

If you prefer executing the applications natively on your machine:

### 1. Database Setup
Execute the contents of `schema.sql` inside your local PostgreSQL manager. Ensure your local server credentials match the parameters inside `python/app.py`:
```python
DB_URI = "postgresql+psycopg2://postgres:mysecretpassword@localhost:5432/my_dash_db"
```

### 2. Initialize and Run Python Dash Service
Open a new terminal session in the `python/` directory:
```bash
# Create virtual environment
python -m venv .venv

# Activate environment
# On macOS/Linux:
source .venv/bin/activate
# On Windows (PowerShell):
.venv\Scripts\Activate.ps1

# Install dependency array
pip install -r ../requirements.txt

# Start Dash microservice listener
python app.py

gunicorn --workers 4 --bind 0.0.0.0:8050 app:server

# Exit the virtual environment
deactivate


```

### 3. Serve the PHP Frontend
Place the contents of the `php/` folder inside your local web server document root directory (e.g., Apache `htdocs`, Nginx `www`, or standard built-in server loops: `php -S localhost:80`).

---

## 📊 Interaction Reference Guide

1. **Automatic Loading:** Navigating to `index.php` triggers an automatic `hx-trigger="load"` payload event. An indigo-colored CSS animated spinner tracks execution while database pipelines process.
2. **Isolating Categories:** Click directly on any colored segment slice inside the **Donut Chart**. The transaction table immediately drops all other lines to isolate records for that product category alone.
3. **Resetting Selections:** Double-click anywhere on the empty gray/blank structural background area of the Donut Chart canvas. The frontend cache resets and automatically expands the table grid view to show all original data parameters once more.

---

## 🛑 Troubleshooting

### Exception: `ModuleNotFoundError: No module named 'psycopg'`
- **Cause:** Newer versions of SQLAlchemy (2.0+) default to the `psycopg` (v3) driver engine block rather than `psycopg2`.
- **Solution:** Ensure your connection URI explicitly states the driver dialect string (`postgresql+psycopg2://...`) as configured in our production samples.

### Exception: `suppress_callback_exceptions`
- **Cause:** Dash components generated dynamically inside callbacks aren't available in the baseline tree layout on page boot.
- **Solution:** The project instance initialization explicitly includes `suppress_callback_exceptions=True` to instruct Dash to skip startup validation constraints safely.

---

## 📝 Management Commands

- **Stop Infrastructure:** `docker-compose down`
- **Wipe and Reset Volumes (Fresh DB):** `docker-compose down -v`
- **Follow Log Outputs:** `docker-compose logs -f dash-engine`
