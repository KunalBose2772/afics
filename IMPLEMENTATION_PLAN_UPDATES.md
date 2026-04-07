# Documantraa Update Implementation Plan (Cost-Optmized)

This plan outlines the implementation of advanced features for Documantraa.in. All solutions are designed to use **zero recurring cost** technologies (Free WhatsApp Links, Standard Hosting Storage, Native Browser GPS).

## Phase 1: Database & Core Architecture Updates

### 1.1 Schema Modifications
We need to update the database to support dual allocation, detailed document categorization, and financial tracking.

*   **Projects Table (`projects`)**:
    *   Add `assigned_doctor_id` (INT) - To support dual allocation (FO + Doctor).
    *   Add `tat_status` (ENUM/VARCHAR) - Calculated column or logic for Green/Yellow/Orange/Red.
    *   Add `mrd_payment_status` (ENUM: 'Pending', 'Paid', 'Verified').
    *   Add `mrd_amount` (DECIMAL).
*   **Users Table (`users`)**:
    *   Add `salary_base` (DECIMAL) - e.g., 15000.
    *   Add `target_points` (INT) - e.g., 130.
    *   Add `current_points` (INT) - Month-to-date points.
*   **Documents Table (`project_documents`)** (New or Update):
    *   Add `category_part` (ENUM: 'Hospital', 'Patient', 'Investigation', 'Other').
    *   Add `document_type` (e.g., 'Hospital Visit Photo', 'ICP', 'Tariff', 'Lab Results').
    *   Add `latitude` & `longitude` (DECIMAL) - For GPS tagging.
    *   Add `visibility_level` (ENUM: 'All', 'Admin_Only', 'Doctor_Only') - To hide investigation reports from FO.
*   **Salary/Points Log (`salary_points_log`)**:
    *   Track additions/deductions (Type: 'Incentive', 'Food Allowance', 'TA', 'Penalty').

## Phase 2: Advanced Allocation & Workflow

### 2.1 Dual Allocation Interface
*   **Update**: `crm/projects.php` & Modal.
*   **Feature**: Allow selecting *both* a Field Officer (Investigator) and an Incharge/Doctor during case creation or edit.
*   **Logic**:
    *   Case appears in both Dashboards.
    *   FO sees "Field Instructions".
    *   Doctor sees "Medical Opinion Required".

### 2.2 TAT (Turnaround Time) Visuals
*   **Update**: `crm/projects.php` (Card View).
*   **Feature**: Dynamic colored flags/borders based on `created_at` date.
    *   **1-3 Days**: <span style="color:green">● Green Flag</span>
    *   **3-5 Days**: <span style="color:yellow">● Yellow Flag</span>
    *   **6-7 Days**: <span style="color:orange">● Orange Flag</span>
    *   **>7 Days**: <span style="color:red">● Red Flag</span>

### 2.3 Notifications (Zero Cost)
*   **Feature**: "Click-to-Notify" Buttons.
*   **Implementation**:
    *   Use `wa.me` links: `https://wa.me/{phone}?text={Encoded Message}`.
    *   **Scenario**: When Admin allocates a case, a "Send WhatsApp" button appears. Clicking it opens the user's WhatsApp Web/App with pre-filled details (Case #, Name, Location) ready to send.
    *   **SOS Button**: A bright red floating button on the mobile dashboard that instantly opens a WhatsApp chat to the Admin with a "HELP NEEDED" message and current location map link.

## Phase 3: Smart Document System & GPS

### 3.1 Upload Interface (Hospital vs Patient Part)
*   **Update**: `crm/project_documents.php` or `crm/view_project.php`.
*   **Feature**: Tabbed upload section.
    *   **Tab 1: Hospital Part**: Visit Photos, ICP, Reg Cert, Tariff, Lab Reports, Discharge Summary.
    *   **Tab 2: Patient Part**: Home Visit Photos, ID, Past Docs, Timeline, Bills.
*   **Constraint**: Max 25MB check in PHP `$_FILES` logic.

### 3.2 GPS Camera Integration
*   **Feature**: "Live Camera" Upload.
*   **Implementation**:
    *   Use HTML5 Input: `<input type="file" capture="environment" accept="image/*">`.
    *   Use JS Navigator API: Capture `navigator.geolocation.getCurrentPosition` when the upload button is clicked.
    *   **Rename Logic**: Auto-rename file on server: `"{Type} : {Claim_Number}.jpg"` (e.g., "Hospital Visit Photo : HI-NIC-00673").

### 3.3 Visibility Rules (Privacy)
*   **Constraint**: "Doctors upload investigation report not visible to Field Officer".
*   **Implementation**:
    *   In the file listing loop (`foreach`), check `$_SESSION['role']`.
    *   If Role == 'Field Officer' AND Doc_Type == 'Investigation Report', **SKIP** display.

## Phase 4: Financial & Incharge Modules

### 4.1 MRD Bill Payment (Client Provided Gateway)
*   **Workflow**:
    1.  Staff fills "MRD Bill Request" (Amount + Hospital Details).
    2.  Staff uploads "QR Code" image from Hospital.
    3.  Admin pays (using their own app/gateway outside system).
    4.  Admin uploads "Payment Slip".
    5.  Status updates to "Paid".
    6.  Staff uploads "Final Receipt".

### 4.2 Salary Points System
*   **Feature**: "My Earnings" Dashboard.
*   **Logic**:
    *   Base Salary: ₹15,000 for 130 Points.
    *   Admin page to manually "Add/Deduct" points or money (Incentive, TA, Food).
    *   Display: Progress bar showing Points vs Target.

### 4.3 Incharge/Doctor Tools
*   **Form Downloads**: A simple section listing all static PDF/Word templates (Cashless Form, Reimbursement Form, etc.) for easy download.
*   **Approval Action**:
    *   When viewing a report, Incharge has buttons: [Approve], [Reject], [Query].
    *   Input field for "Remarks".

## Phase 5: Google Maps Integration
*   **Feature**: Link Hospital Location.
*   **Implementation**:
    *   In `project_details`, the Hospital Address becomes a clickable link:
    *   `https://www.google.com/maps/search/?api=1&query={Encoded Address}`.
    *   This opens the native Google Maps app on mobile.

## Execution Order
1.  **Migrations**: Run SQL for new columns.
2.  **Backend Logic**: Update `projects.php` to handle dual save.
3.  **Frontend DMS**: Build the grouped upload tabs with Camera & GPS JS.
4.  **Frontend Dashboards**: Update FO and Doctor views with specific permissions.
5.  **Financials**: Build the MRD and Points pages.
