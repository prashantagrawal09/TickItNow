# 🎟️ TickItNow - Movie Booking System

A comprehensive web-based movie booking platform built with PHP, MySQL, and vanilla JavaScript. Features real-time seat inventory management, user preferences, and a complete booking workflow.

![TickItNow Homepage](assets/screenshot-homepage.png)

## ✨ Features

### 🎬 Core Functionality
- **Movie Browsing** - Browse available movies with detailed information
- **Show Scheduling** - View showtimes across multiple venues and dates
- **Preference System** - Rank and manage preferred showtimes
- **Real-time Booking** - Live seat availability with inventory management
- **User Authentication** - Registration, login, and account management
- **Order History** - Track previous bookings and receipts
- **Contact System** - Customer support with ticketing

### 🚀 Technical Features
- **Real-time Inventory** - Concurrent booking protection with database locking
- **Session Management** - Secure user sessions and preference tracking
- **Email Notifications** - Booking confirmations via PHPMailer + Mailpit
- **Responsive Design** - Mobile-friendly interface with CSS Grid
- **Form Validation** - Client and server-side validation
- **Transaction Safety** - ACID compliance for booking operations

## 🛠️ Technology Stack

### Backend
- **PHP 8.1+** - Server-side logic and API endpoints
- **MySQL/MariaDB** - Database with transaction support
- **PHPMailer 7.0** - Email notifications
- **Apache/XAMPP** - Web server environment

### Frontend
- **HTML5** - Semantic markup and structure
- **CSS3** - Custom styling with CSS Grid and Flexbox
- **Vanilla JavaScript** - Interactive UI without frameworks
- **Tailwind CSS** - Utility-first CSS framework (development)

### Database Design
- **8 Active Tables** - Normalized schema with foreign key constraints
- **Real-time Inventory** - `show_inventory` table with availability tracking
- **User Preferences** - Session-based preference ranking system
- **Audit Trail** - Booking history and timestamps

## 🏗️ Project Structure

```
TickItNow/
├── 📁 api/                     # Backend API endpoints
│   ├── add_preference.php      # Add showtime to preferences
│   ├── confirm_selection.php   # Complete booking transaction
│   ├── get_schedule.php        # Fetch movie showtimes
│   ├── list_available.php      # Check seat availability
│   ├── login.php              # User authentication
│   ├── order_history.php      # Booking history
│   └── ...                    # Additional API endpoints
├── 📁 assets/                  # Frontend assets
│   ├── app.js                 # Main JavaScript (669 lines)
│   ├── styles.css             # Custom CSS (358 lines)
│   └── posters/               # Movie poster images
├── 📁 database/               # Database files
│   ├── TickItNow-2.sql       # Latest database dump
│   └── schema.sql            # Database schema
├── 📄 index.html              # Homepage
├── 📄 shows.html              # Movie gallery
├── 📄 preferences.html        # User preferences
├── 📄 available.html          # Booking confirmation
├── 📄 account.html            # User dashboard
├── 📄 db.php                  # Database configuration
└── 📄 composer.json           # PHP dependencies
```

## 🚀 Quick Start

### Prerequisites
- **XAMPP** or similar (Apache + MySQL)
- **PHP 8.1+**
- **Composer** (for PHPMailer)
- **Modern web browser**

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/prashantagrawal09/TickItNow.git
   cd TickItNow
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Start XAMPP services**
   - Start Apache (port 8000)
   - Start MySQL (port 3307)

4. **Import database**
   - Open phpMyAdmin: `http://localhost:8000/phpmyadmin`
   - Create database: `TickItNow`
   - Import: `TickItNow-2.sql`

5. **Configure database connection**
   ```php
   // db.php
   $dsn = "mysql:host=localhost;port=3307;dbname=TickItNow;charset=utf8mb4";
   ```

6. **Access the application**
   ```
   http://localhost:8000/TickItNow
   ```

## 📊 Database Schema

### Core Tables
| Table | Purpose | Records |
|-------|---------|---------|
| `users` | User registration & authentication | 2+ |
| `shows` | Movie catalog with details | 10 |
| `show_inventory` | Real-time seat availability | 2,600+ |
| `preference_items` | User showtime preferences | Variable |
| `bookings` | Booking records | 4+ |
| `booking_items` | Booking line items | 5+ |
| `contact_messages` | Support tickets | Variable |
| `schedules` | Schedule templates (for seeding) | 35 |

### Key Relationships
```sql
users (1) ──→ (∞) bookings
bookings (1) ──→ (∞) booking_items  
shows (1) ──→ (∞) show_inventory
preference_items ──→ show_inventory (availability check)
```

## 🔧 API Endpoints

### Authentication
- `POST /api/register.php` - User registration
- `POST /api/login.php` - User login
- `GET /api/logout.php` - User logout
- `GET /api/me.php` - Current user profile

### Movies & Scheduling
- `GET /api/get_show.php?id={id}` - Movie details
- `GET /api/get_schedule.php?show_id={id}` - Show schedules
- `GET /api/list_showtimes.php?show_id={id}&date={date}` - Available showtimes

### Preferences
- `POST /api/add_preference.php` - Add to preferences
- `GET /api/list_preferences.php` - List user preferences
- `POST /api/move_preference.php` - Reorder preferences
- `DELETE /api/remove_preference.php` - Remove preference

### Booking
- `GET /api/list_available.php` - Check availability
- `POST /api/confirm_selection.php` - Complete booking
- `GET /api/order_history.php` - Booking history

## 🎯 Usage Examples

### Adding a Preference
```javascript
const payload = {
  show_id: 1,
  venue_id: "inox",
  venue_name: "Orchard Cineplex A",
  start_at: "2025-11-10 19:00:00",
  ticket_class: "Premium",
  qty: 2,
  price: 15.50
};

fetch('api/add_preference.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify(payload)
});
```

### Completing a Booking
```javascript
const booking = {
  select_item_ids: [1, 2, 3],
  buyer_name: "John Doe",
  buyer_email: "john@example.com",
  buyer_phone: "81234567"
};

fetch('api/confirm_selection.php', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify(booking)
});
```

## 🔒 Security Features

- **SQL Injection Protection** - Prepared statements throughout
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