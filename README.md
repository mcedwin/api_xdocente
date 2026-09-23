<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel1

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

---

## Esquema unificado (actividades)

Desde sep 2026 la API usa un **contrato único de actividad** en `app_activities` (+ `app_activity_criteria`, `app_activity_groups`, `app_activity_group_members`, `app_activity_scores`, `app_activity_group_overrides`) que cubre tareas, prácticas, participación, trabajos grupales y proyectos a través del campo `type` (`task | practice | participation | group_work | project`).

El catálogo de migraciones (ejecutado en la BD MySQL `xdocente` y registrado en `sys_migrations`) incluye:

- **Tablas base:** `app_users`, `app_courses`, `app_units`, `app_sessions`, `app_students`, `app_attendance`, `app_alerts` (`2026_09_21_000000` → `000006`).
- **Tablas de infraestructura:** `sys_cache`, `sys_jobs`, `sys_failed_jobs`, `sys_job_batches`, `sys_password_reset_tokens`, `sys_personal_access_tokens`, `sys_sessions`, `sys_cache_locks` (`000007`).
- **Tablas unificadas de actividad** (`2026_09_21_100000` → `100005`).

### Tablas legacy archivadas

Las tablas duplicadas del esquema anterior se renombraron a `legacy_*` (conservan los datos históricos):

- `legacy_app_tasks`, `legacy_app_task_grades`, `legacy_app_practices`, `legacy_app_practice_grades`
- `legacy_app_participation_items`, `legacy_app_participation_grades`
- `legacy_app_group_works`, `legacy_app_group_work_criteria`, `legacy_app_groups`, `legacy_app_group_members`, `legacy_app_group_criterion_scores`, `legacy_app_individual_adjustments`
- `legacy_app_projects`, `legacy_app_project_criteria`, `legacy_app_project_grades`

Los datos de `app_tasks`/`app_task_grades` y `app_practices` se migraron a `app_activities`/`app_activity_scores` (tipo `task` y `practice`, sin rúbrica). Las tablas `legacy_*` pueden eliminarse cuando ya no se necesiten.

> Nota: el entorno de desarrollo actual no dispone de binario PHP; la aplicación del esquema y la migración de datos se realizó directamente sobre MySQL. En un entorno con PHP/Composer basta con `php artisan migrate` (las migraciones ya figuran como ejecutadas en `sys_migrations`).
