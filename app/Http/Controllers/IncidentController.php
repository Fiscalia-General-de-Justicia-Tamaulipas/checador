<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Interfaces\EmployeeIncidentInterface;
use App\ViewModels\EmployeeViewModel;
use App\Helpers\IncidentsReport;
use App\Services\{
    EmployeeService,
    IncidentService
};
use App\Models\{
    Employee,
    GeneralDirection,
    Incident,
    IncidentState,
    WorkingDays,
    WorkingHours
};
use Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\RedirectResponse;

class IncidentController extends Controller
{
    protected EmployeeService $employeeService;
    protected EmployeeIncidentInterface $employeeIncidentService;

    static $INCIDENT_STATE_PENDING = 1;

    function __construct(EmployeeService $employeeService, EmployeeIncidentInterface $employeeIncidentService)
    {
        $this->employeeService = $employeeService;
        $this->employeeIncidentService = $employeeIncidentService;
    }

    function index(Request $request)
    {
        $generalDirecctionId = $request->filled('gdi') ? $request->input("gdi") : intval(Auth::user()->general_direction_id);
        $repType = $request->filled('t') ? $request->input("t") : 'monthly';
        $year = $request->filled('y') ? $request->input("y") : Carbon::now()->year;
        if ($request->filled('p')) {
            $period = $request->query('p');
        } else {
            // if the period is no passed by parameter make it based on the report type, for type `fortnight` the format of the period is '{month}-{NumQuincena}'
            $period = ($repType == 'monthly') ? Carbon::now()->month : Carbon::now()->month . "-1";
        }

        // * get the incidents
        $employees = array();
        if ($period != null && $generalDirecctionId != null) {
            // * prepare options
            if ($repType == 'monthly') {
                $startOfMonth = Carbon::parse("$year-$period-01")->startOfDay();
                $endOfMonth = Carbon::parse("$year-$period-01")->endOfMonth()->endOfDay();
            } else {
                $month = explode("-", $period)[0];
                $quin = explode("-", $period)[1];
                if ($quin == 1) {
                    $startOfMonth = Carbon::parse("$year-$month-01");
                    $endOfMonth = Carbon::parse("$year-$month-15");
                } else {
                    $startOfMonth = Carbon::parse("$year-$month-16");
                    $endOfMonth = Carbon::parse("$year-$month-01")->endOfMonth()->endOfDay();
                }
            }

            $employees = $this->getEmployeesWithIncidentsByDirection($generalDirecctionId, $startOfMonth, $endOfMonth);
        }


        // * catalog incident status
        $incidentStatuses = IncidentState::where("id", ">", 1)->select('id', 'name')->get()->toArray();
        $generalDirections = GeneralDirection::select(['id', 'name'])->get()->sortBy('name')->all();
        $reportTypes = [
            "monthly" => "Mensual",
            "fortnight" => "Quincenal"
        ];

        if ($repType == 'monthly') {
            $periods = [
                "1"  => "Enero",
                "2"  => "Febrero",
                "3"  => "Marzo",
                "4"  => "Abril",
                "5"  => "Mayo",
                "6"  => "Junio",
                "7"  => "Julio",
                "8"  => "Agosto",
                "9"  => "Septiembre",
                "10" => "Octubre",
                "11" => "Noviembre",
                "12" => "Diciembre"
            ];

            // If current year, only show months up to current month
            if ($year == Carbon::now()->year) {
                $currentMonth = Carbon::now()->month;
                $periods = array_slice($periods, 0, $currentMonth, true);
            }
        } else {
            $periods = [
                "1-1"  => "1ra Quincena de Enero",
                "1-2"  => "2da Quincena de Enero",
                "2-1"  => "1ra Quincena de Febrero",
                "2-2"  => "2da Quincena de Febrero",
                "3-1"  => "1ra Quincena de Marzo",
                "3-2"  => "2da Quincena de Marzo",
                "4-1"  => "1ra Quincena de Abril",
                "4-2"  => "2da Quincena de Abril",
                "5-1"  => "1ra Quincena de Mayo",
                "5-2"  => "2da Quincena de Mayo",
                "6-1"  => "1ra Quincena de Junio",
                "6-2"  => "2da Quincena de Junio",
                "7-1"  => "1ra Quincena de Julio",
                "7-2"  => "2da Quincena de Julio",
                "8-1"  => "1ra Quincena de Agosto",
                "8-2"  => "2da Quincena de Agosto",
                "9-1"  => "1ra Quincena de Septiembre",
                "9-2"  => "2da Quincena de Septiembre",
                "10-1" => "1ra Quincena de Octubre",
                "10-2" => "2da Quincena de Octubre",
                "11-1" => "1ra Quincena de Noviembre",
                "11-2" => "2da Quincena de Noviembre",
                "12-1" => "1ra Quincena de Diciembre",
                "12-2" => "2da Quincena de Diciembre"
            ];

            // If current year, only show fortnights up to current fortnight
            if ($year == Carbon::now()->year) {
                $currentMonth = Carbon::now()->month;
                $currentDay = Carbon::now()->day;
                $currentFortnight = $currentDay <= 15 ? 1 : 2;

                $filteredPeriods = [];
                foreach ($periods as $key => $value) {
                    $parts = explode('-', $key);
                    $month = (int)$parts[0];
                    $fortnight = (int)$parts[1];

                    if ($month < $currentMonth || ($month == $currentMonth && $fortnight <= $currentFortnight)) {
                        $filteredPeriods[$key] = $value;
                    }
                }
                $periods = $filteredPeriods;
            }
        }

        // * get years availables
        $years = array();
        for ($i = 0; $i < 6; $i++) {
            $years[] = Carbon::now()->subYears($i)->year;
        }

        // * return the view
        return Inertia::render('Incidents/Index', [
            "incidentStatuses" => array_values($incidentStatuses),
            "generalDirections" => array_values($generalDirections),
            "employees" => $employees,
            "reportTypes" => $reportTypes,
            "periods" => $periods,
            "options" => [
                "generalDirecctionId" => $generalDirecctionId,
                "year" => $year,
                "period" => $period,
                "type" => $repType,
                "dateGeneration" => Carbon::now()->format("Y-m-d")
            ],
            "years" => $years
        ]);
    }

    function getIncidentsByEmployee(Request $request, string $employee_number)
    {
        $employee =  $this->findEmployee($employee_number);
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        // * retrive the query params
        if ($request->filled('year') && $request->filled('month')) {
            $options = [
                'year' => $request->input('year'),
                'month' => $request->input('month'),
            ];
        }

        // todo: calculate the breadcrumns based on where the request come from
        $previous_path = parse_url(url()->previous(), PHP_URL_PATH);
        if ($previous_path == '/incidents') {
            $breadcrumbs = array(
                ["name" => "Inicio", "href" => "/"],
                ["name" => "Incidencias", "href" => url()->previous()],
                ["name" => "Incidencias del empleado", "href" => ""],
            );
        } else {
            $breadcrumbs = array(
                ["name" => "Inicio", "href" => "/"],
                ["name" => "Vista Empleados", "href" => route('employees.index')],
                ["name" => "Empleado: $employee->employeeNumber", "href" => route('employees.show', $employee->employeeNumber)],
                ["name" => "Incidencias", "href" => ""],
            );
        }

        // * calculate status
        $status = array(
            'name' => 'BAJA',
            'class' => 'border border-red-400 text-red-600'
        );
        if ($employee->active) {
            $status = array(
                'name' => 'ACTIVO',
                'class' => 'border border-green-400 text-green-600'
            );
        }

        // * calculate status checa
        $checa = array(
            'name' => 'REGISTRA ASISTENCIA',
            'class' => 'border border-green-400 text-green-600'
        );
        if ($employee->checa != 1) {
            $checa = array(
                'name' => 'NO REGISTRA ASISTENCIA',
                'class' => 'border border-red-400 text-red-600'
            );
        }

        // * get working hours
        $hours = array();
        $workingHours = WorkingHours::where("employee_id", $employee->id)->first();
        if ($workingHours != null) {
            if ($workingHours->toeat == null) {
                array_push($hours, $workingHours->checkin . "-" . $workingHours->checkout);
            } else {
                array_push($hours, $workingHours->checkin . "-" . $workingHours->toeat);
                array_push($hours, $workingHours->toarrive . "-" . $workingHours->checkout);
            }
        }

        // * catalog incident status
        $incidentStatuses = IncidentState::where("id", ">", 1)->select('id', 'name')->get()->toArray();

        // Validate if photo employee exists $employee->photo in public folder
        $employeePhoto = '/images/unknown.png';
        // validate if photo exists in directory
        if ($employee->photo != null) {
            $employeePhoto = public_path($employee->photo);
            if (file_exists($employeePhoto)) {
                $employeePhoto = asset($employee->photo);
            }
        }

        // * get years availables
        $years = array();
        for ($i = 0; $i < 6; $i++) {
            $years[] = Carbon::now()->subYears($i)->year;
        }

        // * return the view
        return Inertia::render('Incidents/Employee', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "breadcrumbs" => $breadcrumbs,
            "status" => (object) $status,
            "checa" => (object) $checa,
            "workingHours" => $hours,
            "incidentStatuses" => array_values($incidentStatuses),
            "options" => isset($options) ? $options : null,
            "employeePhoto" => $employeePhoto,
            "years" => $years
        ]);
    }

    /**
     * retrive the incidents of the employee in json format
     *
     * @param  string $employee_number
     * @return void
     */
    function employeeIncidentsJson(Request $request, string $employee_number): JsonResponse
    {

        // * retrive the querys
        $year = $request->query('year');
        $month = $request->query('month');

        // * make to range of dates
        $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();

        try {
            // * attempt to retrive the incidents
            $data = $this->employeeIncidentService->getIncidents($employee_number, $startOfMonth, $endOfMonth);

            // * validate if the data need to be filtered
            if ($request->query->has("onlyPendings")) {
                $data = array_filter($data, fn($item) => $item['incident_state_id'] == self::$INCIDENT_STATE_PENDING);
            }

            return response()->json($data, 200);
        } catch (ModelNotFoundException $nf) {
            Log::error("Employee not found at attempting to retrive the incidents of the employee '{employeeNmber}'", [
                "employeeNmber" => $employee_number,
                "message" => $nf->getMessage()
            ]);
            return response()->json([
                "message" => "Employee not found"
            ], 404);
        } catch (\Throwable $th) {
            Log::error("Fail at attempting to retrive the incidents of the employee '{employeeNmber}: {message}'", [
                "employeeNmber" => $employee_number,
                "message" => $th->getMessage()
            ]);
            return response()->json([
                "message" => "Fail at attempting to retrive the incidents"
            ], 409);
        }
    }


    /**
     * update the status of the incident
     *
     * @param  mixed $request
     * @param  mixed $incident_id
     * @return void
     */
    function updateIncidentState(Request $request, int $incident_id)
    {

        // * validate the request
        $request->validate([
            "state_id" => "required|exists:incident_states,id"
        ]);

        // * retrive the incident id
        $incident = Incident::with('employee')->find($incident_id);
        if ($incident == null) {
            Log::warning("Incident id '{incidentId}' not found when attempting to update the status at IncidentController.updateIncidentStatus", ["incidentId" => $incident_id]);
            return redirect()->back()->withErrors([
                "message" => "La incidencia no se encuentra en el sistema o no está disponible."
            ])->withInput();
        }

        // tmp data for loggin
        $oldStateValue = $incident->incident_state_id;
        $employee = $incident->employee;

        // * attempt to update the state of the incident
        try {

            $incident->incident_state_id = $request->input('state_id');
            $incident->save();

            Log::notice("The state of the incident with id '{incident_id}' of the employee with id '{employee_id}' was updated from '{old_value}' to '{new_value}'.", [
                "incident_id" => $incident->id,
                "employee_id" => $employee->id,
                "old_value" => $oldStateValue,
                "new_value" => $request->input('state_id')
            ]);
        } catch (\Throwable $th) {

            Log::error("Fail to update the state of the incident id '{incident_id}' at IncidentController.updateIncidentStatus: {message}", [
                "message" => $th->getMessage(),
                "incident_id" => $incident->id
            ]);

            return redirect()->back()->withErrors([
                "message" => "Error al actualizar el estado de la incidencia, intente de nuevo o comuníquese con el administrador."
            ])->withInput();
        }

        // TODO: Calculate where to redirect based on where the request is come from
        return redirect()->back()->with('success', 'Estado de la incidencia actualizada.');
    }


    /**
     * make the incident report
     *
     * @param  mixed $request
     * @return void
     */
    function makeReport(Request $request)
    {
        // * validate the request data
        $validator = Validator::make($request->query(), [
            'general_direction_id' => 'required|numeric',
            'year' => 'required|numeric|min:2020',
            'period' => 'required',
            'report_type' => 'required|in:monthly,fortnight'
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator->errors()->messages())->withInput();
        }

        // * prepare the inputs
        $__generalDirection = $request->input('general_direction_id');
        $__year = $request->input('year');
        $__period = $request->input('period');
        $__reportType = $request->input('report_type');
        $generalDirection = GeneralDirection::find($__generalDirection);
        $startDate = null;
        $endDate = null;
        $totales = array(
            'omissions' => 0,
            'delays' => 0,
            'absents' => 0,
            'acumulations' => 0,
            'total' => 0,
        );
        $title = "Reporte de incidencias";

        // * get employees with incidences
        $employees = array();
        if ($__reportType == 'monthly') {
            $startDate = Carbon::parse("$__year-$__period-01")->startOfDay();
            $endDate = Carbon::parse("$__year-$__period-01")->endOfMonth()->endOfDay();
            $title = "Reporte de incidencias del mes de " . $this->monthName($__period - 1);
        }
        if ($__reportType == 'fortnight') {
            $month = explode("-", $__period)[0];
            $quin = explode("-", $__period)[1];
            if ($quin == 1) {
                $startDate = Carbon::parse("$__year-$month-01");
                $endDate = Carbon::parse("$__year-$month-15");
            } else {
                $startDate = Carbon::parse("$__year-$month-16");
                $endDate = Carbon::parse("$__year-$month-01")->endOfMonth()->endOfDay();
            }

            $title = "Reporte de incidencias del " . $startDate->format('d M Y') . ' al ' . $endDate->format('d M Y');
        }
        $employees = $this->getEmployeesWithIncidentsByDirection($__generalDirection, $startDate, $endDate);
        $employees = array_map(fn($item) => (array) $item, $employees);

        // * get the incident of each employee for the report
        $totales = array(
            'omissions' => 0,
            'delays' => 0,
            'absents' => 0,
            'acumulations' => 0,
            'total' => 0,
        );

        /**
         * @var array<string,mixed> $employee
         * reference to the original array element rather than a copy
         */
        foreach ($employees as &$employee) {

            $employee['nivel'] = "No disponible";
            $employee['puesto'] = "No disponible";

            // * get the resume of incidents
            $resumeIncidents = $this->getIncidentsOfEmployeeGrupedByType($employee['id'], $startDate, $endDate);

            // * append the properties ('noEmployee', 'nivel', 'puesto', 'delays', 'absents', 'acumulations', 'totalAbsentsd' )
            $plaza = \App\Services\EmployeeRHService::getPlazaByEmployeeNumber($employee['employeeNumber']);
            if ($plaza) {
                $employee['nivel'] = $plaza->nivel->NIVEL ?? "No disponible";
                $employee['puesto'] = $plaza->puesto->PUESTO ?? "No disponible";
            }

            $employee['noEmployee'] = $employee['employeeNumber'];
            $employee['delays'] = $resumeIncidents['delays'];
            $employee['absents'] = $resumeIncidents['absents'];
            $employee['acumulations'] = $resumeIncidents['acumulations'];
            $employee['totalAbsents'] = $resumeIncidents['totalAbsents'];

            // * add totals
            $totales['delays'] = $totales['delays'] + $resumeIncidents['delays'];
            $totales['absents'] = $totales['absents'] + $resumeIncidents['absents'];
            $totales['acumulations'] = $totales['acumulations'] + $resumeIncidents['acumulations'];
            $totales['total'] = $totales['total'] + $resumeIncidents['totalAbsents'];
        }

        // * make excel document
        $date = ["start" => $startDate, "end" => $endDate];
        $incidentReport = new IncidentsReport($employees, $date, $generalDirection->name, $totales, $title);
        $documentContent = $incidentReport->create();
        if ($documentContent === false) {
            // TODO: Log fail
            throw new \Exception("Fail to make the report document");
        }


        // * save the file on a temporally file and downlaod them
        $fileName = sprintf("%s.xlsx", (string) Str::uuid());
        $filePath = sprintf("tmp/incidentReports/$fileName");
        if (Storage::disk('local')->put($filePath, $documentContent)) {
            Log::notice("User {userName} generate a incident report of period {start} to {end}", [
                "userName" => Auth::user()->name,
                ...$date
            ]);
        }

        // * download the file
        return Storage::disk('local')->download($filePath, "reporte-incidencias.xlsx");
    }

    function makeIncidentsOfEmployee(Request $request, string $employee_number)
    {

        // * validate the user level
        if (Auth::user()->level_id != 1) {
            Log::warning('User ' . Auth::user()->name . ' tried to create incidents.');
            abort(404);
        }

        $request->validate([
            'date' => 'required|date|before:today',
        ]);

        // * get employee
        try {
            $employee = $this->employeeService->getEmployee($employee_number);
        } catch (ModelNotFoundException $ex) {
            //TODO: log exception
        }

        // * get working hours and working days of the employee
        $workingHours = WorkingHours::where('employee_id', $employee->id)->first();
        $workingDays = WorkingDays::where('employee_id', $employee->id)->first();

        // * calculate if the date is midweek or weekend
        // *    Get the day of the week as a number (7 = Sunday, 6 = Saturday, 5 = Friday, ...)
        $date = Carbon::parse($request->input('date'));
        $dayNumber = $date->format('N');
        $workDays = array('week');
        $dayIs = ($dayNumber < 6) ? "week" : "weekend";

        if ($workingDays) {
            if ($workingDays->weekend == 1) {
                array_push($workDays, 'weekend');
            }
            if ($workingDays->week == 0) {
                $key = array_search('week', $workDays);
                array_splice($workDays, $key, 1);
            }
        }


        // * calculate the incidents if the date target is on the employee schedule work
        if (in_array($dayIs, $workDays)) {
            try {

                $incidentService = new IncidentService(
                    $employee->id,
                    $workingHours,
                    $date->format('Y-m-d')
                );

                $incidentService->calculateAndStoreIncidentsV2();
            } catch (\Exception $e) {
                Log::error('Error al crear la incidencias del empleado id: ' . $employee->id . ' - ' . $e->getMessage() . ' - ' . $e->getTraceAsString());

                // return redirect()
                //     ->route('employee', ['employee_id' => $employee->id, 'general_direction_id' => $employee->general_direction_id])
                //     ->with('error', 'Ocurrió un error. Por favor, verifique que el empleado cuente con un horario establecido correctamente e intente nuevamente.');
                return redirect()->back()->withErrors([
                    "message" => "Error al generar las incidencias del dia " . $date->format('Y-M-d')
                ])->withInput();
            }

            Log::notice('El usuario ' . Auth::user()->name . ' creó las incidencias para el empleado id: ' . $employee->id . ' del día ' . $date->format('Y-m-d'));
        }

        return redirect()->route('employees.show', $employee_number);
    }


    function createIncidentsJob(Request $request)
    {

        // * validate the date
        $request->validate([
            'date' => 'date|before_or_equal:today'
        ]);

        \App\Jobs\CreateIncidentsDate::dispatch($request->input('date'));
    }


    #region private methods
    /**
     * find Employee
     *
     * @param  string $employee_number
     * @return \App\ViewModels\EmployeeViewModel|\Illuminate\Http\RedirectResponse
     */
    private function findEmployee(string $employee_number)
    {

        // * attempt to get the employee
        try {
            return $this->employeeService->getEmployee($employee_number);
        } catch (ModelNotFoundException $nf) {

            Log::warning("Employee with employee number '$employee_number' not found");

            //TODO: redirect to not found page

            // * redirect back
            return redirect()->back()->withErrors([
                "employee_number" => "Empleado no encontrado",
                "message" => "Empleado no encontrado"
            ])->withInput();
        }
    }

    /**
     * @param  int|string $generalDirecctionId
     * @param  string|Date|Carbon $from
     * @param  string|Date|Carbon $to
     * @return array<EmployeeViewModel>
     */
    private function getEmployeesWithIncidentsByDirection(
        int $generalDirecctionId,
        $from,
        $to
    ) {

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

        /*
    |--------------------------------------------------------------------------
    | Get incidents (con regla especial 18 / 28)
    |--------------------------------------------------------------------------
    */

        $incidents = Incident::whereBetween('date', [$from, $to])
            ->whereHas("employee", function ($employee) use ($generalDirecctionId, $specialEmployeeNumbers) {

                if ($generalDirecctionId == 18) {

                    $employee->where('general_direction_id', 18)
                        ->whereNotIn('employee_number', $specialEmployeeNumbers);
                } elseif ($generalDirecctionId == 28) {

                    $employee->where(function ($q) use ($specialEmployeeNumbers) {
                        $q->where('general_direction_id', 28)
                            ->orWhereIn('employee_number', $specialEmployeeNumbers);
                    });
                } else {

                    $employee->where('general_direction_id', $generalDirecctionId);
                }

                return $employee;
            })
            ->get();


        /*
    |--------------------------------------------------------------------------
    | Group by employee
    |--------------------------------------------------------------------------
    */

        $groupedByEmployee = $incidents->groupBy('employee_id')->all();


        /*
    |--------------------------------------------------------------------------
    | Get employees
    |--------------------------------------------------------------------------
    */

        $employeesQuery = Employee::with([
            'generalDirection',
            'direction'
        ])->whereIn('id', array_keys($groupedByEmployee));


        /*
    |--------------------------------------------------------------------------
    | Filter employees by user level
    |--------------------------------------------------------------------------
    */

        if (Auth::user()->level_id > 1) {
            $employeesOfUser = $this->employeeService->getEmployeesOfUser();
            $employeesQuery->whereIn(
                'id',
                $employeesOfUser->pluck('id')->all()
            );
        }


        /*
    |--------------------------------------------------------------------------
    | Transform to ViewModel
    |--------------------------------------------------------------------------
    */

        $employeesRaw = $employeesQuery->get()->all();

        $employees = array_map(function ($employeeData) {
            return EmployeeViewModel::fromEmployeeModel($employeeData);
        }, $employeesRaw);


        /*
    |--------------------------------------------------------------------------
    | Append total incidents
    |--------------------------------------------------------------------------
    */

        foreach ($employees as &$empl) {
            try {
                $empl->totalIncidents = count($groupedByEmployee[$empl->id]);
            } catch (\Throwable $th) {
                $empl->totalIncidents = 0;
            }
        }

        return $employees;
    }
    /**
     * get the resume of incidents of the employee in a period
     *
     * @param  int $employee_id
     * @param  Date|string $from
     * @param  Date|string $to
     * @return array [ 'id', 'delays', 'absents', 'acumulations', 'totalAbsents' ]
     */
    private function getIncidentsOfEmployeeGrupedByType($employee_id, $from, $to)
    {

        // * get incidents by type
        $delays = Incident::where('employee_id', $employee_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereIn('incident_type_id', array(2, 6))
            ->count();

        $absents = Incident::where('employee_id', $employee_id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->whereIn('incident_type_id', array(1, 3, 4, 5, 7, 8, 9, 10))
            ->count();

        $response = [
            'id' => $employee_id,
            'delays' => $delays,
            'absents' => $absents,
            'acumulations' => 0,
            'totalAbsents' => $absents
        ];

        if ($delays > 0 || $absents > 0) {
            // for 5 delays 1 absent
            $acumulations = (int)($delays / 5);
            $response['acumulations'] = $acumulations;
            $response['totalAbsents'] = $absents + $acumulations;
        }

        return $response;
    }

    private function monthName($i)
    {
        $months = array('Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
        return $months[$i];
    }

    #endregion

}
