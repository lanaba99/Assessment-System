# Project Architecture Documentation

## 1. Overview
This is a Multi-tenant SaaS project built with Laravel.
- **Goal:** Assessment and Exam management system.
- **Architecture Pattern:** Domain-Driven Design (DDD).

## 2. Multi-Tenancy Strategy
- **Library:** `stancl/tenancy`
- **Isolation:** Each tenant has its own database/schema.
- **Resolution:** Tenancy is resolved via subdomains.
- **Middleware:** Any request hitting a tenant route must go through `InitializeTenancyBySubdomain`.
- **Global Rule:** Never perform queries that cross-tenant boundaries without explicit global scope bypass (which should be rare). Always respect the `tenant()` context.

## 3. Directory Structure (DDD)
We move business logic out of the `app/Http/Controllers` directory and into `app/Domains`.
- `app/Domains/{DomainName}/Services/`: Contains the business logic.
- `app/Domains/{DomainName}/Models/`: Domain-specific Eloquent models.
- `app/Domains/{DomainName}/DTOs/`: Data Transfer Objects for clean communication.
- `app/Http/Controllers/`: Thin controllers. They only validate requests and call Service methods.
- `app/Http/Resources/`: API response transformations.

## 4. Key Domains (Modules)
1. **Identity:** Auth, Roles, Permissions, User Management.
2. **ExamEngine:** Exam creation, publishing, and lifecycle.
3. **ExamSession:** Candidate sessions, responses, and termination.
4. **Grading:** Assessment results and scoring logic.

## 5. Coding Standards
- **Strict Typing:** All files must start with `declare(strict_types=1);`.
- **Dependency Injection:** Always use constructor injection for services.
- **Request Handling:** Use FormRequests for all validations.
- **API Response:** Standardize JSON responses using `API Resources`.

## 6. How to add new features
1. Define the Domain.
2. Create necessary DTOs and Services.
3. Register routes in `routes/tenant.php` with the `api/v1` prefix.
4. Ensure tenancy middleware is applied.