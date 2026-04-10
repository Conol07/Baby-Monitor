# 👶 BabyWatch — Baby Noise Monitoring System

A complete PHP/MySQL web application for monitoring baby sounds and needs in real-time.

---

## 🚀 Quick Setup

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB
- Apache/Nginx with mod_rewrite (XAMPP, WAMP, Laragon, etc.)

### Installation Steps

1. **Copy project** to your web server root:
   ```
   XAMPP: C:/xampp/htdocs/baby-monitor/
   Linux: /var/www/html/baby-monitor/
   ```

2. **Create the database** — Import the SQL file:
   ```bash
   mysql -u root -p < database.sql
   ```
   Or via phpMyAdmin: Create DB `baby_monitor`, then import `database.sql`

3. **Configure** `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');       // your MySQL user
   define('DB_PASS', '');           // your MySQL password
   define('DB_NAME', 'baby_monitor');
   define('APP_URL', 'http://localhost/baby-monitor');
   ```

4. **Visit**: `http://localhost/baby-monitor`

---

## 🔑 Demo Credentials

| Role        | Email              | Password   |
|-------------|-------------------|------------|
| Parent (Admin) | parent@demo.com | demo1234   |
| Babysitter  | sitter@demo.com   | demo1234   |

---

## 📁 Project Structure

```
baby-monitor/
├── index.php                  # Login page
├── logout.php                 # Logout handler
├── database.sql               # DB schema + demo data
├── includes/
│   └── config.php             # DB, auth, helper functions
├── admin/
│   └── dashboard.php          # Parent/Admin dashboard
├── babysitter/
│   └── dashboard.php          # Babysitter dashboard
└── api/
    ├── simulate_sound.php     # POST: Log simulated sound event
    ├── poll_events.php        # GET:  Poll for new events (AJAX)
    ├── resolve_event.php      # POST: Mark event as resolved
    ├── update_prefs.php       # POST: Save notification preferences
    ├── update_baby.php        # POST: Update baby profile
    └── log_feeding.php        # POST: Log a feeding session
```

---

## ✨ Features

### Parent Dashboard
- **Live Sound Monitor** with animated waveform visualizer
- **Simulate Events**: Hungry / Sleepy / Discomfort / Happy / Random
- **Auto-Simulate**: Fires random events every 5 seconds for testing
- **Real-time Toast Notifications** with sound alerts (Web Audio API)
- **Activity Logs** with filter by type + resolve actions
- **Analytics Dashboard** with doughnut chart and 7-day timeline
- **Baby Profile Management** (name, DOB, weight, feeding schedule)
- **Feeding Log** tracker
- **Notification Preferences** (toggles per alert type, volume)

### Babysitter Dashboard
- **Baby Status Overview** with active alert highlight
- **Alert Center** with resolve button
- **Activity Log** timeline
- **Auto-polls** server every 8s for new events
- **Limited access** — cannot edit baby profile or view analytics

---

## 🔊 Sound Classification Types

| Type        | Emoji | Color    | Alert Triggered |
|-------------|-------|----------|-----------------|
| Hungry      | 🍼    | Orange   | Yes             |
| Sleepy      | 😴    | Purple   | Yes             |
| Discomfort  | 😣    | Red      | Yes             |
| Happy       | 😊    | Green    | No (optional)   |
| Burp        | 💨    | Cyan     | No              |
| Unknown     | ❓    | Gray     | No              |

---

## 🔮 Future Enhancements

- **IoT Integration**: Replace simulator with WebSocket stream from microcontroller (Raspberry Pi / ESP32)
- **Machine Learning**: Train a CNN model on Dunstan Baby Language dataset
- **Push Notifications**: Browser push / FCM mobile alerts
- **Multi-baby Support**: Track multiple babies per account
- **Export**: PDF/CSV export of activity logs
- **Email Alerts**: SMTP integration for critical events
- **Video Feed**: Integration with IP camera streams

---

## 🛡️ Security Notes

- Passwords hashed with `password_hash()` (bcrypt, cost 12)
- All DB queries use PDO prepared statements
- Role-based access control (admin vs babysitter)
- Session-based authentication with `httponly` cookies
- Input sanitized via `htmlspecialchars` + `strip_tags`

---

## 📞 Support

Built with PHP 8, MySQL, Bootstrap-free vanilla CSS, Chart.js, and Font Awesome.
