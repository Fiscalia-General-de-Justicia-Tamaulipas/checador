<?php

namespace App\Helpers;

use App\Models\Employee;
use App\Models\User;

class ValidateAccessEmployee
{
    // Empleados especiales para reglas de negocio
    private const EMPLOYEES_VLCPC = [
        //20902, // BRENDA LIZZETH SANCHEZ PICASSO
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
     * Obtener las direcciones generales que un usuario puede consultar.
     */
    public static function getAllowedGeneralDirectionIds(User $user): array
    {
        if ($user->level_id == 1) {
            return [];
        }

        $allowedGdIds = [$user->general_direction_id];

        if ($user->general_direction_id == 12) {
            return [11, 12, 13, 14];
        }

        if ($user->general_direction_id == 16) {
            return [16, 17, 18];
        }

        if ($user->general_direction_id == 17) {
            return [17, 18];
        }

        return $allowedGdIds;
    }

    private static function hasGeneralDirectionScopeAccess(User $user, Employee $employee): bool
    {
        $allowedGeneralDirections = self::getAllowedGeneralDirectionIds($user);

        return !empty($allowedGeneralDirections)
            && in_array($employee->general_direction_id, $allowedGeneralDirections, true);
    }

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
                if (!self::hasGeneralDirectionScopeAccess($user, $employee)) {
                    $__hasAccess = false;
                }
            }

            if ($__currentLevel >= 3 && $__hasAccess) {
                if (!self::hasGeneralDirectionScopeAccess($user, $employee)) {
                    $__hasAccess = false;
                } elseif ($user->direction_id != $employee->direction_id && !self::hasGeneralDirectionScopeAccess($user, $employee)) {
                    $__hasAccess = false;
                }
            }

            if ($__currentLevel >= 4 && $__hasAccess) {
                if (!self::hasGeneralDirectionScopeAccess($user, $employee)) {
                    $__hasAccess = false;
                } elseif ($user->subdirectorate_id != $employee->subdirectorate_id && !self::hasGeneralDirectionScopeAccess($user, $employee)) {
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
        $employeeGeneralDirectionId = $employee->general_direction_id;

        // Caso 1: Usuario de GD 16 (VLCPC) puede ver empleados de GD 17 y 18
        if ($userGeneralDirectionId == 16) {
            if ($employeeGeneralDirectionId == 17 || $employeeGeneralDirectionId == 18) {
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
