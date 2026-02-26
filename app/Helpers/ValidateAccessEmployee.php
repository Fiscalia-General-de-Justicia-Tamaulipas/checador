<?php

namespace App\Helpers;

use App\Models\Employee;
use App\Models\User;

class ValidateAccessEmployee
{
    // Empleados especiales para reglas de negocio
    private const EMPLOYEES_VLCPC = [
        20902, // BRENDA LIZZETH SANCHEZ PICASSO
        10829, // HOMERO GONZALEZ SANCHEZ
        48461, // YARAHI JOSELIN SILVERIO DUQUE
        7057,  // MA. IGNACIA RUIZ RETA
        20882, // YESENIA COLUNGA BRISEÑO
    ];

    private const EMPLOYEES_PROCESOS = [
        15492, // ROSAURA OTERO ZARATE
        35561, // VALERIA MONSERRAT GALLEGOS MALDONADO
        30874, // JUAN CARLOS GUTIERREZ REYNA
        24493, // ROSA IRMA REYNA FLORES
        26934, // IRASEMA SANCHEZ GANDARA
        22515, // SANTANA MARQUEZ LOPEZ
        28875, // MARIA DE LOURDES ARRATIA MALDONADO
    ];

    /**
     * validate if the user has access to the employee
     *
     * @return bool
     */
    static function validateUser(User $user, Employee $employee)
    {
        // Los administradores (level_id = 1) tienen acceso a todo
        if ($user->level_id == 1) {
            return true;
        }

        $__hasAccess = true;

        if ($user->level_id > 1) {
            $__currentLevel = $user->level_id;

            // Verificar reglas especiales primero
            if ($__hasAccess && self::checkSpecialRules($user, $employee)) {
                return true;
            }

            // Reglas normales de jerarquía
            if ($__currentLevel >= 2) {
                if ($user->general_direction_id != $employee->general_direction_id) {
                    $__hasAccess = false;
                }
            }

            if ($__currentLevel >= 3 && $__hasAccess) {
                if ($user->direction_id != $employee->direction_id) {
                    $__hasAccess = false;
                }
            }

            if ($__currentLevel >= 4 && $__hasAccess) {
                if ($user->subdirectorate_id != $employee->subdirectorate_id) {
                    $__hasAccess = false;
                }
            }

            return $__hasAccess;
        }

        return false;
    }

    /**
     * Verificar reglas especiales de negocio
     *
     * @param User $user
     * @param Employee $employee
     * @return bool
     */
    private static function checkSpecialRules(User $user, Employee $employee)
    {
        $userGeneralDirectionId = $user->general_direction_id;
        $employeeNumber = $employee->employee_number;

        // Caso 1: Usuario de GD 16 (VLCPC) puede ver empleados específicos de GD 18
        if ($userGeneralDirectionId == 16) {
            if (in_array($employeeNumber, self::EMPLOYEES_VLCPC)) {
                return true;
            }
        }

        // Caso 2: Usuario de GD 17 (Procesos) puede ver empleados específicos de GD 18
        if ($userGeneralDirectionId == 17) {
            if (in_array($employeeNumber, self::EMPLOYEES_PROCESOS)) {
                return true;
            }
        }

        // Caso 3: Usuario de GD 18 NO puede ver empleados específicos que fueron transferidos
        if ($userGeneralDirectionId == 18) {
            if (
                in_array($employeeNumber, self::EMPLOYEES_VLCPC) ||
                in_array($employeeNumber, self::EMPLOYEES_PROCESOS)
            ) {
                return false;
            }
        }

        return false;
    }

    /**
     * Obtener lista de empleados excluidos para una GD específica
     *
     * @param int $generalDirectionId
     * @return array
     */
    static function getExcludedEmployees(int $generalDirectionId)
    {
        if ($generalDirectionId == 18) {
            return array_merge(self::EMPLOYEES_VLCPC, self::EMPLOYEES_PROCESOS);
        }

        return [];
    }

    /**
     * Obtener lista de empleados incluidos para una GD específica
     *
     * @param int $generalDirectionId
     * @return array
     */
    static function getIncludedEmployees(int $generalDirectionId)
    {
        if ($generalDirectionId == 16) {
            return self::EMPLOYEES_VLCPC;
        }

        if ($generalDirectionId == 17) {
            return self::EMPLOYEES_PROCESOS;
        }

        return [];
    }
}
