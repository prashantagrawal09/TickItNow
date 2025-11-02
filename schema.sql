-- TickItNow Database Schema
CREATE DATABASE IF NOT EXISTS tickitnow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tickitnow;

-- Users
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL
);

-- Movies / Shows
CREATE TABLE movies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  synopsis TEXT,
  genre VARCHAR(50),
  rating VARCHAR(10),
  poster_url VARCHAR(255)
);

-- Venues
CREATE TABLE venues (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  total_seats INT NOT NULL
);

-- Showtimes
CREATE TABLE showtimes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  movie_id INT NOT NULL,
  venue_id INT NOT NULL,
  start_at DATETIME NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  seats_available INT NOT NULL,
  FOREIGN KEY (movie_id) REFERENCES movies(id),
  FOREIGN KEY (venue_id) REFERENCES venues(id)
);

-- Bookings
CREATE TABLE bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  booked_at DATETIME NOT NULL,
  total_amount DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Booking items
CREATE TABLE booking_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  showtime_id INT NOT NULL,
  tickets INT NOT NULL,
  price_each DECIMAL(8,2) NOT NULL,
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (showtime_id) REFERENCES showtimes(id)
);

-- Contact messages
CREATE TABLE contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  topic VARCHAR(80) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(40) NOT NULL,
  booking_ref VARCHAR(40),
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL
);

CREATE TABLE shows (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  synopsis TEXT,
  genre VARCHAR(50),
  rating VARCHAR(10),
  duration VARCHAR(20),
  poster_url VARCHAR(255)
);

CREATE TABLE schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  show_id INT NOT NULL,
  venue VARCHAR(120) NOT NULL,
  start_at DATETIME NOT NULL,
  price DECIMAL(8,2) NOT NULL,
  FOREIGN KEY (show_id) REFERENCES shows(id)
);