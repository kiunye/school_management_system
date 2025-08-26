# School Management System - Administrator User Manual

## Table of Contents

1. [Getting Started](#getting-started)
2. [Dashboard Overview](#dashboard-overview)
3. [Student Management](#student-management)
4. [Academic Management](#academic-management)
5. [Financial Management](#financial-management)
6. [Communication System](#communication-system)
7. [Transport Management](#transport-management)
8. [Reports & Analytics](#reports--analytics)
9. [System Settings](#system-settings)
10. [User Management](#user-management)
11. [Data Management](#data-management)
12. [Troubleshooting](#troubleshooting)

## Getting Started

### Logging In

1. Navigate to your school's website admin area (usually `yourschool.com/wp-admin`)
2. Enter your administrator username and password
3. Click "Log In"

### First Time Setup

After logging in for the first time, you'll be guided through the initial setup:

1. **School Information**: Enter your school's basic details
2. **Academic Year**: Set up the current academic year and terms
3. **Payment Gateways**: Configure M-Pesa, Airtel Money, and other payment methods
4. **SMS Settings**: Set up Africastalking for SMS communications
5. **User Roles**: Review and customize user permissions

## Dashboard Overview

The administrator dashboard provides a comprehensive overview of your school's operations:

### Key Metrics Widgets

- **Total Students**: Current enrollment numbers
- **Active Classes**: Number of classes this term
- **Pending Payments**: Outstanding fee amounts
- **Recent Transactions**: Latest payment activities
- **Attendance Summary**: Today's attendance statistics
- **System Notifications**: Important alerts and updates

### Quick Actions

- Add New Student
- Create Class
- Generate Invoices
- Send SMS Notification
- View Reports
- System Backup

### Navigation Menu

The SMS menu in the WordPress admin sidebar provides access to all system features:

- **Dashboard**: Main overview page
- **Students**: Student management
- **Classes**: Academic class management
- **Attendance**: Attendance tracking
- **Timetables**: Schedule management
- **Fees & Invoices**: Financial management
- **Transactions**: Payment tracking
- **Notices**: Communication management
- **Transport**: Route and bus management
- **Reports**: Analytics and reporting
- **Settings**: System configuration

## Student Management

### Adding New Students

1. Navigate to **SMS > Students > Add New**
2. Fill in the required information:
   - **Personal Details**: Full name, date of birth, admission number
   - **Academic Information**: Grade level, class assignment
   - **Parent/Guardian Details**: Name, email, phone number, address
   - **Medical Information**: Allergies, medical conditions, emergency contacts
   - **Transport Information**: Route assignment (if applicable)

3. Click **Publish** to save the student record

**Note**: If you leave the admission number blank, the system will automatically generate one.

### Managing Existing Students

#### Viewing Student Records
1. Go to **SMS > Students**
2. Use the search bar to find specific students
3. Filter by grade level, class, or enrollment status
4. Click on a student's name to view their full profile

#### Editing Student Information
1. Find the student in the students list
2. Click **Edit** or click on the student's name
3. Update the necessary information
4. Click **Update** to save changes

#### Student Enrollment and Transfers
1. Open the student's profile
2. In the **Academic Information** section:
   - Change the **Current Class** to enroll in a different class
   - Update **Grade Level** for grade promotion
   - Modify **Enrollment Status** (Active, Inactive, Graduated, Transferred)

### Bulk Operations

#### Importing Students
1. Go to **SMS > Data Migration**
2. Select the **Data Import** tab
3. Choose your CSV or JSON file with student data
4. Map the columns to system fields
5. Click **Import Students**

#### Bulk Updates
1. Go to **SMS > Students**
2. Select multiple students using checkboxes
3. Choose **Bulk Actions** from the dropdown
4. Select the action (Update Class, Change Status, etc.)
5. Click **Apply**

## Academic Management

### Class Management

#### Creating Classes
1. Navigate to **SMS > Classes > Add New**
2. Enter class details:
   - **Class Name**: e.g., "Grade 5A"
   - **Grade Level**: Select from dropdown
   - **Capacity**: Maximum number of students
   - **Academic Year**: Current academic year
   - **Class Teacher**: Assign a teacher

3. Click **Publish** to create the class

#### Managing Class Enrollment
1. Go to **SMS > Classes**
2. Click on a class name to view details
3. In the **Enrolled Students** section:
   - View current enrollment count
   - Add or remove students
   - Check capacity status

### Timetable Management

#### Creating Timetables
1. Navigate to **SMS > Timetables > Add New**
2. Select the class for the timetable
3. Use the drag-and-drop interface to:
   - Add time slots
   - Assign subjects to time slots
   - Assign teachers to subjects
   - Set room/location information

4. The system will automatically detect conflicts
5. Click **Save Timetable** when complete

#### Managing Conflicts
When creating timetables, the system will alert you to conflicts:
- **Teacher Conflicts**: Same teacher assigned to multiple classes at once
- **Room Conflicts**: Same room booked for multiple classes
- **Resource Conflicts**: Shared resources double-booked

To resolve conflicts:
1. Review the conflict notification
2. Adjust the timetable to eliminate overlaps
3. Save the updated timetable

### Attendance Management

#### Setting Up Attendance
1. Ensure all classes have assigned teachers
2. Teachers can mark attendance through their dashboard
3. Monitor attendance through **SMS > Attendance**

#### Viewing Attendance Reports
1. Go to **SMS > Reports > Academic Reports**
2. Select **Attendance Report**
3. Choose filters:
   - Date range
   - Specific class or student
   - Attendance status (Present, Absent, Late)

4. Generate and download the report

## Financial Management

### Fee Structure Setup

#### Creating Fee Types
1. Navigate to **SMS > Fees > Add New**
2. Enter fee details:
   - **Fee Name**: e.g., "Tuition Fee Term 1"
   - **Amount**: Fee amount in KES
   - **Due Date**: Payment deadline
   - **Grade Levels**: Which grades this fee applies to
   - **Payment Options**: Installments allowed
   - **Penalty Settings**: Late payment penalties

3. Click **Publish** to create the fee

#### Managing Fee Categories
1. Go to **SMS > Fees > Categories**
2. Create categories like:
   - Tuition Fees
   - Transport Fees
   - Meal Fees
   - Activity Fees
   - Examination Fees

### Invoice Management

#### Generating Invoices
1. Navigate to **SMS > Invoices > Bulk Generator**
2. Select criteria:
   - **Grade Levels**: Which grades to invoice
   - **Fee Types**: Which fees to include
   - **Academic Term**: Current term
   - **Due Date**: Payment deadline

3. Click **Generate Invoices**
4. Review the generated invoices
5. Click **Send to Parents** to email invoices

#### Individual Invoice Management
1. Go to **SMS > Invoices**
2. View all generated invoices
3. Filter by status (Pending, Paid, Overdue)
4. Click on an invoice to:
   - View details
   - Send reminder
   - Record manual payment
   - Apply discounts or exemptions

### Payment Gateway Configuration

#### M-Pesa Setup
1. Go to **SMS > Settings > Payment Gateways**
2. Select **M-Pesa Configuration**
3. Enter your credentials:
   - Consumer Key
   - Consumer Secret
   - Shortcode
   - Passkey
   - Environment (Sandbox/Production)

4. Test the connection
5. Save settings

#### Airtel Money Setup
1. In **Payment Gateways**, select **Airtel Money**
2. Enter credentials:
   - Client ID
   - Client Secret
   - Merchant ID
   - Environment

3. Test and save

### Transaction Management

#### Monitoring Payments
1. Navigate to **SMS > Transactions**
2. View all payment transactions
3. Filter by:
   - Payment method (M-Pesa, Airtel Money, Cash)
   - Status (Pending, Completed, Failed)
   - Date range
   - Student or class

#### Handling Failed Payments
1. Identify failed transactions in the transactions list
2. Click on the transaction to view details
3. Options available:
   - **Retry Payment**: For technical failures
   - **Manual Verification**: For disputed transactions
   - **Refund**: For overpayments
   - **Contact Parent**: Send payment assistance

## Communication System

### Notice Management

#### Creating Notices
1. Navigate to **SMS > Notices > Add New**
2. Enter notice details:
   - **Title**: Notice headline
   - **Content**: Full notice text
   - **Priority**: Normal, High, Urgent
   - **Target Audience**: Select recipients
   - **Expiry Date**: When notice should be removed
   - **Attachments**: Upload relevant files

3. Click **Publish** to post the notice

#### Targeting Audiences
When creating notices, you can target:
- **All Users**: Everyone in the system
- **Specific Roles**: Teachers, Parents, Students
- **Grade Levels**: Specific grades only
- **Classes**: Individual classes
- **Individual Users**: Specific people

### SMS Communication

#### Setting Up SMS Service
1. Go to **SMS > Settings > SMS Configuration**
2. Enter Africastalking credentials:
   - Username
   - API Key
   - Sender ID

3. Test the connection
4. Save settings

#### Sending Bulk SMS
1. Navigate to **SMS > Communication > Send SMS**
2. Compose your message (160 characters recommended)
3. Select recipients:
   - All parents
   - Specific classes
   - Individual contacts

4. Preview the message
5. Click **Send SMS**

#### SMS Templates
Create reusable templates for common messages:
1. Go to **SMS > Communication > Templates**
2. Create templates for:
   - Attendance alerts
   - Fee reminders
   - General announcements
   - Emergency notifications

### Automated Notifications

#### Setting Up Automated SMS
1. Navigate to **SMS > Settings > Automated Notifications**
2. Configure triggers:
   - **Attendance Alerts**: When student is absent
   - **Fee Reminders**: Before due dates
   - **Payment Confirmations**: After successful payments
   - **Grade Updates**: When grades are posted

3. Customize message templates
4. Set timing and frequency
5. Save automation rules

## Transport Management

### Route Management

#### Creating Transport Routes
1. Navigate to **SMS > Transport > Routes > Add New**
2. Enter route details:
   - **Route Name**: e.g., "Route A - City Center"
   - **Stops**: List all pickup/drop-off points
   - **Timing**: Schedule for each stop
   - **Capacity**: Maximum students per route
   - **Fee**: Transport fee amount
   - **Driver Details**: Driver information

3. Click **Publish** to create the route

#### Managing Route Assignments
1. Go to **SMS > Transport > Assignments**
2. View current student-route assignments
3. To assign students:
   - Select students from the list
   - Choose the route
   - Verify capacity availability
   - Save assignments

### Bus Management

#### Adding Buses
1. Navigate to **SMS > Transport > Buses > Add New**
2. Enter bus information:
   - **Registration Number**: Vehicle registration
   - **Capacity**: Number of seats
   - **Driver Information**: Name, license, contact
   - **Route Assignment**: Which route this bus serves
   - **Maintenance Schedule**: Service dates

3. Save the bus record

## Reports & Analytics

### Financial Reports

#### Revenue Reports
1. Go to **SMS > Reports > Financial Reports**
2. Select **Revenue Report**
3. Choose parameters:
   - Date range
   - Fee categories
   - Payment methods
   - Grade levels

4. Generate the report
5. Export as PDF, Excel, or CSV

#### Outstanding Fees Report
1. Select **Outstanding Fees Report**
2. Filter by:
   - Grade level
   - Overdue period
   - Amount range

3. Use this report to:
   - Send targeted reminders
   - Plan collection strategies
   - Identify payment patterns

### Academic Reports

#### Attendance Reports
1. Navigate to **SMS > Reports > Academic Reports**
2. Select **Attendance Report**
3. Choose filters:
   - Date range
   - Class or individual student
   - Attendance patterns

4. Generate report for:
   - Parent meetings
   - Academic planning
   - Intervention strategies

#### Enrollment Reports
1. Select **Enrollment Report**
2. View statistics on:
   - Total enrollment by grade
   - New admissions
   - Transfers and withdrawals
   - Capacity utilization

### System Reports

#### User Activity Reports
1. Go to **SMS > Reports > System Reports**
2. Select **User Activity Report**
3. Monitor:
   - Login patterns
   - Feature usage
   - Data modifications
   - System performance

## System Settings

### General Settings

#### School Information
1. Navigate to **SMS > Settings > General**
2. Update:
   - School name and logo
   - Contact information
   - Address details
   - Academic calendar

#### Academic Year Configuration
1. Go to **Academic Year Settings**
2. Set:
   - Current academic year
   - Term dates
   - Holiday periods
   - Examination schedules

### User Role Management

#### Customizing Roles
1. Navigate to **SMS > Settings > User Roles**
2. For each role (Admin, Teacher, Parent, Student):
   - Review current capabilities
   - Add or remove permissions
   - Set access restrictions

#### Creating Custom Roles
1. Click **Add New Role**
2. Define:
   - Role name
   - Capabilities
   - Access levels
   - Restrictions

### Security Settings

#### Access Control
1. Go to **SMS > Settings > Security**
2. Configure:
   - Password requirements
   - Session timeouts
   - Login attempt limits
   - IP restrictions (if needed)

#### Audit Logging
1. Enable activity logging
2. Set retention periods
3. Configure alert thresholds
4. Review log reports regularly

## User Management

### Managing User Accounts

#### Creating User Accounts
1. Navigate to **Users > Add New**
2. Enter user details:
   - Username and email
   - Role assignment
   - Password (or generate automatically)
   - Profile information

3. For teachers, also set:
   - Subject specializations
   - Class assignments
   - Contact information

#### Bulk User Operations
1. Go to **SMS > Data Migration > User Import**
2. Upload CSV file with user data
3. Map fields correctly
4. Review and import users

### Parent Account Management

#### Automatic Parent Account Creation
When you add a student, the system can automatically:
1. Create a parent account using the parent's email
2. Generate a secure password
3. Send welcome email with login details
4. Link the parent to their child's records

#### Manual Parent Account Setup
1. Go to **Users > Add New**
2. Set role as "SMS Parent"
3. In the user profile, link to student records
4. Configure access permissions

## Data Management

### Backup and Restore

#### Creating Backups
1. Navigate to **SMS > Data Migration > Backup & Restore**
2. Select backup options:
   - Include database
   - Include plugin files
   - Include uploads
   - Compress backup

3. Add description
4. Click **Create Backup**

#### Restoring from Backup
1. In the **Available Backups** section
2. Select the backup to restore
3. Choose restore options
4. Click **Restore** (this will create a pre-restore backup automatically)

### Data Import/Export

#### Importing Data
1. Go to **SMS > Data Migration > Data Import**
2. Select data type (Students, Classes, etc.)
3. Upload your CSV or JSON file
4. Map fields to system fields
5. Review and import

#### Exporting Data
1. Navigate to the relevant section (Students, Classes, etc.)
2. Use the **Export** button
3. Choose format (CSV, Excel, PDF)
4. Select fields to include
5. Download the export file

### Data Validation

#### Running System Validation
1. Go to **SMS > Data Migration > Data Validation**
2. Click **Run Full Validation**
3. Review the validation report
4. Address any issues found

#### Data Cleanup
1. In the validation section, review orphaned data
2. Select cleanup options
3. Create backup before cleanup
4. Run cleanup process

## Troubleshooting

### Common Issues

#### Students Not Appearing in Class Lists
**Possible Causes:**
- Student not properly enrolled in class
- Class capacity exceeded
- Student status set to inactive

**Solutions:**
1. Check student's current class assignment
2. Verify class capacity settings
3. Confirm student enrollment status

#### Payment Gateway Not Working
**Possible Causes:**
- Incorrect API credentials
- Network connectivity issues
- Gateway service downtime

**Solutions:**
1. Verify API credentials in settings
2. Test gateway connection
3. Check gateway service status
4. Review error logs

#### SMS Not Sending
**Possible Causes:**
- Insufficient SMS credits
- Incorrect Africastalking settings
- Invalid phone numbers

**Solutions:**
1. Check SMS credit balance
2. Verify Africastalking configuration
3. Validate phone number formats
4. Test with a single recipient first

#### Slow System Performance
**Possible Causes:**
- Large database size
- Insufficient server resources
- Too many concurrent users

**Solutions:**
1. Run database optimization
2. Clear system caches
3. Check server resources
4. Consider upgrading hosting

### Getting Help

#### System Logs
1. Enable WordPress debug mode
2. Check error logs in **SMS > System > Logs**
3. Review activity logs for unusual patterns

#### Support Resources
- **Documentation**: Complete system documentation
- **Video Tutorials**: Step-by-step video guides
- **Support Forum**: Community support and discussions
- **Direct Support**: Email support for urgent issues

#### Reporting Issues
When reporting issues, include:
1. Detailed description of the problem
2. Steps to reproduce the issue
3. Screenshots or error messages
4. System information (WordPress version, PHP version)
5. Recent changes made to the system

### Best Practices

#### Regular Maintenance
1. **Weekly**: Review system reports and user activity
2. **Monthly**: Create system backups and validate data
3. **Quarterly**: Review user permissions and security settings
4. **Annually**: Update system documentation and procedures

#### Data Security
1. Regular backups (automated daily backups recommended)
2. Strong password policies
3. Regular security updates
4. User access reviews
5. Activity monitoring

#### Performance Optimization
1. Regular database cleanup
2. Image optimization
3. Cache management
4. Server resource monitoring
5. User training to reduce support load

## Conclusion

This administrator manual covers the essential functions of the School Management System. For additional help or advanced features, refer to the technical documentation or contact support.

Remember to:
- Keep regular backups
- Monitor system performance
- Train users properly
- Stay updated with system changes
- Follow security best practices

For the latest updates and additional resources, visit the system documentation portal.