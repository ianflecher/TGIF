# Department portals

Implemented 16 October 2026. Departments now have independent login pages and workspace addresses. Staff do not need an admin role or a visit to the global dashboard.

## Addresses

Start at `/portals` to choose a workspace. Signed-in staff see their assigned department links; guests see the public login directory. Login-page visibility does not grant access to records.

| Department | Login | Workspace |
|---|---|---|
| Projects | `/projects/login` | `/projects` |
| Finance | `/finance/login` | `/finance` |
| Inventory | `/inventory/login` | `/inventory` |
| Procurement | `/procurement/login` | `/procurement` |
| People & HR | `/hr/login` | `/hr` |
| Sales | `/sales/login` | `/sales` |
| Orders & products | `/ecommerce/login` | `/ecommerce` |
| Supply chain | `/supplychain/login` | `/supplychain` |
| Customer service | `/customerservice/login` | `/customerservice` |
| Reports | `/reports/login` | `/reports` |
| Store settings | `/commerce/login` | `/commerce` |

`/project` redirects to `/projects`. Existing `/admin/projects`, `/admin/finance` and other former department-home addresses remain permission-checked redirects to the new canonical workspaces. Existing detailed routes such as `/projects/budget` retain their names and authorization.

Personal portals remain `/applicant/login`, `/employee/login`, `/supplier/login` and `/login` (customers). `/admin/login` is the full super-admin login; `/admin` and `/admin/access` are reserved for full administrators.

## Assigning access

As a full administrator, open **Access control**, load an existing staff account, select the department and read/write/approve abilities, then save. Employees and managers must have an active HR employee record to use a department grant. No department permission is inferred from username, job title or department name. An unassigned employee retains only their personal employee portal. Multiple department grants are possible only when an administrator explicitly assigns them.

Read opens the department; write permits changes and interactive forms; approve permits the existing approval workflows. A preparer still cannot approve their own financial journal/payment/credit. Some cross-functional operations, such as stock-backed supplier returns, intentionally require both Finance and Inventory permissions. Store staff without Finance write access cannot view account mappings or replace them in a forged settings submission. Staff cannot grant permissions, promote identities, or enter the global dashboard even if a forged matrix includes dashboard/access grants.

Existing `admin` accounts with NULL module permissions remain full administrators for compatibility. **Review these accounts in Access control and restrict any that actually belong to department staff.** Restricted legacy admin-role accounts use department login, cannot enter the global admin dashboard and have the same explicit module/action boundaries. New demo accounts receive department-only grants; the demo seeder does not overwrite existing users, passwords or grants. No production account permissions were automatically changed during development.

The full-administrator status is the existing `role=admin` plus NULL permissions convention; this change does not add a new database role enum. Ordinary role changes continue to clear grants rather than silently creating full administrators. No new schema migration is required for this feature when the previous audit migrations are installed.

## Boundaries and sessions

Department login validates credentials, active employment where applicable, and current module permission before signing in. Existing two-factor challenges and shared login throttling remain in force. Route checks, persistent Livewire middleware, component hydration/action checks and critical service checks use current database grants. Revoking a grant or suspending employment blocks subsequent requests/actions, including mounted components and sessions that just completed two-factor verification.

Department workspaces show their own department navigation and authorized sections, plus personal/portal links. A forged URL into another ungranted department returns 403; public login pages and the storefront remain public. Signing out returns to the relevant department login. Supplier login clears the web staff/customer identity, and web logins clear the supplier identity to prevent simultaneously retaining unrelated portal sessions.

Applicants are still represented by the existing employee-role identity before HR activation. Active employees, terminated/on-leave employee records and identities already marked hired cannot enter the applicant portal. Eligible inactive applicants retain their own application/documents. Customer/supplier ownership boundaries and private-file download checks remain in place.

This is server-enforced logical separation inside one application/database. A department grant permits its existing module-wide records and necessary supporting data. It is not separate database tenancy, branch/warehouse-level access, project-by-project row grants or configurable field-level payroll visibility. Existing workflow permissions continue to govern actions within a module.

A browser check used a synthetic **employee-role Projects-only** identity in the isolated SQLite preview. It signed in at `/projects/login`, landed directly at `/projects`, saw only Projects/personal navigation and received 403 at `/finance`. Real staff grants and business databases were not modified. An observed project dashboard defect was also corrected: tasks/progress now join through the actual project-phase relationship rather than matching obsolete phase IDs.

Validation: **131 PHPUnit tests passed, 687 assertions** across the full suite. New tests cover every department home/login, staff scope, cross-module denial, direct Livewire calls, revoked permissions/employment, applicant/employee separation, super-admin-only access configuration, two-factor revocation, Finance mapping isolation, supplier session switching and correct project-phase linking. The Vite build, sequential Blade compilation, PHP syntax checks and whitespace checks passed. No new database migration is required. The synthetic preview browser session is currently signed in as a Projects-only employee.

## Local demo accounts and department interface

Department identities now use a separate horizontal portal layout with their department's sections, account links and no administrator sidebar. The full administrator retains the central workspace.

Run `php artisan portals:provision-demo` only in local/testing to create missing demo managers and restrict the known demo department accounts. It preserves passwords and existing employee details, rejects conflicting emails/deleted or inactive identities, and leaves the main administrator unchanged. Each account gets exactly its own module. This command was applied to the isolated preview SQLite database.

Usernames: `project`, `finance`, `inventory`, `procurement`, `hr`, `sales`, `ecommerce`, `supplychain`, `customerservice`, `businessintelligence` (Reports), `commerce` (Store settings). New demo accounts use `password`; existing passwords remain unchanged. These are local demonstration credentials only.
