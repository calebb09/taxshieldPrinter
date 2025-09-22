# Tax Shield

**Tax Shield** is a PHP-based application that provides client and check management features with a structured, object-oriented design. It uses **PDO** for secure database interactions and follows a simple MVC-like architecture.

---

## 🚀 Features

* **Database Connection Management** using PDO
* **Client Management** with dedicated logic in `ClientManager.php`
* **Check Management** with centralized handling in `CheckManager.php`
* **Centralized Configuration** through `config.php`
* **Single Entry Point** via `index.php`

---

## 📂 Project Structure

```
tax_shield/
├── index.php          # Entry point for all requests
├── config.php         # Database and configuration settings
├── classes/
│   ├── Database.php       # Database connection class (PDO)
│   ├── ClientManager.php  # Handles client-related logic
│   ├── CheckManager.php   # Handles check-related logic
```

---

## ⚙️ Requirements

* **PHP** >= 7.4
* **MySQL** >= 5.7 (or MariaDB equivalent)
* **XAMPP/LAMP/MAMP** for local development

---

## 🛠 Installation & Setup

1. **Clone the repository**

   ```bash
   git clone https://github.com/yourusername/tax_shield.git
   cd tax_shield
   ```

2. **Set up the database**

   * Create a new MySQL database.
   * Import your schema (if provided).
   * Update the database credentials in `config.php`.

   ```php
   $config = (object)[
       "db" => (object)[
           "host" => "localhost",
           "dbname" => "tax_shield_db",
           "charset" => "utf8mb4",
           "user" => "root",
           "pass" => ""
       ]
   ];
   ```

3. **Start the development server**
   If you’re using XAMPP or MAMP, place the project folder in `htdocs` and visit:

   ```
   http://localhost/tax_shield
   ```

   Or run PHP’s built-in server:

   ```bash
   php -S localhost:8000
   ```

---

## 🧪 Usage

* Navigate to `index.php` in your browser.
* The application will automatically establish a database connection using the `Database` class.
* Extend functionality using `ClientManager` and `CheckManager`.

---

## 📌 Example Query (Check if Users Exist)

```php
$db = Database::getInstance($config)->pdo();
$stmt = $db->query("SELECT COUNT(*) as count FROM users");
$result = $stmt->fetch();
if ($result['count'] > 0) {
    echo "Users exist in the database.";
} else {
    echo "No users found.";
}
```

---

## 🐛 Troubleshooting

* **Database connection failed** → Check your `config.php` credentials.
* Ensure MySQL is running on the configured host/port.
* Enable PDO extension in your `php.ini`.

---

## 📜 License

This project is licensed under the MIT License.
You are free to use, modify, and distribute it for personal or commercial use.

---
