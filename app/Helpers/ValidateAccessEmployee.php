<?php

namespace App\Helpers;

use App\Models\Employee;
use App\Models\User;

class ValidateAccessEmployee
{
    /**
     * Números especiales que pertenecen lógicamente a GD 28
     */
    private static array $specialEmployeeNumbers = [
        20902,
        10829,
        48461,
        7057,
        20882,
        24493,
        28875,
        22515,
        30874,
        15492,
        26934,
        35561
    ];

    /**
     * Valida si el usuario tiene acceso al empleado
     */
    public static function validateUser(User $user, Employee $employee): bool
    {
        // Admin siempre tiene acceso
        if ($user->level_id == 1) {
            return true;
        }

        $currentLevel = $user->level_id;

        /*
        |--------------------------------------------------------------------------
        | NIVEL 2 → GENERAL DIRECTION (con regla 18 / 28)
        |--------------------------------------------------------------------------
        */
        if ($currentLevel >= 2) {

            if (!self::validateGeneralDirection($user, $employee)) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NIVEL 3 → DIRECCIÓN
        |--------------------------------------------------------------------------
        */
        if ($currentLevel >= 3) {
            if ($user->direction_id != $employee->direction_id) {
                return false;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | NIVEL 4 → SUBDIRECCIÓN
        |--------------------------------------------------------------------------
        */
        if ($currentLevel >= 4) {
            if ($user->subdirectorate_id != $employee->subdirectorate_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Regla especial para Dirección General 18 / 28
     */
    private static function validateGeneralDirection(User $user, Employee $employee): bool
    {
        $userGD = (int) $user->general_direction_id;
        $employeeGD = (int) $employee->general_direction_id;

        // Si es GD 18 → no puede ver los especiales
        if ($userGD == 18) {
            if (in_array($employee->employee_number, self::$specialEmployeeNumbers)) {
                return false;
            }

            return $employeeGD == 18;
        }

        // Si es GD 28 → puede ver:
        // - Los que son GD 28
        // - Los especiales aunque estén en GD 18
        if ($userGD == 28) {
            return $employeeGD == 28
                || in_array($employee->employee_number, self::$specialEmployeeNumbers);
        }

        // Caso normal
        return $userGD == $employeeGD;
    }
}
