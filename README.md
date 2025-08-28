# Camagru - Photo Sharing Application

A modern photo sharing application built with PHP, MySQL, and vanilla JavaScript.

## Features

- **User Authentication**: Complete signup/signin system with sessions
- **Database**: MySQL/MariaDB with proper foreign key relationships
- **Modern UI**: Responsive design with beautiful gradients
- **Session Management**: Secure user sessions with automatic authentication checks

## Database Schema

- **users**: User accounts with authentication and preferences
- **images**: User-uploaded photos with metadata
- **likes**: User likes on images
- **comments**: User comments on images

## Quick Start

1. **Start the application:**
   ```bash
   docker-compose up -d
   ```

2. **Access the application:**
   - Web interface: http://localhost:8080
   - Database: localhost:3306 (root/root)

3. **Test database connection:**
   ```bash
   curl http://localhost:8080/api/test-db
   ```

## API Endpoints

- `POST /api/signup` - User registration
- `POST /api/signin` - User authentication
- `POST /api/logout` - User logout
- `GET /api/check-auth` - Check authentication status
- `GET /api/test-db` - Test database connection

## Authentication Flow

1. **Signup**: Users create accounts with username, email, and password
2. **Signin**: Users authenticate with username/email and password
3. **Session**: Successful login creates a PHP session
4. **Dashboard**: Logged-in users see their profile information
5. **Logout**: Users can end their session

## Security Features

- Password hashing with `password_hash()`
- Session-based authentication
- SQL injection prevention with prepared statements
- Input validation and sanitization

## Development

The application automatically initializes the database schema on first run using the `init.sql` script mounted in the MariaDB container.

## File Structure

```
├── docker-compose.yaml    # Docker services configuration
├── init.sql              # Database initialization script
├── nginx.conf            # Nginx configuration
├── php.Dockerfile        # PHP container configuration
├── public/               # Frontend files
│   ├── index.html       # Main application page
│   ├── app.js           # Frontend JavaScript
│   └── style.css        # Application styling
└── server/               # Backend PHP files
    └── index.php         # Main router, database, and all API endpoints
```
