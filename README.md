# Game Configuration API

A secure, high-performance REST API for managing game configurations with enterprise-grade security features.

## Features

- **Secure API Key Authentication** with strong hashing
- **Admin Panel** with brute force protection
- **Rate Limiting** to prevent abuse
- **Input Validation** and sanitization
- **Configuration Management** with JSON support
- **Session-based Authentication** for admin panel
- **SQL Injection Protection** with prepared statements
- **XSS Protection** with proper output encoding

## Quick Start

1. Clone this repository
2. Copy `.env.example` to `.env` and configure your settings
3. Set up the database using the provided SQL files
4. Follow the [Complete Deployment & Integration Guide](COMPLETE_DEPLOYMENT_INTEGRATION_GUIDE.md)

## Documentation

- [Complete Deployment & Integration Guide](COMPLETE_DEPLOYMENT_INTEGRATION_GUIDE.md) - Everything you need to deploy and integrate
- [System Architecture](system_architecture.md) - Technical architecture details
- [Environment Configuration](.env.example) - Environment variables template

## API Endpoints

### Public API
- `GET /api/v1/config/{game_id}` - Get all configurations for a game
- `GET /api/v1/config/{game_id}/{key}` - Get specific configuration value
- `GET /api/v1/config/{game_id}?category={category}` - Get configurations by category

### Admin API (Requires Admin Session)
- `GET /api/v1/admin/games` - List all games
- `POST /api/v1/admin/games` - Create new game
- `PUT /api/v1/admin/games/{id}` - Update game
- `DELETE /api/v1/admin/games/{id}` - Delete game
- `GET /api/v1/admin/config` - List all configurations
- `POST /api/v1/admin/config` - Create new configuration
- `PUT /api/v1/admin/config/{id}` - Update configuration
- `DELETE /api/v1/admin/config/{id}` - Delete configuration

## Admin Panel

Access the admin panel at `/admin/` to manage games and configurations through a web interface.

Default credentials:
- Username: `admin`
- Password: `admin123`

**Important**: Change the default password after first login!

## Security Features

- Brute force protection (5 attempts, 15-minute lockout)
- Strong API key hashing (SHA384 + RIPEMD160)
- Session timeout management
- Input validation and sanitization
- SQL injection prevention
- XSS protection
- Rate limiting

## Performance

- Singleton database connections
- Prepared statements for all queries
- Static caching for frequently used data
- Optimized file operations
- Efficient memory usage

## License

MIT License