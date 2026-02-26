<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\UnauthorizedException;
use App\Models\{
    Employee,
    WorkingHours,
    WorkingDays
};
use App\ViewModels\EmployeeViewModel;

class EmployeeService
{
    /**
     * Aplica la regla especial de Dirección General (18 / 28)
     */
    private function applyGeneralDirectionRules($query, int $gd)
    {
        $specialEmployeeNumbers = [
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

        if ($gd == 18) {
            $query->where('general_direction_id', 18)
                ->whereNotIn('employee_number', $specialEmployeeNumbers);
        } elseif ($gd == 28) {
            $query->where(function ($q) use ($specialEmployeeNumbers) {
                $q->where('general_direction_id', 28)
                    ->orWhereIn('employee_number', $specialEmployeeNumbers);
            });
        } else {
            $query->where('general_direction_id', $gd);
        }

        return $query;
    }


    /**
     * Obtener empleados con filtros y paginación
     */
    public function getEmployees(int $take = 0, int $skip = 0, array $filters = [], &$total)
    {
        $employees = [];
        $query = Employee::query();

        $authUser = Auth::user();
        $currentLevel = $authUser->level_id;

        /*
    |--------------------------------------------------------------------------
    | FILTRO POR NIVEL
    |--------------------------------------------------------------------------
    */

        if ($currentLevel == 1) {
            // ADMIN → puede filtrar libremente

            if (!empty($filters['general_direction_id'])) {
                $query = $this->applyGeneralDirectionRules(
                    $query,
                    (int)$filters['general_direction_id']
                );
            }
        } else {

            // NO ADMIN → restricciones por jerarquía

            if ($currentLevel > 2) {
                $query = $this->applyGeneralDirectionRules(
                    $query,
                    (int)$authUser->general_direction_id
                );
            } elseif (!empty($filters['general_direction_id'])) {
                $query = $this->applyGeneralDirectionRules(
                    $query,
                    (int)$filters['general_direction_id']
                );
            }

            if ($currentLevel > 3) {
                $query->where('direction_id', $authUser->direction_id);
            } elseif (!empty($filters['direction_id'])) {
                $query->where('direction_id', $filters['direction_id']);
            }

            if ($currentLevel > 4) {
                $query->where('subdirectorate_id', $authUser->subdirectorates_id);
            } elseif (!empty($filters['subdirectorate_id'])) {
                $query->where('subdirectorate_id', $filters['subdirectorate_id']);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | FILTROS GENERALES
    |--------------------------------------------------------------------------
    */

        if (!empty($filters['search'])) {
            $query = $this->applyAdvancedSearch($query, trim($filters['search']));
        }

        if (isset($filters['active'])) {
            $query->where('active', $filters['active']);
        }

        /*
    |--------------------------------------------------------------------------
    | TOTAL
    |--------------------------------------------------------------------------
    */

        $total = $query->count();

        /*
    |--------------------------------------------------------------------------
    | PAGINACIÓN
    |--------------------------------------------------------------------------
    */

        if ($take > 0) {
            $query->orderBy('name', 'ASC')
                ->skip($skip)
                ->take($take);
        }

        $employeesRaw = $query->get();

        foreach ($employeesRaw as $employeeData) {
            $employees[] = EmployeeViewModel::fromEmployeeModel($employeeData);
        }

        return $employees;
    }


    /**
     * Obtener empleados asignados al usuario autenticado
     */
    public function getEmployeesOfUser()
    {
        $query = Employee::query();

        $authUser = Auth::user();
        $currentLevel = $authUser->level_id;

        if ($currentLevel > 1) {

            if ($currentLevel >= 2) {
                $query = $this->applyGeneralDirectionRules(
                    $query,
                    (int)$authUser->general_direction_id
                );
            }

            if ($currentLevel >= 3) {
                $query->where('direction_id', $authUser->direction_id);
            }

            if ($currentLevel >= 4) {
                $query->where('subdirectorate_id', $authUser->subdirectorates_id);
            }
        }

        return $query->orderBy('name', 'ASC')->get();
    }


    /**
     * get the employee by the employee number
     *
     * @param  string $employeeNumber
     * @return EmployeeViewModel
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Illuminate\Validation\UnauthorizedException
     */
    public function getEmployee(string $employeeNumber)
    {
        // * get the employee
        $employee = Employee::where('plantilla_id', '1' . $employeeNumber)->first();
        if ($employee == null) {
            throw new ModelNotFoundException("Employee not fount");
        }

        // * validate if the user has access the employee
        $__hasAccess = true;
        try {
            if (Auth::user()->level_id > 1) // * validate the access only if is not a Admin user (level id 1)
            {
                $__hasAccess = \App\Helpers\ValidateAccessEmployee::validateUser(Auth::user(), $employee);
            }
        } catch (\Throwable $th) {
            Log::error("Fail at validate if the user with id '{userId}' has access to the employee with employee number '{employee}: {message}'", [
                "userId" => Auth::user()->id,
                "employee" => $employeeNumber,
                "message" => $th->getMessage()
            ]);
            throw $th;
        }

        if (!$__hasAccess) {
            throw new UnauthorizedException("The user has nos access to this employee.");
        }

        // * return the employee
        return EmployeeViewModel::fromEmployeeModel($employee);
    }


    /**
     * update employee data
     *
     * @param  string $employeeNumber
     * @param  array $data
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Throwable
     */
    public function updateEmployee(string $employeeNumber, array $data)
    {
        // * get the employee
        $employee = Employee::where('plantilla_id', '1' . $employeeNumber)->first();
        if ($employee == null) {
            throw new ModelNotFoundException("Employee not fount");
        }

        // * attempt to update the employee
        try {
            $employee->general_direction_id = $data['general_direction_id'];

            $employee->direction_id = isset($data['direction_id']) ? $data['direction_id'] : 1;

            $employee->subdirectorate_id = isset($data['subdirectorate_id']) ? $data['subdirectorate_id'] : 1;

            $employee->department_id = isset($data['department_id']) ? $data['department_id'] : 1;

            if (isset($data['name'])) {
                $employee->name = $data['name'];
            }

            if (isset($data['canCheck'])) {
                $employee->status_id = $data['canCheck'];
            }

            if (isset($data['status_id'])) {
                $employee->active = $data['status_id'];
            }

            $employee->save();

            Log::notice("Employee '$employeeNumber:$employee->name' was updated.");
        } catch (\Throwable $th) {
            Log::error("Fail to update the employee '{employeeNumber}': {message}", [
                "employeeNumber" => $employeeNumber,
                "message" => $th->getMessage(),
                "request" => $data,
            ]);
            throw $th;
        }
    }

    /**
     * update the status of the employee
     *
     * @param  string $employeeNumber
     * @param  int $newStatusId
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Throwable
     */
    public function updateEmployeeStatus(string $employeeNumber, int $newStatusId)
    {
        // * get the employee
        $employee = Employee::where('plantilla_id', '1' . $employeeNumber)->first();
        if ($employee == null) {
            throw new ModelNotFoundException("Employee not fount");
        }

        // * attempt to update the employee
        try {
            $employee->active = $newStatusId;
            $employee->save();

            Log::notice("Updated Employee status of the employee'$employeeNumber:$employee->name'.");
        } catch (\Throwable $th) {
            Log::error("Fail to update the employee '{employeeNumber}': {message}", [
                "employeeNumber" => $employeeNumber,
                "message" => $th->getMessage()
            ]);
            throw $th;
        }
    }

    /**
     * return the employees that dont have assigned a general-direction, direction or subdirection
     *
     * @return array<EmployeeViewModel>
     */
    public function getNewEmployees()
    {
        $employeesRaw = Employee::where('general_direction_id', 1)
            ->orWhere('general_direction_id', null)
            ->orWhere('general_direction_id', '')
            ->get()
            ->all();

        $employees = array();

        foreach ($employeesRaw as $employee) {
            array_push($employees, EmployeeViewModel::fromEmployeeModel($employee));
        }

        return $employees;
    }

    #region schedule

    /**
     * update employee schedule
     *
     * @param  string $employeeNumber
     * @param  array $scheduleRequest {
     * An array of schedule data.
     *     @type int    $scheduleType Required. The type of schedule.
     *     @type string $checkin      Required. The check-in time (format: H:i).
     *     @type string $toeat        Required. The time allocated for eating (format: H:i).
     *     @type string $toarrive     Required. The arrival time (format: H:i).
     *     @type string $checkout     Required. The check-out time (format: H:i).
     *     @type bool   $midweek      Required. Indicates if the schedule applies to midweek.
     *     @type bool   $weekend      Required. Indicates if the schedule applies to the weekend.
     * }
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException employee not found
     * @throws \Exception fail to updat the schedule
     */
    public function updateEmployeeSchedule(string $employeeNumber, $scheduleRequest)
    {
        // * get the employee
        $employee = Employee::where('plantilla_id', '1' . $employeeNumber)->first();
        if ($employee == null) {
            throw new ModelNotFoundException("Employee not fount");
        }

        DB::beginTransaction();

        // * attempt to update the working hours
        try {
            // * remove the old working hours
            WorkingHours::where('employee_id', $employee->id)->delete();

            // * store new working hours
            if ($scheduleRequest['scheduleType'] == 1) /* Horario corrido */ {
                WorkingHours::create([
                    'employee_id' => $employee->id,
                    'checkin' => $scheduleRequest['checkin'],
                    'checkout' => $scheduleRequest['toeat'],
                ]);
            } else /* Horario quebrado */ {
                WorkingHours::create([
                    'employee_id' => $employee->id,
                    'checkin' => $scheduleRequest['checkin'],
                    'toeat' => $scheduleRequest['toeat'],
                    'toarrive' => $scheduleRequest['toarrive'],
                    'checkout' => $scheduleRequest['checkout']
                ]);
            }
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error("Fail to update the employee working hours: {message}", [
                "employee_id" => $employee->id,
                "message" => $th->getMessage(),
                "request" => $scheduleRequest
            ]);
            throw new \Exception($th->getMessage());
        }

        // * attempt tp update the working days
        try {
            $workingDays = WorkingDays::where('employee_id', $employee->id)->first();
            if ($workingDays == null) {
                WorkingDays::create([
                    'employee_id' => $employee->id,
                    'week' => $scheduleRequest['midweek'],
                    'weekend' => $scheduleRequest['weekend'],
                ]);
            } else {
                $workingDays->week = $scheduleRequest['midweek'];
                $workingDays->weekend = $scheduleRequest['weekend'];
                $workingDays->save();
            }
        } catch (\Throwable $th) {
            DB::rollback();
            Log::error("Fail to update the employee working days: {message}", [
                "employee_id" => $employee->id,
                "message" => $th->getMessage(),
                "request" => $scheduleRequest
            ]);
            throw new \Exception($th->getMessage());
        }

        DB::commit();
    }

    #endregion

    /**
     * Enhanced search for employees that handles multiple search scenarios
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  string $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyAdvancedSearch($query, string $searchTerm)
    {
        $searchTerm = trim($searchTerm);

        if (empty($searchTerm)) {
            return $query;
        }

        return $query->where(function ($q) use ($searchTerm) {
            // Search by employee number (plantilla_id)
            $q->where('plantilla_id', 'like', "%" . $searchTerm . "%");

            // Search by employee number without prefix (if numeric)
            if (is_numeric($searchTerm)) {
                $q->orWhere('plantilla_id', 'like', "%1" . $searchTerm . "%");
            }

            // Advanced name search - split search terms for better matching
            $searchWords = array_filter(explode(' ', $searchTerm), function ($word) {
                return !empty(trim($word)) && strlen(trim($word)) > 1; // Skip single characters
            });

            if (!empty($searchWords)) {
                $q->where(function ($nameQuery) use ($searchWords) {
                    // Search for each word in the name
                    foreach ($searchWords as $word) {
                        $trimmedWord = trim($word);
                        $nameQuery->where('name', 'like', "%" . $trimmedWord . "%");
                    }
                });

                // Also search for any word match (OR condition)
                $q->orWhere(function ($nameQuery) use ($searchWords) {
                    foreach ($searchWords as $word) {
                        $trimmedWord = trim($word);
                        $nameQuery->orWhere('name', 'like', "%" . $trimmedWord . "%");
                    }
                });
            }

            // Search for the complete term in name
            $q->orWhere('name', 'like', "%" . $searchTerm . "%");
        });
    }
}
