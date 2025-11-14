# 🎟️ TickItNow – Seat-first Cinema Booking

TickItNow is a XAMPP-friendly cinema booking project for EE4727-style assignments. It runs on vanilla PHP/MySQL/JS (no frameworks, no build tools) and now includes the full seat-selection flow with double-booking protection.

![TickItNow Homepage](assets/screenshot-homepage.png)

## ✨ Highlights

- **Movie gallery + show pages** with real-time seat counts per venue/date.
- **Preference cart** – users add showtimes, rank them, and proceed to seat selection.
- **Seat picker** – 10×10 hall grid rendered by `seat_selection.php`, locked by exact seat IDs.
- **Atomic bookings** – `api/confirm_selection.php` wraps everything in a transaction, deducts inventory, inserts rows into `booking_seats`, and emails a receipt.
- **Account tools** – login/register, order history, change-password flow, contact form.
- **No external tooling** – everything works by dropping the repo into `htdocs` and importing SQL.

## 🛠️ Stack

| Layer      | Details |
|------------|---------|
| Backend    | PHP 8.x (procedural), mysqli + PDO, PHPMailer + Mailpit (optional) |
| Frontend   | HTML5, CSS3 (custom), vanilla JavaScript (`assets/app.js`) |
| Database   | MySQL / MariaDB (`TickItNow` schema) with transactions + FK constraints |
| Server     | Apache via XAMPP on localhost (default ports 80/3306 or 8000/3307) |

## 📁 Project Layout (trimmed)

```
TickItNow/
├── api/
│   ├── add_preference.php      # Save show to cart (with seat IDs / labels)
│   ├── confirm_selection.php   # Final booking + transactional seat lock
│   ├── get_schedule.php        # Show_inventory windowed fetch
│   ├── list_available.php      # Review cart table
│   ├── list_preferences.php    # Preference ranking
│   ├── order_history.php       # Account page history feed
│   └── ...                     # login, register, contact, etc.
├── assets/
│   ├── app.js                  # UI logic (shows, cart, confirmation, account)
│   └── site.css                # Base styling
├── database/
│   ├── TickItNow.sql           # Primary dump
│   ├── migrations/
│   │   ├── 20250210_add_seats.sql
│   │   └── 20250215_add_schedule_refs.sql
│   └── README-db.md            # (create if you need notes)
├── seat_selection.php          # PHP seat grid + preference POST
├── confirm_booking.php         # Legacy direct booking (optional)
├── *.html                      # index, shows, available, preferences, account, etc.
└── db.php                      # PDO config (points to TickItNow)
```

## 🚀 Setup in 5 Minutes

1. **Clone / copy into XAMPP htdocs**
   ```bash
   git clone https://github.com/prashantagrawal09/TickItNow.git
   mv TickItNow /Applications/XAMPP/xamppfiles/htdocs/
   ```

2. **Create DB + import base dump**
   - Open phpMyAdmin → “New” → name it `TickItNow`.
   - Import `database/TickItNow.sql`.

3. **Run the two migrations (phpMyAdmin SQL tab)**
   - `database/migrations/20250210_add_seats.sql`
   - `database/migrations/20250215_add_schedule_refs.sql`
   > If columns already exist, comment out the duplicate `ALTER` lines (see file comments).

4. **(Optional) seed demo inventory**
   ```sql
   INSERT INTO show_inventory (show_id, venue_id, start_at, ticket_class, available_qty) VALUES
   (2,'pvr1','2025-11-14 14:00:00','Standard',110),
   (2,'pvr1','2025-11-14 20:00:00','VIP',55),
   (2,'pvr1','2025-11-15 15:10:00','Premium',85),
   (2,'pvr1','2025-11-15 21:30:00','VIP',55);
   ```

5. **Start Apache + MySQL**, browse to `http://localhost/TickItNow/`

## 🔄 Seat Selection Flow

1. User clicks “Select seats” on a showtime → `seat_selection.php?show_id=123&qty=2`.
2. PHP renders the hall (10 rows × 10 seats) using `seats` + `booking_seats` to mark sold seats.
3. On submit, JS sends `seat_ids` + `seat_labels` + `schedule_id` to `api/add_preference.php`.
4. `available.html` pulls `api/list_available.php` to review cart → `api/confirm_selection.php`.
5. Confirm API re-checks seats, inserts rows into `booking_seats`, nukes preferences, and returns JSON for `confirmation.html`.

## 📊 Schema Notes

- `seats` – master grid per hall.
- `preference_items` – now has `schedule_id`, `seat_ids`, `seat_labels`.
- `booking_seats` – `(booking_id, seat_id, schedule_id)` ensures every seat belongs to a specific showtime.
- `show_inventory` – aggregated availability for non-seat classes (Standard/Premium/VIP counts).

## 🔧 Key Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /api/get_schedule.php?show_id=2&start_days=0&end_days=4` | Windowed fetch from `show_inventory` + hall metadata |
| `POST /api/add_preference.php` | Adds/updates a preference row (with seats) |
| `GET /api/list_preferences.php` | Preference ranking table |
| `GET /api/list_available.php` | Cart / review page data |
| `POST /api/confirm_selection.php` | Finalizes booking, writes `booking_seats`, emails buyer |
| `GET /api/order_history.php` | Account page order list |

> All APIs use prepared statements with mysqli/PDO and never require npm/composer.

## ⚠️ Dev Tips

- **No Node/Composer** – delete `node_modules`, `package.json`, `composer.json`, `vendor/` if they sneak back in.
- **DB name is `TickItNow`** – `db.php` + `seat_selection.php` + `confirm_booking.php` already point there.
- **Seat data required** – if `preference_items.schedule_id = 0` you must rerun the backfill query or delete those rows before booking.
- **Sharing locally** – run `ngrok http 80` or expose Apache on your LAN (`ipconfig` shows your 10.x.x.x address).

Enjoy building on top of TickItNow! PRs that keep the no-framework rule are welcome. 😉
- **Session Management** - Secure session handling
- **Input Validation** - Client and server-side validation
- **Transaction Locking** - Race condition prevention
- **CSRF Protection** - Form token validation
- **Password Hashing** - PHP password_hash() function

## 📧 Email System

Integrated with **Mailpit** (local SMTP server) for development:
- Booking confirmations
- Contact form acknowledgments
- Development-safe email testing

## 🧪 Testing

### Manual Testing Checklist
- [ ] User registration/login flow
- [ ] Movie browsing and selection
- [ ] Preference management (add/remove/reorder)
- [ ] Availability checking
- [ ] Complete booking workflow
- [ ] Email notifications
- [ ] Concurrent booking scenarios
- [ ] Mobile responsiveness

### Database Testing
```sql
-- Test concurrent bookings
BEGIN;
SELECT * FROM show_inventory WHERE show_id=1 AND venue_id='inox' FOR UPDATE;
UPDATE show_inventory SET available_qty = available_qty - 2 WHERE id=46;
COMMIT;
```

## 📈 Performance Considerations

- **Database Indexing** - Optimized queries with proper indexes
- **Connection Pooling** - Efficient database connections
- **Session Optimization** - Minimal session data
- **CSS/JS Minification** - Ready for production optimization
- **Image Optimization** - Compressed movie posters

## 🚀 Deployment

### Production Checklist
1. **Environment Configuration**
   - Update database credentials
   - Configure production email SMTP
   - Set secure session settings

2. **Security Hardening**
   - Remove development files
   - Configure HTTPS
   - Set proper file permissions

3. **Performance Optimization**
   - Enable PHP OPcache
   - Compress static assets
   - Configure database indexing

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Create feature branch: `git checkout -b feature-name`
3. Make changes and test thoroughly
4. Commit: `git commit -m "Add feature"`
5. Push: `git push origin feature-name`
6. Create Pull Request

### Code Standards
- **PHP**: PSR-12 coding standards
- **JavaScript**: ES6+ with consistent formatting
- **SQL**: Formatted queries with proper indentation
- **CSS**: BEM methodology for naming

## 📝 License

This project is licensed under the ISC License - see the [LICENSE](LICENSE) file for details.

## 👥 Team

**Team A (Frontend)** - UI/UX, Client-side functionality
**Team B (Backend)** - API development, Database operations

## 📞 Support

For support and questions:
- **Issues**: [GitHub Issues](https://github.com/prashantagrawal09/TickItNow/issues)
- **Documentation**: This README and inline code comments
- **Contact**: Use the built-in contact form in the application

## 🔄 Version History

- **v1.0.0** - Initial release with core booking functionality
- **Latest** - Real-time inventory management and email notifications

---

⭐ **Star this repository** if you find it useful!

Built with ❤️ for modern web development education.
