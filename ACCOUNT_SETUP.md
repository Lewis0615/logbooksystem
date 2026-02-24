# User Account Creation Guide
## St. Dominic Savio College - Visitor Management System

### Quick Start - Create Admin & Guard Accounts

I've created three ways to set up your initial admin and guard user accounts:

## Method 1: Automated PHP Script (Recommended)
1. Open your web browser
2. Navigate to: `http://localhost/logbooksystem/admin/create-users.php`
3. The script will automatically create:
   - **Admin Account**: Username: `admin`, Password: `admin123`
   - **Guard Account**: Username: `guard`, Password: `guard123`
4. Follow the on-screen instructions

## Method 2: SQL Database Script
If the PHP script doesn't work:
1. Open phpMyAdmin in your browser
2. Select your `sdc_visitor_management` database
3. Go to the SQL tab
4. Copy and paste the contents of `/admin/create-users.sql`
5. Click "Go" to execute

## Method 3: User Management Interface
After creating the initial admin account:
1. Login as admin at: `http://localhost/logbooksystem/admin/login.php`
2. Go to User Management in the admin sidebar
3. Click "Create New User" to add more accounts

---

## Default Login Credentials

### Administrator Account
- **Username**: `admin`
- **Password**: `admin123`  
- **Access**: Full system access including:
  - Dashboard & Analytics
  - Check In/Out Management
  - Visitor History & Reports
  - Blacklist Management
  - Audit Trail & Logs
  - User Account Management
  - System Settings

### Guard Account  
- **Username**: `guard`
- **Password**: `guard123`
- **Access**: Limited guard functions:
  - Check In/Out Visitors
  - View Blacklist (read-only)
  - View Audit Trail (limited)

---

## Important Security Notes

⚠️ **CHANGE DEFAULT PASSWORDS IMMEDIATELY** after first login!

🔒 **Security Best Practices**:
- Use strong passwords (8+ characters, mix of letters, numbers, symbols)
- Change passwords regularly
- Delete the `create-users.php` script after creating accounts
- Only give admin access to trusted personnel
- Monitor user activity through audit trails

---

## User Roles Explained

### Administrator (`admin`)
- Full system control and configuration
- Can create/edit/delete user accounts  
- Access to all visitor data and reports
- System settings and security management

### Supervisor (`supervisor`)  
- Same as admin but typically for department heads
- Can manage visitors and view reports
- Limited user management capabilities

### Guard (`guard`)
- Basic visitor check-in/check-out operations
- View blacklist for security screening
- Limited audit trail access
- Cannot modify system settings

---

## Login URLs

- **Admin Login**: `http://localhost/logbooksystem/admin/login.php`
- **Main System**: `http://localhost/logbooksystem/`

After logging in, users will be automatically redirected to their appropriate interface based on their role.

---

## Troubleshooting

**Can't access create-users.php?**
- Make sure XAMPP/Apache is running
- Verify the database connection in `/config/database.php`
- Check that the `sdc_visitor_management` database exists

**Login not working?**
- Verify accounts were created successfully
- Check database connection
- Ensure you're using correct URLs
- Clear browser cache/cookies

**Need help?**
- Check the activity logs in admin dashboard
- Verify user table structure in database
- Ensure all config files are properly set up

---

*For technical support, check the system documentation or contact your system administrator.*