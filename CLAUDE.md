# Project: Laravel Backend API Service

This is a production-ready Laravel API-only backend application. Follow strict type safety, domain-driven organization, and standard Laravel ecosystem patterns.

## 🛠 Build & Development Commands
* Run local server: `php artisan serve`
* Run test suite: `php artisan test` or `./vendor/bin/pest`
* Run single test: `php artisan test --filter=NameOfTest`
* Run code formatter: `./vendor/bin/pint`
* Run static analysis: `./vendor/bin/phpstan analyse`
* Clear application cache: `php artisan optimize:clear`
* Run migrations: `php artisan migrate`
* Run seeders: `php artisan db:seed`
* Run queue worker: `php artisan queue:work --queue=default`

## 📐 Architecture & Structural Rules
* **Controllers**: Use single-action invokable controllers (`__invoke`) or strict resource controllers for API endpoints.
* **Business Logic**: Keep controllers thin. Move heavy logic, third-party integrations, and complex transactions into dedicated Service classes (`app/Services/`).
* **Validation**: Never validate inside controllers. Always create and inject dedicated Form Request classes (`app/Http/Requests/`).
* **Data Transformation**: Always return API responses wrapped inside Laravel API Resources (`app/Http/Resources/`). Never return raw arrays or models directly.
* **Database Access**: Rely entirely on Eloquent ORM. Never perform raw SQL queries or use the `DB` facade unless writing highly optimized, macro-level operations.

## ✒️ Code Style & Conventions
* **PHP Target**: Use modern PHP features (Enums, readonly properties, constructor property promotion, match expressions).
* **Type Safety**: Enforce `declare(strict_types=1);` on all newly created files. Declare strict type-hints for all parameters and function return values.
* **Code Formatting**: Do not manually rearrange code spaces or alignment. Laravel Pint enforces styling rules automatically.
* **Naming**:
  * Models: Singular PascalCase (`UserOrder`).
  * Controllers: Plural/Singular PascalCase postfixed with Controller (`OrderController` or `ProcessPaymentController`).
  * Database Tables: Plural snake_case (`user_orders`).
  * Methods/Variables: camelCase (`calculateTotalAmount`).

## 💾 Database & Eloquent Operations
* **Relationships**: Define strict return type-hints on all model relationships (`hasMany`, `belongsTo`).
* **Performance**: Avoid N+1 query problems. Always use eager loading (`with()`) for related models when pulling lists.
* **Query Scopes**: Move reusable query logic and filters into local Eloquent Scopes within the model.

## 🧪 Testing Guidelines
* **Framework**: Use Pest PHP for writing clean, readable test suites.
* **Database Isolation**: Use the `RefreshDatabase` trait for features interacting with the database.
* **Coverage**: Every new feature request or bug fix requires an accompanying feature test. Mock external APIs completely.
