# Stock Comp 2026 📈

A competitive stock portfolio tracking app where participants log their monthly gains and compete on a leaderboard.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.x-4479A1?logo=mysql&logoColor=white)

## Features

- **Leaderboard** — Real-time rankings by YTD (Year-to-Date) and all-time compound returns
- **Performance Chart** — Interactive Chart.js visualization of cumulative returns (Actuals + Predictions)
- **Prediction Accuracy** — Automatically calculates and displays the difference between predicted and actual results on the Dashboard and History views.
- **Monthly Entries** — Users submit their portfolio gain % (Actual or Prediction) with optional commentary
- **History View** — Browse all entries grouped by month
- **Dark Theme** — Modern dark UI with green/red gain indicators

## Screenshots

| Dashboard | Add Entry |
|-----------|-----------|
| Leaderboard + Chart | Monthly gain submission form |

## Quick Start

### Prerequisites

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- A web server (Apache, Nginx) or PHP's built-in server

### 1. Database Setup

Run the SQL in `setup.sql` to create the required tables:

```sql
-- Run in your MySQL client
SOURCE setup.sql;
```

Or manually execute:

```sql
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#3498db',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    year INT NOT NULL,
    month INT NOT NULL,
    gain_percent DECIMAL(10,2) NOT NULL,
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_entry (user_id, year, month)
);
```

### 2. Configure Database Connection

Edit `config.php` with your MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'stockcomp');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('SITE_NAME', 'Stock Comp 2026');
```

### 3. Run the Server

Using PHP's built-in server:

```bash
php -S localhost:8000
```

Then open [http://localhost:8000](http://localhost:8000) in your browser.

## Project Structure

```
stockcomp/
├── index.php           # Main dashboard (leaderboard + chart)
├── add-entry.php       # Form to submit monthly gains
├── add-user.php        # Form to register new participants
├── history.php         # View all historical entries
├── config.php          # Database credentials & settings
├── db.php              # PDO database connection
├── setup.sql           # Database schema
├── api/
│   └── chart-data.php  # JSON endpoint for Chart.js data
└── assets/
    ├── style.css       # Dark theme styles
    └── app.js          # Chart.js initialization
```

## Usage

### Adding a Participant

1. Click **Add User** on the dashboard
2. Enter name and choose a color

### Submitting Monthly Gains

1. Click **Add Entry**
2. Select your name from the dropdown
3. Choose the month/year
4. Enter your gain percentage (e.g., `5.25` or `-2.1`)
5. Optionally add a comment about your trades

### Viewing Performance

- **Dashboard** shows the leaderboard sorted by YTD returns
- **Chart** displays cumulative performance over time
- **History** shows all entries grouped by month

## How Returns Are Calculated

Returns are **compounded monthly**:

```
YTD = ((1 + Jan%) × (1 + Feb%) × ... × (1 + Dec%)) - 1
```

For example, if you had +10% in January and +5% in February:
```
YTD = (1.10 × 1.05) - 1 = 0.155 = 15.5%
```

## How Scores are Calculated

The app features a comprehensive scoring system that rewards both performance and prediction accuracy. Points are calculated monthly:

1. **Gain Reward**: **+10 pts per 1%** monthly gain.
2. **Prediction Accuracy**: **-1 pt per 1% miss** between your prediction and actual result.
3. **Beat Market**: **+10 pts** if your gain is higher than the "Market" entry for that month.
4. **Monthly Winner**: **+10 pts** for the user with the highest gain of the month.
5. **Underdog Bonus**: **+5 pts** if the Monthly Winner was in last place on the all-time leaderboard before that month.

Scores are aggregated across all months to determine the leaderboard ranking.

## License

MIT License — use freely for your own investment competitions!

