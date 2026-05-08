<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Exception;
use App\Services\{
    EmployeeService,
    InactiveService,
    JustificationService
};
use App\Models\{
    Department,
    Employee,
    GeneralDirection,
    Direction,
    Subdirectorate,
    WorkingHours,
    Record,
    Incident,
};
use App\ViewModels\{
    CalendarEvent
};
use App\Http\Requests\{
    UpdateEmployeeRequest,
    UpdateEmployeeStatusRequest
};
use App\Helpers\EmployeeKardexRecords;
use App\Helpers\EmployeeKardexExcel;

class EmployeeController extends Controller
{

    protected EmployeeService $employeeService;
    protected JustificationService $justificationService;
    protected InactiveService $inactiveService;

    function __construct( EmployeeService $employeeService, JustificationService $justificationService, InactiveService $inactiveService)
    {
        $this->employeeService = $employeeService;
        $this->justificationService = $justificationService;
        $this->inactiveService = $inactiveService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $currentPage = $request->query('p', 1);
        $elementsToTake = 50;
        $generalDirectionId = null;
        $directionId = 0;
        $subdirectionId = 0;
        $AUTH_USER = Auth::user();

        // * set by defaul the user general direction asigneds
        $generalDirectionId = $AUTH_USER->general_direction_id;

        if($AUTH_USER->level_id > 1){
            if( $AUTH_USER->level_id > 2){
                $directionId = $AUTH_USER->direction_id;
            }else{
                // Level 2: can select from allowed GDs
                if($request->filled('gd')){
                    $requestedGd = $request->query("gd");
                    $allowedGdIds = [$AUTH_USER->general_direction_id];

                    // Special rules for specific General Directions
                    if ($AUTH_USER->general_direction_id == 16) {
                        $allowedGdIds = [16, 17, 18];
                    } elseif ($AUTH_USER->general_direction_id == 17) {
                        $allowedGdIds = [17, 18];
                    }

                    // Validate that the requested GD is allowed
                    if (in_array($requestedGd, $allowedGdIds)) {
                        $generalDirectionId = $requestedGd;
                    }
                }

                $directionId = $request->filled('d') ?$request->query("d") : 0;
            }

            $subdirectionId = $request->query("sd");
        }else{
            if( $request->filled('gd')){
                $generalDirectionId = $request->query("gd");
            }
            if( $request->filled('d')){
                $directionId = $request->query("d");
            }
            if( $request->filled('sd')){
                $subdirectionId = $request->query("sd");
            }
        }

        // * get catalogs
        $generalDirections = GeneralDirection::select('id', 'name')
            ->orderBy('name', 'asc')
            ->get()
            ->all();

        // * Filter General Directions based on user level and special rules
        if ($AUTH_USER->level_id > 1) {
            // Non-admin users: apply access control based on general_direction_id
            $allowedGdIds = [$AUTH_USER->general_direction_id];

            // Special rules for specific General Directions
            if ($AUTH_USER->general_direction_id == 16) {
                // GD 16: can see 16, 17, 18
                $allowedGdIds = [16, 17, 18];
            } elseif ($AUTH_USER->general_direction_id == 17) {
                // GD 17: can see 17 and 18
                $allowedGdIds = [17, 18];
            }

            $generalDirections = array_filter($generalDirections, function($gd) use ($allowedGdIds) {
                return in_array($gd['id'], $allowedGdIds);
            });
        }

        $directions = Direction::select('id', 'name', 'general_direction_id')
            ->where('general_direction_id', $generalDirectionId)
            ->orderBy('name', 'asc')
            ->get();

        $subdirectorate = Subdirectorate::select('id', 'name', 'direction_id')
            ->where('direction_id', $directionId)
            ->orderBy('name', 'asc')
            ->get();

        // * prepare the filters
        $filters = array();

        if(isset($generalDirectionId)){
            $filters[ EmployeeFiltersEnum::GD ] = $generalDirectionId;
            $directions = $directions->where('general_direction_id', $generalDirectionId);
        }
        if(isset($directionId) && $directionId > 0){
            $filters[ EmployeeFiltersEnum::D ] = $directionId;
            $subdirectorate = $subdirectorate->where('direction_id', $directionId);
        }

        if(isset($subdirectionId) && $subdirectionId > 0){
            $filters[ EmployeeFiltersEnum::SD ] = $subdirectionId;
        }

        if($request->filled("se")){
            $filters['search'] = $request->query("se");
        }

        // If user is not admin load active employees only
        if(Auth::user()->level_id != 1) { 
            $filters['active'] = 1;
        }

        // * get employees
        $totalEmployees = 0;
        $data = $this->employeeService->getEmployees(
            take: $elementsToTake,
            skip: ($elementsToTake * ($currentPage - 1)),
            filters:$filters,
            total:$totalEmployees
        );

        // * verify if display paginator
        $showPaginator = $elementsToTake < $totalEmployees;

        // * make paginator
        $paginator = [
            "from" => $elementsToTake * ($currentPage - 1),
            "to" =>  $elementsToTake * $currentPage,
            "total" => $totalEmployees,
            "pages" =>  range(1, ceil( $totalEmployees / $elementsToTake))
        ];

        // * return the viewe
        return Inertia::render('Employees/Index', [
            "employees" => $data,
            "general_direction" => $generalDirections,
            "directions" => array_values( $directions->toArray() ),
            "subdirectorate" => array_values( $subdirectorate->toArray() ),
            "showPaginator" => $showPaginator,
            "filters" => [
                "gd" => $generalDirectionId,
                "d" => $directionId,
                "sd" => $subdirectionId,
                "page" => $currentPage,
                "search" => $request->filled("se") ?$request->input("se") :null
            ],
            "paginator" => $paginator
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $employee_number)
    {

        // * attempt to get the employee
        try
        {
            $employee = $this->employeeService->getEmployee($employee_number);
        }
        catch (ModelNotFoundException $nf) {
            Log::warning("Employee with employee number '$employee_number' not found");
            abort(404);
        }
        catch(UnauthorizedException $ue)
        {
            Log::warning("The user with id '{userId}' can't access the data of the employee with employee number '{employeeNumber}': {message}", [
                "userId" => $employee_number,
                "employeeNumber" => $employee_number,
                "message" => $ue->getMessage(),
            ]);
            abort(403);
        }
        catch (\Throwable $th)
        {
            Log::error("Unhandle exception at attempting to get the employee at EmployeeController.show: {message}", [
                "employee_number" => $employee_number,
                "message" => $th->getMessage(),
            ]);
            abort(500);
        }

        // calculate status
        $status = array(
            'name' => 'BAJA',
            'class' => 'text-xs p-1 rounded border border-red-500 text-red-600'
        );
        if ($employee->active) {
            $status = array(
                'name' => 'ACTIVO',
                'class' => 'text-xs p-1 rounded border border-green-500 text-green-600'
            );
        }

        // calculate status checa
        $checa = array(
            'name' => 'REPORTA INCIDENCIAS',
            'class' => 'text-xs p-1 rounded border border-green-500 text-green-600'
        );
        if ($employee->checa != 1) {
            $checa = array(
                'name' => 'NO REPORTA INCIDENCIAS',
                'class' => 'text-xs p-1 rounded border border-yellow-500 text-yellow-600'
            );
        }

        // * get working hours
        $hours = array();
        $workingHours = WorkingHours::where("employee_id", $employee->id)->first();
        if( $workingHours != null){
            if( $workingHours->toeat == null){
                array_push($hours, $workingHours->checkin . "-" . $workingHours->checkout);
            }else {
                array_push($hours, $workingHours->checkin . "-" . $workingHours->toeat);
                array_push($hours, $workingHours->toarrive . "-" . $workingHours->checkout);
            }
        }

        // * calculate the breadcrumns based on where the request come from
        $breadcrumbs = array(
            ["name"=> "Inicio", "href"=> route('employees.index') ],
            ["name"=> "Empleado: $employee->employeeNumber", "href"=> route('employees.show', $employee->employeeNumber)],
        );

        if( parse_url( $request->headers->get('referer'), PHP_URL_PATH ) == '/inactive' ){
            $breadcrumbs[1] = [
                "name"=> "Inactivos", "href"=> $request->headers->get('referer'),
            ];
        }

        // Validate if photo employee exists $employee->photo in public folder
        $employeePhoto = '/images/unknown.png';
        // validate if photo exists in directory
        if($employee->photo != null) {
            $employeePhoto = public_path($employee->photo);
            if (file_exists($employeePhoto)) {
                $employeePhoto = asset($employee->photo);
            }
        }

        // * return the view
        return Inertia::render('Employees/Show', [
            "employeeNumber" => $employee_number,
            "employee" => isset($employee) ?$employee :null,
            "status" => (object) $status,
            "checa" => (object) $checa,
            "workingHours" => $hours,
            "breadcrumbs" => $breadcrumbs,
            "employeePhoto" => $employeePhoto,
        ]);
    }

    /**
     * show the form for editing the employee
     *
     * @param  string $employee_number
     * @return void
     */
    public function edit(Request $request, string $employee_number)
    {
        // * retrive the employee
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        // * retrieve the query parameters to filter the catalogs if is necessary
        $_gd = $employee->generalDirectionId ?? 1;
        $_di = $employee->directionId ?? 1;
        $_sd = $employee->subDirectionId ?? 1;

        if($request->filled('gd')){
            $_gd = $request->query('gd');
            $_di = $request->query('di');
            $_sd = $request->query('sd');
        }

        // * retrive the catalogs
        $generalDirections = GeneralDirection::select('id','name')->get()->sortBy('name')->all();

        $directions = Direction::select('id','name', 'general_direction_id')
            ->where('general_direction_id', $_gd)
            ->get()->sortBy('name')->all();

            $subdirectorates = Subdirectorate::select('id', 'name', 'direction_id')
            ->where('direction_id', $_di)
            ->get()->sortBy('name')->all();

            $deparments = Department::select('id', 'name', 'subdirectorate_id')
            ->where('subdirectorate_id', $_sd)
            ->get()->sortBy('name')->all();

        // * return the view
        return Inertia::render('Employees/Edit', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "generalDirections" => array_values($generalDirections),
            "directions" => array_values($directions),
            "subdirectorates" => array_values($subdirectorates),
            "deparments" => array_values($deparments),
            "defaultValues" => (object) array(),
        ]);
    }

    /**
     * Update the employee in storage.
     *
     * @param  UpdateEmployeeRequest $request
     * @param  string $employee_number
     * @return void
     */
    public function update(UpdateEmployeeRequest $request, string $employee_number)
    {
        // * retrive the employee
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        $authUser = Auth::user();

        if ($authUser->level_id > 2 &&
            $authUser->general_direction_id != $request->input('general_direction_id')
        ) {
            return redirect()->back()->withErrors([
                "message" => "Solicite el cambio de dirección general al administrador del sistema."
            ])->withInput();
        }

        // * update the employee data
        try {
            $this->employeeService->updateEmployee( $employee->employeeNumber, $request->request->all());
        }catch (\Throwable $th) {
            return redirect()->back()->withErrors([
                "message" => $th->getMessage()
            ])->withInput();
        }

        // * redirect to show view
        return redirect()->route('employees.show', ['employee_number' => $employee->employeeNumber ]);

    }

    /**
     * Update the employee status in storage.
     *
     * @param  UpdateEmployeeStatusRequest $request
     * @param  string $employee_number
     * @return void
     */
    public function updateStatus(UpdateEmployeeStatusRequest $request, string $employee_number)
    {
        // * retrive the employee
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        $employeeModel = Employee::find($employee->id);

        // * update the status of the employee
        try
        {
            $this->inactiveService->changeStatus(
                $this->employeeService,
                $employeeModel,
                $request->comments ?? "",
                $request->status_id,
                $request->file('file')
            );
        }
        catch (\Throwable $th)
        {
            return redirect()->back()->withErrors([
                "message" => $th->getMessage()
            ])->withInput();
        }

        // * redirect to show view
        return redirect()->route('employees.show', ['employee_number' => $employee->employeeNumber ]);
    }


    public function eventsJson(Request $request, string $employee_number): JsonResponse{

        // * get the range day from the querys
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();
        if($request->has("from") && $request->has("to")){
            $from = Carbon::parse($request->query("from"));
            $to = Carbon::parse($request->query("to"));
        }

        // * retrive the employee
        $employee = $this->employeeService->getEmployee($employee_number);

        // * get the records

        $records = Record::where('employee_id', $employee->id)
            ->whereDate('check', '>=', $from->format('Y-m-d'))
            ->whereDate('check', '<=', $to->format('Y-m-d'))
            ->get();

        // * get the incidents
        $incidents = Incident::with(['type', 'state'])
            ->where('employee_id', $employee->id)
            ->whereDate('date', '>=', $from->format('Y-m-d'))
            ->whereDate('date', '<=', $to->format('Y-m-d'))
            ->get();

        // * get the justifications
        $justifications = $this->justificationService->getJustificationsEmployee( $employee, $from->format('Y-m-d'), $to->format('Y-m-d') );

        // * parse events
        $events = array();
        foreach($records as $record) {
            $event = new CalendarEvent("E$record->id", "", $record->check, $record->check);
            $event->color = "#27ae60";
            $event->type = "RECORD";
            array_push( $events, $event);
        }
        foreach($incidents as $incident){
            $title = $incident->type->name;
            $event = new CalendarEvent("I$incident->id",$title, $incident->date, $incident->date);
            $event->color = "#ef8b11";
            $event->type = "INCIDENT";
            array_push( $events, $event);
        }
        foreach($justifications as $justify) {
            $justify_title = $justify->type->name;
            if( $justify->date_finish != null ){
                $_from = Carbon::parse($justify->date_start);
                $_to = Carbon::parse($justify->date_finish);
                // Loop through each day from start to end date
                for ($date = $_from; $date->lte($_to); $date->addDay())
                {
                    $event = new CalendarEvent("J$justify->id", $justify_title, $date->format('Y-m-d'), $date->format('Y-m-d'));
                    $event->color = "#3ea1e7";
                    $event->type = "JUSTIFY";
                    array_push( $events, $event);
                }
            }
            else{
                $event = new CalendarEvent("J$justify->id", $justify_title, $justify->date_start->format('Y-m-d'), $justify->date_start->format('Y-m-d'));
                $event->color = "#3ea1e7";
                $event->type = "JUSTIFY";
                array_push( $events, $event);
            }
        }

        return response()->json($events, 200);
    }

    public function kardexEmployee(Request $request, string $employee_number){
        // * retrive the employee
        $employee = null;
        try {
            $employeeVM = $this->employeeService->getEmployee($employee_number);
            $employee = Employee::with(['workingHours', 'workingDays', 'generalDirection'])->findOrFail($employeeVM->id);
        } catch (ModelNotFoundException $nf) {
            Log::warning("Employee with employee number '$employee_number' not found");

            // * redirect back
            return redirect()->back()->withErrors([
                "employee_number" => "Empleado no encontrado",
                "message" => "Empleado no encontrado"
            ])->withInput();
        }

        $workingHours = $employee->workingHours;
        $year = $request->input('year');
        $today = new \DateTime();


        // * attempt to get the cache kardex record from the mongodb
        $recordMongo = \App\Models\KardexRecord::where('employee_id', $employee->id)
            ->where('report_date', '=', $today->format('Y-m-d'))
            ->where('year', '=', $year)
            ->first();

        if ($recordMongo) {
            $dataUser = $recordMongo->data;
        } else {
            if ($workingHours) {
                if (!$workingHours->checkin || $workingHours->checkin == '') {
                    throw new \Exception("The employee has no working schedule assigned.");
                }
            }

            // * make the records of the employee kardex
            $employeeKardexRecords = new EmployeeKardexRecords($employee);
            $dataUser = $employeeKardexRecords->makeRecords($year);
        }

        // * make the excel file
        $employeeKardexExcel = new EmployeeKardexExcel($dataUser, $employee->generalDirection->name);
        $documentContent = $employeeKardexExcel->create();
        if( $documentContent === false){
            // TODO: Log fail
            throw new \Exception("Fail to make the report document");
        }

        // * store the file
        $fileName = sprintf("%s.xlsx", (string) Str::uuid() );
        $filePath = sprintf("tmp/kardex/$fileName");
        if( Storage::disk('local')->put( $filePath, $documentContent ) ){
            Log::info('User ' . Auth::user()->name . ' generate daily report for year ' . $request->input('year'));
        }else {
            Log::warning('Fail at stored the report of the employee kardex by User ' . Auth::user()->name);
        }

        // * download the file
        $name = "kardex-empleado.xlsx";
        return Storage::disk('local')->download($filePath, $name);
    }

    public function workinHoursHistory(string $employee_number)
    {
        // * retrive the employee
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        // * retrive workin hours of the employee
        $workingHours = WorkingHours::where('employee_id', $employee->id)
            ->with(['user'])
            ->orderByDesc('created_at')
            ->withTrashed()
            ->take(10)
            ->get()
            ->all();

        // TODO: calculate the breadcrumns based on where the request come from
        $breadcrumbs = array(
            ["name"=> "Inicio", "href"=> "/dashboard"],
            ["name"=> "Vista Empleados", "href"=> route('employees.index') ],
            ["name"=> "Empleado: $employee->employeeNumber", "href"=> route('employees.show', $employee->employeeNumber)],
            ["name"=> "Historial de Horario", "href"=>""],
        );

        // * return the view
        return Inertia::render('Employees/WorkinHoursHistory', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "workingHours" => array_values($workingHours),
            "breadcrumbs" => $breadcrumbs
        ]);
    }

    #region Incidents
    public function incidentCreate(Request $request, string $employee_number) {

        // * retrive the employee
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        // * return the view
        return Inertia::render('Employees/Incidents/Create', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "date" => $request->filled('date') ?$request->query('date') :null
        ]);

    }

    public function removeIncident(Request $request, string $employee_number, int $incidentId)
    {
        // * validate if the user is Admin
        if(Auth::user()->level_id > 1){
            return redirect()->back()->withErrors([
                "message" => "You do not have the privileges to perform this action."
            ])->withInput();
        }

        // * attempt to get the employee, may cause a forbidden response if the user has no access.
        $employee = $this->findEmployee($employee_number);
        if($employee instanceof \Illuminate\Http\RedirectResponse){
            return $employee;
        }

        // * get the incident
        try
        {
            $incident = Incident::where('id', $incidentId)->firstOrFail();
        }
        catch(Exception)
        {
            return redirect()->back()->withErrors([
                "message" => "Incident not found"
            ])->withInput();
        }

        // * attempt to delete the incident
        $logData = [
            "incidentId" => $incident->id,
            "incidentData" => $incident
        ];
        $incident->delete();
        Log::notice("Deleted the incident '{incidentId}'; ", $logData);
    }

    #endregion

    #region private methods
    /**
     * find Employee
     *
     * @param  string $employee_number
     * @return \App\ViewModels\EmployeeViewModel|\Illuminate\Http\RedirectResponse
     */
    private function findEmployee(string $employee_number){

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
    #endregion

}

class EmployeeFiltersEnum {
    const GD = 'general_direction_id';
    const D = 'direction_id';
    const SD = 'subdirectorate_id';
}