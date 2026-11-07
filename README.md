# 📂 Staff Project Management Portal (ICT600)

A full-stack web application designed for staff to manage projects, track actionable sub-tasks, and maintain an automated audit trail of all system activities.

**Built with:** PHP (PDO), MySQL, Bootstrap 5, and Vanilla JavaScript

---

## 👨‍💻 Author Information
* **Student Name:** Irfan Daniel
* **GitHub:** [@CodeNewbDan](https://github.com/CodeNewbDan)
* **Course Code:** ICT600 - Web Development
* **Institution:** Universiti Teknologi MARA (UiTM)

---

## 🌟 Key Features

* **Staff Authentication & Profile CRUD**
  * Secure authentication with dual-layer validation and `PASSWORD_DEFAULT`
  * Session fixation protection with `session_regenerate_id()`
  * **Staff Profile Hub:** View account details and activity metrics, update profile name/email, change password, or permanently delete account with safe cascading data cleanup

* **Interactive Project CRUD**
  * **CREATE:** Dynamic category combobox allows staff to pick existing categories or create new ones on the fly
  * **READ:** Responsive dashboard featuring project metrics, status badges, and subtask counters
  * **UPDATE:** Secure editing strictly scoped to owner's `staff_id`
  * **DELETE:** Permanent deletion with JavaScript confirmation prompts

* **Sub-Task Management**
  * Interactive modal on dashboard and project edit page for instant task checking
  * Full subtask checklist embedded inside project edit view
  * Dynamic completion progress bar

* **Automated Audit Trail**
  * Records user actions behind the scenes automatically
  * Real-time recent activity feed displayed on the dashboard

* **Live Instant Search**
  * Client-side real-time table filtering by title, category, or status with zero page reloads

---

## 🗄️ Database Schema (`my_db`)

The relational database consists of 5 tables:

* `STAFF(staff_id(PK), user_name, full_name, email, password, created_at)`
* `CATEGORY(category_id(PK), category_name)`
* `PROJECT(project_id(PK), staff_id(FK), category_id(FK), title, project_description, status, created_at)`
* `TASK(task_id(PK), project_id(FK), task_name, is_completed)`
* `ACTIVITY_LOG(activity_id(PK), staff_id(FK), activity_notes, logged_at)`

---

## 🚀 Installation & Local Setup

### Requirements
* [XAMPP](https://www.apachefriends.org/) (Apache & MySQL)
* Web Browser (JavaScript enabled)

### Step 1: Clone the Project
Clone the repository into your XAMPP `htdocs` directory:
```bash
cd c:\xampp\htdocs\
git clone https://github.com/CodeNewbDan/Individual-ICT600.git Individual
```

### Step 2: Start Apache & MySQL
Open the **XAMPP Control Panel** and click **Start** for both Apache and MySQL.

### Step 3: Create the Database
1. Open phpMyAdmin: [http://localhost/phpmyadmin/](http://localhost/phpmyadmin/)
2. Create a database named `my_db`.
3. Import or execute your SQL schema table definitions for `staff`, `category`, `project`, `task`, and `activity_log`.

### Step 4: Run the Application
Open your browser and navigate to:
```
http://localhost/Individual/register.php
```

```