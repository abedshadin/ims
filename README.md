# IMS Vendor Management

This project provides the foundation for a PHP/MySQL web application using Bootstrap for styling and vanilla JavaScript with Fetch/AJAX for dynamic behaviour. The initial module implements vendor data capture with a responsive form and server-side persistence logic.

## Project Structure

```
app/
  Database.php          # PDO wrapper for database connectivity
public/
  index.php             # Redirects to the vendor form
  assets/
    css/styles.css      # Custom styles
    js/vendor_form.js   # Form submission logic via Fetch API
  vendor/
    create.php          # Vendor creation form
    store.php           # Handles vendor persistence

database/
  schema.sql            # MySQL schema for the `vendors` table
```

## Getting Started

1. **Install dependencies**

   Ensure PHP 8.1+ and MySQL are available on your system.

2. **Configure database access**

   Update the credentials in `app/Database.php` to match your MySQL setup.

3. **Create the database table**

   ```sql
   SOURCE database/schema.sql;
   ```

4. **Run the application**

   Serve the `public/` directory via your preferred web server or PHP's built-in server:

   ```bash
   php -S localhost:8000 -t public
   ```

5. **Access the vendor form**

   Navigate to `http://localhost:8000/vendor/create.php` to submit vendor information. Successful submissions return confirmation messages without reloading the page.

## Next Steps

Future tasks include implementing authentication (registration, login), ID encryption, and additional modules as required.
