<?php

namespace App\Services;

use App\Services\Authorization\PermissionResolver as AuthorizationPermissionResolver;

/**
 * Compatibility wrapper for legacy consumers.
 * Canonical implementation resides at \App\Services\Authorization\PermissionResolver.
 *
 * Dipertahankan secara eksklusif untuk backward compatibility modul eksternal
 * pada branch development (seperti Renstra, Regulasi, Master Unit) sebelum modul-modul
 * tersebut dimigrasikan ke canonical namespace pada PR masing-masing. Seluruh use-case
 * Perjanjian Kinerja (ISS-02.08) wajib menggunakan canonical resolver.
 *
 * @deprecated Gunakan \App\Services\Authorization\PermissionResolver secara langsung.
 */
class PermissionResolver extends AuthorizationPermissionResolver {}
