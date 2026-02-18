# iOne Resources Inc. Ticketing System

A comprehensive help desk ticketing system built with Laravel and Tailwind CSS, designed for iOne Resources Inc. The system provides both client-side and admin-side interfaces for efficient ticket management.

## Features

### Client Features
- 🎫 Create and manage support tickets
- 📱 Responsive dashboard with ticket statistics
- 💬 Reply to tickets and add attachments
- ⭐ Rate resolved tickets
- 🔍 Search and filter tickets
- 📎 File attachments support

### Admin Features
- 📊 Comprehensive admin dashboard with analytics
- 👥 User management (clients and admins)
- 🎯 Ticket assignment and status management
- 📈 Priority management and due date tracking
- 💼 Internal notes and replies
- 📋 Advanced filtering and search capabilities
- 📊 Reporting and statistics

### General Features
- 🔐 Secure authentication system
- 🎨 Modern, responsive UI with Tailwind CSS
- 📱 Mobile-friendly design
- 🏷️ Category-based ticket organization
- 🔔 Status tracking and notifications
- 📁 File attachment system

## Technology Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Tailwind CSS, Alpine.js
- **Database**: MySQL/MariaDB
- **Authentication**: Laravel built-in authentication
- **File Storage**: Laravel Storage (Local/Cloud)

## Installation Instructions

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and NPM
- MySQL/MariaDB database
- Web server (Apache/Nginx)

### Step 1: Install Dependencies

1. **Install Composer** (if not already installed):
   - Download from https://getcomposer.org/download/
   - Follow the installation guide for your operating system

2. **Install Node.js** (if not already installed):
   - Download from https://nodejs.org/
   - Choose the LTS version

### Step 2: Set Up the Project

1. **Navigate to the project directory**:
   ```bash
   cd C:\Users\iOne5\Desktop\app\Ticketing\ione-ticketing-system
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install Node.js dependencies**:
   ```bash
   npm install
   ```

4. **Create environment file**:
   ```bash
   copy .env.example .env
   ```

5. **Generate application key**:
   ```bash
   php artisan key:generate
   ```

### Step 3: Database Configuration

1. **Create a MySQL database** named `ione_ticketing`

2. **Update the `.env` file** with your database credentials:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ione_ticketing
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   ```

3. **Run database migrations**:
   ```bash
   php artisan migrate
   ```

4. **Seed the database with sample data**:
   ```bash
   php artisan db:seed
   ```

### Step 4: Build Assets

1. **Compile CSS and JavaScript**:
   ```bash
   npm run build
   ```

   For development with hot reloading:
   ```bash
   npm run dev
   ```

### Step 5: Storage Setup

1. **Create storage link**:
   ```bash
   php artisan storage:link
   ```

### Step 6: Run the Application

1. **Start the Laravel development server**:
   ```bash
   php artisan serve
   ```

2. **Access the application**:
   - Open your browser and go to `http://localhost:8000`

## Default User Accounts

After seeding, you can log in with these default accounts:

### Admin Account
- **Email**: admin@ioneresources.com
- **Password**: password
- **Role**: Admin (full access)

### Additional Admin Account
- **Email**: support@ioneresources.com
- **Password**: password
- **Role**: Admin

### Client Accounts
- **Email**: client@ioneresources.com
- **Password**: password
- **Role**: Client

- **Email**: jane@ioneresources.com
- **Password**: password
- **Role**: Client

## Project Structure

```
ione-ticketing-system/
├── app/
│   ├── Http/Controllers/
│   │   ├── AuthController.php
│   │   ├── Admin/
│   │   │   ├── DashboardController.php
│   │   │   └── TicketController.php
│   │   └── Client/
│   │       ├── DashboardController.php
│   │       └── TicketController.php
│   └── Models/
│       ├── User.php
│       ├── Ticket.php
│       ├── Category.php
│       ├── TicketReply.php
│       └── Attachment.php
├── database/
│   ├── migrations/
│   └── seeders/
├── resources/
│   ├── views/
│   │   ├── auth/
│   │   ├── admin/
│   │   ├── client/
│   │   └── layouts/
│   ├── css/
│   └── js/
└── routes/
    └── web.php
```

## Database Schema

### Users Table
- id, name, email, phone, department, role, password, timestamps

### Tickets Table
- id, ticket_number, subject, description, priority, status, user_id, assigned_to, category_id, due_date, resolved_at, closed_at, timestamps

### Categories Table
- id, name, description, color, is_active, timestamps

### Ticket Replies Table
- id, ticket_id, user_id, message, is_internal, timestamps

### Attachments Table
- id, filename, original_filename, file_path, mime_type, file_size, attachable_type, attachable_id, timestamps

## Configuration

### Mail Configuration (Optional)
To enable email notifications, configure your mail settings in the `.env` file:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@ioneresources.com
MAIL_FROM_NAME="iOne Resources Ticketing"
```

### File Upload Configuration
The system supports file attachments up to 10MB by default. To modify this:

1. Update `php.ini`:
   ```ini
   upload_max_filesize = 10M
   post_max_size = 10M
   ```

2. Update the validation rules in the controllers if needed.

## Deployment

### Production Deployment

1. **Set environment to production**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```

2. **Optimize the application**:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

3. **Set proper file permissions**:
   ```bash
   chmod -R 755 storage
   chmod -R 755 bootstrap/cache
   ```

### Web Server Configuration

#### Apache
Create a `.htaccess` file in the public directory (should already exist).

#### Nginx
Configure your Nginx virtual host to point to the `public` directory.

## Usage Guide

### For Clients
1. Register or log in to the system
2. Create a new ticket by clicking "New Ticket"
3. Fill in the ticket details and attach files if needed
4. Track ticket progress on the dashboard
5. Reply to tickets and provide additional information
6. Rate resolved tickets

### For Admins/Agents
1. Log in with admin credentials
2. View the admin dashboard for system overview
3. Manage tickets from the "All Tickets" page
4. Assign tickets to available admins
5. Update ticket status and priority
6. Reply to tickets (internal or public)
7. Set due dates and track performance

## Troubleshooting

### Common Issues

1. **Composer not found**:
   - Install Composer from https://getcomposer.org/

2. **Permission errors**:
   ```bash
   chmod -R 775 storage
   chmod -R 775 bootstrap/cache
   ```

3. **Database connection errors**:
   - Check your database credentials in `.env`
   - Ensure MySQL service is running

4. **CSS not loading**:
   - Run `npm run build` or `npm run dev`
   - Check if Vite is running for development

## Support

For technical support or questions about the ticketing system:
- Create a ticket within the system (for general issues)
- Contact the development team for system-level issues

## License

This project is proprietary software developed for iOne Resources Inc.

---

**iOne Resources Inc. Ticketing System v1.0**
Built with ❤️ using Laravel and Tailwind CSS
