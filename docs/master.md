```markdown
# Nagarjuna KOT – Context

Laravel 12 / PHP 8.2 / MySQL / Eloquent / Blade / Bootstrap 5.3.

## Rules
- Inspect and reuse existing code; don't duplicate or rewrite completed work.
- Preserve architecture, business logic and existing permissions.
- Use controller validation for simple CRUD; avoid unnecessary Services/Form Requests.
- Enforce permissions and company/branch scope server-side.
- Super Admin: all companies; Company Admin: own company; branch users: assigned branch.
- Validate related IDs to prevent cross-company/branch links.
- Use soft deletes and protect records with dependencies.
- Do not run destructive migrations or delete data without approval.
- Do not write or run automated tests. I will test manually.

## Masters
1. Company
2. Branch (company)
3. Kitchen (branch)
4. Floor (branch)
5. Table (branch + floor)
6. Category
7. Menu Type
8. Menu Item (category + menu type, price, image, availability)
9. Kitchen Mapping (menu item ↔ kitchen, same branch)

## Logic
- Dropdowns: Company → Branch; Branch → Floor/Kitchen; Floor → Table.
- Enforce same-company/branch relationships.
- Each master: CRUD, validation, search, pagination, status where applicable, permissions and safe deletion.
- Reuse existing permission conventions.

## Order
Company → Branch → Kitchen → Floor → Table → Category → Menu Type → Menu Item → Kitchen Mapping.

## Progress
- Completed web masters: Company, Branch, Kitchen, Floor, Table, Category, Menu Type, Menu Item, and Kitchen Mapping.
- Each master has scoped CRUD, search, pagination, status, permission checks, relationship validation, and dependency-protected soft deletes.
- Super Admin sees every company. Company Admin is limited to the assigned company. Other branch users are limited to the assigned branch.
- Pending: manual testing, then user management and the order/KOT modules when instructed.

```

