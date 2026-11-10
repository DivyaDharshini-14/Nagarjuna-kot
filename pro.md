NAGARJUNA – RESTAURANT KOT MANAGEMENT SYSTEM
Final Software Requirements & Development Scope
Project: Nagarjuna Restaurant Management System
Application Type: Web Application + Mobile Application + Kitchen Display System (KDS)
Core Module: Kitchen Order Ticket (KOT) Management
Backend: Laravel / PHP
Database: MySQL
Mobile Application: Flutter
Real-time Communication: WebSockets / Laravel Reverb

1. PROJECT OVERVIEW
The objective is to develop a centralized Restaurant KOT Management System for Nagarjuna, allowing restaurant staff to take customer orders through a mobile application and automatically distribute individual food items to their respective kitchen display screens.
The system should support multiple companies, branches, floors, tables and kitchens.
Each kitchen will have a dedicated TV display URL. When a waiter submits an order, the system should automatically identify the assigned kitchen for each dish and display the corresponding items on that kitchen's TV.
The system will also maintain prepared-dish stock. Stock should automatically decrease when an order is submitted successfully.
Important: Nagarjuna already uses existing billing software. We are not developing billing functionality. We will provide APIs to integrate our KOT application with their existing billing software.
2. APPLICATION COMPONENTS
The project will contain three primary applications.
A. Web Application – Admin & Management
Used by administrators and management to configure and manage:
Companies and branches
Kitchens and kitchen assignments
Floors and tables
Menu categories and dishes
Users, roles and permissions
Kitchen stock
Orders and KOTs
Reports and settings
External billing software API integration
B. Mobile Application – Waiter / Captain
Used by restaurant staff to:
Log in
Select floor and table
View menu categories
Select dishes and quantities
Add special instructions
Submit customer orders
View order status
Add additional items to existing orders
View kitchen-ready notifications
C. Kitchen Display System – TV
Each kitchen will have a dedicated browser-based display.
The display should:
Show only the orders assigned to that kitchen.
Receive new orders in real time.
Display table number, KOT number, dishes and quantities.
Allow kitchen staff to update preparation status.
Show order waiting time.
Notify staff about new and cancelled items.
Display ready orders separately.
Example URLs:
/branch1/biryani
/branch1/chinese

The URLs should be dynamically linked to the branch and kitchen configuration rather than hardcoded.
3. MASTER MODULES
3.1 Company Master
Fields:
Company Name
Company Code
Address
Contact Number
Email
GST Number
Status
3.2 Branch Master
Fields:
Branch Name
Branch Code
Company
Address
Contact Number
Status
One company can have multiple branches.
3.3 Kitchen Master
Fields:
Kitchen Name
Kitchen Code
Company
Branch
Kitchen Type
Display URL / Display Identifier
Status
Examples:
Biryani Kitchen
Chinese Kitchen
Starters Kitchen
Main Kitchen
One branch can have multiple kitchens.
3.4 Floor Master
Fields:
Floor Name
Branch
Description
Status
3.5 Table Master
Fields:
Table Number / Name
Branch
Floor
Seating Capacity
Table Status
Active / Inactive
Table statuses:
Available
Occupied
Reserved (optional)
Cleaning (optional)
3.6 Category Master
Create categories to organize restaurant dishes.
Examples:
Biryani
Chinese
Starters
Main Course
Beverages
Desserts
Fields:
Category Name
Category Code
Description
Display Order
Status
3.7 Menu Type Master
Examples:
Veg
Non-Veg
Egg
Beverages
Fields:
Type Name
Description
Status
3.8 Menu / Dish Master
Fields:
Dish Name
Dish Code
Category
Menu Type
Company / Branch applicability
Selling Price (for reference and billing API integration)
Assigned Kitchen
Dish Image
Description
Preparation Instructions
Display Order
Stock Tracking Enabled
Active / Inactive
Each dish must be mapped to its appropriate kitchen.
Example:
Dish
Category
Kitchen
Chicken Biryani
Biryani
Biryani Kitchen
Mutton Biryani
Biryani
Biryani Kitchen
Veg Fried Rice
Chinese
Chinese Kitchen
Chicken Noodles
Chinese
Chinese Kitchen
Gobi Manchurian
Starters
Starters Kitchen

Support configurable kitchen mapping in case a dish needs to be prepared by more than one kitchen.
4. USER, ROLE & PERMISSION MANAGEMENT
User Master
Fields:
User Name
Employee Code
Mobile Number
Email
Password
Company
Branch
Role
Designation
Status
Suggested Roles
Super Admin
Company Admin
Branch Manager
Restaurant Manager
Captain / Waiter
Kitchen Manager
Kitchen Staff
Permission Management
Implement module-wise permissions:
View
Add
Edit
Delete
Approve
Cancel
Export
Stock Adjustment
Permissions should be configurable by role.
Users should only access the companies, branches and kitchens assigned to them.
5. MOBILE APPLICATION – ORDER MANAGEMENT
5.1 Login
Users log in using their registered credentials.
Access should be controlled by company, branch and role.
5.2 Floor & Table Selection
Display the restaurant floor layout or a table list.
Show the current status of each table.
Allow the waiter to select an available table and open a customer order.
5.3 Menu Display
Display menu items category-wise.
Features:
Search dishes.
Filter by category.
Display dish images.
Display selling price.
Show available stock.
Disable sold-out dishes.
Display Veg / Non-Veg indicators.
5.4 Add Items to Order
The waiter should be able to:
Select dishes.
Enter quantities.
Add special instructions.
Add item-level notes.
Review the complete order.
Submit the order.
Examples of special instructions:
Less spicy.
Extra spicy.
No onion.
No oil.
5.5 Submit Order
When the waiter submits an order:
Generate a unique order number.
Save the main order information.
Save individual order items.
Identify the kitchen assigned to each item.
Automatically split items into kitchen-wise KOT tickets.
Update prepared-dish stock according to the stock rules.
Push the KOT to the respective kitchen display in real time.
Return the order confirmation to the mobile application.
One customer order may generate multiple KOT tickets.
5.6 Additional Orders
If a customer adds more items after the initial submission:
Add the new items to the existing table order.
Generate additional KOT tickets for the newly added items.
Do not replace or duplicate previously submitted KOT items.
Update stock accordingly.
5.7 Order Status
Display the status of each item:
New
Accepted
Preparing
Ready
Served
Cancelled
The waiter should receive real-time updates when kitchen staff mark dishes as Ready.
6. KOT MANAGEMENT & KITCHEN DISPLAY SYSTEM
6.1 Automatic Kitchen Routing
The system must automatically route each dish based on its kitchen mapping.
Example:
Customer order:
Chicken Biryani – 2
Veg Fried Rice – 1
Gobi Manchurian – 1
System output:
Biryani Kitchen:
Chicken Biryani – 2
Chinese Kitchen:
Veg Fried Rice – 1
Starters Kitchen:
Gobi Manchurian – 1
Each kitchen receives only its assigned items.
6.2 Kitchen Display Screen
Each kitchen TV should display:
KOT Number
Order Number
Table Number
Dish Name
Quantity
Special Instructions
Order Received Time
Waiting Duration
Current Status
6.3 Kitchen Actions
Kitchen staff should be able to:
Accept Order
Start Preparing
Mark Ready
View Completed Orders
View Cancelled Items
6.4 Kitchen Display Layout
Separate orders into:
New Orders
Preparing Orders
Ready Orders
Use clear visual indicators for different statuses.
New orders should be visually highlighted, and a sound notification should play when a new KOT arrives.
6.5 Real-time Functionality
Use WebSocket-based real-time communication.
No manual refresh should be required.
If the TV loses connectivity, show a connection warning and reconnect automatically. Pending orders should be retrieved when connectivity is restored.
6.6 Kitchen Display Security
Each display should be registered or configured against a specific branch and kitchen.
Only authorized displays should be able to access their assigned kitchen orders.
7. PREPARED DISH STOCK MANAGEMENT
Scope: Prepared Dish Inventory Only
Raw material inventory and ingredient-level stock deductions are not included in this phase.
7.1 Kitchen Stock In
Kitchen staff should be able to enter the available prepared-dish quantities.
Example:
Dish
Opening Stock
Stock Added
Chicken Biryani
20
30
Mutton Biryani
10
10
Veg Fried Rice
15
20

7.2 Automatic Stock Deduction
When a customer order is submitted successfully, the ordered quantity must automatically reduce from the respective dish stock.
Example:
Chicken Biryani available stock: 50
Customer orders: 2
New available stock: 48
7.3 Stock Validation
Before submitting an order, validate the available stock.
Example:
Available quantity: 3
Requested quantity: 5
System should:
Display an insufficient stock warning.
Prevent order submission for the unavailable quantity.
Show the latest available quantity.
Allow the waiter to modify the order.
Negative stock should not be allowed.
7.4 Stock Adjustment
Authorized kitchen staff should be able to:
Add stock.
Reduce stock.
Record wastage.
Record damaged / spoiled dishes.
Correct physical stock differences.
Every adjustment must record the user, date, time, quantity and reason.
7.5 Stock Transaction History
Maintain a complete transaction history.
Transaction types:
Opening Stock
Stock In
Order Deduction
Order Cancellation Reversal
Wastage
Manual Adjustment
7.6 Dish Availability
Kitchen staff should be able to mark dishes as:
Available
Temporarily Unavailable
Sold Out
Changes should immediately reflect in the waiter mobile application.
7.7 Important Stock Rules
Stock must be deducted only once per successfully submitted order item.
Repeated API requests or retries must not deduct stock twice.
Order cancellation must reverse stock only according to the cancellation and preparation rules.
If a dish is already prepared or dispatched, the system should not automatically return it to available stock.
All stock movements must be recorded in the transaction history.
Stock deduction and order creation should be handled transactionally to prevent inconsistent data.
8. ORDER CANCELLATION & MODIFICATION
Implement controlled cancellation functionality.
Requirements:
Cancel individual dishes.
Cancel complete orders.
Mandatory cancellation reason.
Approval permission for authorized users.
Send cancellation updates to the relevant kitchen display.
Maintain cancellation history.
Apply stock reversal only when permitted by the preparation status and cancellation policy.
If an item has already been prepared, it should be recorded as wastage or handled according to the restaurant's approved policy.
9. BILLING SOFTWARE API INTEGRATION
Important: Billing is not part of our development scope.
Nagarjuna already has existing billing software.
We need to develop secure APIs so their billing software can receive the required order information from our KOT application.
9.1 Integration Requirements
Our application should expose REST APIs to provide:
Order Number
Branch Code
Table Number
Order Date and Time
Waiter Details
Dish Code
Dish Name
Quantity
Selling Price
Item Instructions
Kitchen Details
Item Preparation Status
Order Status
Cancellation Details
9.2 Suggested API Endpoints
Method
Endpoint
Purpose
POST
/api/v1/orders
Create an order, if required by the integration
GET
/api/v1/orders/{id}
Retrieve order details
GET
/api/v1/orders
Retrieve orders using filters
GET
/api/v1/branches
Retrieve branch information
GET
/api/v1/menu-items
Retrieve menu information
GET
/api/v1/orders/{id}/kot
Retrieve kitchen ticket details
GET
/api/v1/orders/{id}/status
Retrieve order and item statuses
POST
/api/v1/orders/{id}/cancel
Request authorized order cancellation

The final endpoint list and direction of data exchange must be confirmed with Nagarjuna's existing billing software provider.
9.3 Integration Security
API authentication using secure API credentials or tokens.
Branch-level access control.
Request validation.
Standard JSON responses.
Error handling.
API activity logs.
Duplicate request prevention using unique request identifiers.
API documentation (Swagger / OpenAPI).
9.4 Integration Clarification
Before implementation, obtain the following from Nagarjuna:
Existing billing software name and provider.
API documentation.
Authentication method.
Whether the billing software will pull order information or our system must push it.
Required order and item fields.
Dish code and branch code mapping.
Cancellation and modification synchronization rules.
Test environment and API credentials.
Do not assume the existing billing software supports an API until its provider confirms it.
10. REPORTS & DASHBOARD
Dashboard
Display:
Total orders today.
Active orders.
Preparing orders.
Ready orders.
Completed orders.
Cancelled orders.
Branch-wise order count.
Kitchen-wise pending orders.
Reports
Order Report
KOT Report
Kitchen-wise Order Report
Dish-wise Order Quantity Report
Stock In Report
Stock Deduction Report
Stock Adjustment Report
Wastage Report
Cancellation Report
Kitchen Preparation Time Report
User Activity Report
API Integration Logs
Filters should include date, company, branch, kitchen, order number and status wherever applicable.
11. SETTINGS
General Settings
Company and Branch Settings
KOT Number Configuration
Order Number Configuration
Kitchen Display Configuration
Notification and Sound Settings
Stock Management Settings
Cancellation Approval Settings
API Configuration
Device / Kitchen Display Registration
12. DATABASE DESIGN
The development team should prepare a normalized relational database structure.
Suggested tables:
Organization
companies
branches
Restaurant Configuration
kitchens
floors
restaurant_tables
Menu
categories
menu_types
menu_items
kitchen_menu_items
Users & Security
users
roles
permissions
role_permissions
user_roles
Orders & KOT
orders
order_items
kot_tickets
kot_items
order_status_history
item_cancellations
Inventory
kitchen_stock
stock_transactions
System
kitchen_displays
api_clients
api_logs
audit_logs
settings
Every applicable operational table should have company_id and branch_id to ensure multi-company and multi-branch data separation.
Use proper foreign keys, indexes, timestamps and transaction handling.
13. NON-FUNCTIONAL REQUIREMENTS
Responsive web application.
Mobile-friendly interface.
Fast kitchen screen updates.
Secure authentication and authorization.
Branch-wise data isolation.
Real-time communication.
Automatic reconnection for kitchen displays.
Reliable stock transaction handling.
Duplicate order prevention.
Audit logs for critical operations.
API documentation.
Database backup and recovery.
Secure device registration.
Scalable architecture for additional branches and kitchens.
14. DEVELOPMENT PHASES
Phase
Modules
Deliverable
1
Architecture, login, company, branch, kitchen, user, role and permissions
Application foundation
2
Floors, tables, categories, menu types and menu items
Master management
3
Mobile app, table selection, menu and order submission
Waiter ordering
4
KOT generation, kitchen routing and order management
KOT functionality
5
Kitchen TV display and real-time status updates
Kitchen operations
6
Prepared dish stock, stock deduction and adjustments
Kitchen inventory
7
API development and existing billing software integration
External integration
8
Dashboard, reports, audit logs and final settings
Management features
9
Testing, UAT, deployment and production configuration
Production release

15. TESTING & ACCEPTANCE CRITERIA
The following scenarios must be tested before production release.
Create multiple companies and branches.
Create multiple kitchens under one branch.
Assign dishes to different kitchens.
Place one order containing dishes from three different kitchens.
Verify that each kitchen receives only its assigned dishes.
Verify that the same KOT is not duplicated.
Add additional dishes to an existing table order.
Verify that additional items generate new KOT tickets.
Update preparation status from the kitchen TV.
Verify that the waiter receives the updated status.
Add prepared-dish stock.
Verify automatic stock deduction after order submission.
Verify insufficient stock validation.
Verify cancellation and stock reversal rules.
Verify sold-out dishes are disabled in the mobile app.
Verify branch-wise user access restrictions.
Test kitchen display reconnection after network interruption.
Test API authentication and order synchronization with the billing software.
Verify that API retries do not create duplicate orders or stock deductions.
Verify reports against actual order and stock transactions.
16. OUT OF SCOPE – PHASE 1
The following features are not included in the initial development scope:
Customer billing and invoice generation.
Payment gateway integration.
Cash / UPI / Card payment collection.
Raw material inventory.
Recipe-based ingredient deduction.
Purchase Order and Vendor Management.
Supplier Management.
Accounting and GST filing.
Customer-facing online food ordering.
Delivery partner integrations.
Existing billing software integration through APIs is included, subject to API availability and confirmation from the existing software provider.
17. FINAL EXPECTED DELIVERABLES
Web-based Admin and Management Application.
Android Waiter Mobile Application.
Browser-based Kitchen Display System.
Multi-company and Multi-branch configuration.
Kitchen-wise automatic KOT routing.
Prepared-dish stock management.
Real-time order status synchronization.
REST API for existing billing software integration.
Reports and dashboards.
Database documentation.
API documentation.
User and technical documentation.
Testing and UAT support.
Production deployment and configuration.

FINAL PROJECT OBJECTIVE
The completed system should allow a waiter to take customer orders from any assigned restaurant table using a mobile application, automatically distribute the ordered dishes to their respective kitchens, allow kitchen staff to process and complete orders through dedicated TV displays, maintain accurate prepared-dish stock, and share order information with Nagarjuna's existing billing software through secure APIs.
The system should be designed so that additional branches, kitchens, users and menu items can be configured without requiring major application changes.
  
