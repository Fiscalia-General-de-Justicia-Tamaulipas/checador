<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use App\Services\{
    EmployeeService,
    JustificationService
};
use App\ViewModels\EmployeeViewModel;
use App\Http\Requests\{
    NewJustificationRequest,
    UpdateJustificationRequest
};
use App\Models\{
    TypeJustify,
    Justify
};

class JustificationController extends Controller
{

    protected EmployeeService $employeeService;
    protected JustificationService $justificationService;

    function __construct(EmployeeService $employeeService, JustificationService $justificationService)
    {
        $this->employeeService = $employeeService;
        $this->justificationService = $justificationService;
    }


    public function index(Request $request)
    {
        $elementsToTake = 25;
        $page = $request->query("p", 1);
        $justifications = [];
        $data = [];

        $__authUser = Auth::user();
        $__currentLevel = intval($__authUser->level_id);

        // 🔹 Empleados especiales (misma regla que en getEmployees)
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

        if ($__currentLevel > 0) {

            $data = Justify::with(['type', 'employee'])
                ->when($__currentLevel > 1, function ($query) use ($__currentLevel, $__authUser, $specialEmployeeNumbers) {

                    return $query->whereHas('employee', function ($emp) use ($__currentLevel, $__authUser, $specialEmployeeNumbers) {

                        /*
                     |--------------------------------------------------------------------------
                     | NIVEL 2+ → General Direction
                     |--------------------------------------------------------------------------
                     */
                        if ($__currentLevel >= 2) {

                            $gd = $__authUser->general_direction_id;

                            // GD 18 → excluir especiales
                            if ($gd == 18) {
                                $emp->where('general_direction_id', 18)
                                    ->whereNotIn('employee_number', $specialEmployeeNumbers);
                            }
                            // GD 28 → incluir especiales
                            elseif ($gd == 28) {
                                $emp->where(function ($q) use ($specialEmployeeNumbers) {
                                    $q->where('general_direction_id', 28)
                                        ->orWhereIn('employee_number', $specialEmployeeNumbers);
                                });
                            }
                            // Resto de GD
                            else {
                                $emp->where('general_direction_id', $gd);
                            }
                        }

                        /*
                     |--------------------------------------------------------------------------
                     | NIVEL 3+ → Dirección
                     |--------------------------------------------------------------------------
                     */
                        if ($__currentLevel >= 3) {
                            $emp->where('direction_id', $__authUser->direction_id);
                        }

                        /*
                     |--------------------------------------------------------------------------
                     | NIVEL 4+ → Subdirección
                     |--------------------------------------------------------------------------
                     */
                        if ($__currentLevel >= 4) {
                            $emp->where('subdirectorate_id', $__authUser->subdirectorates_id);
                        }

                        return $emp;
                    });
                })
                ->orderByDesc('created_at')
                ->skip(($page - 1) * $elementsToTake)
                ->take($elementsToTake)
                ->get()
                ->all();
        }

        foreach ($data as $element) {
            $justifications[] = [
                "id" => $element->id,
                "employee_name" => $element->employee?->name,
                "type_name" => $element->type->name,
                "date_start" => $element->date_start,
                "date_finish" => $element->date_finish,
                "date_register" => $element->created_at,
                "details" => $element->details
            ];
        }

        return Inertia::render('Justifications/Index', [
            "justifications" => array_values($justifications),
            "paginator" => [
                "page" => $page,
                "elements" => $elementsToTake,
                "previous" => $page > 1,
                "next" => count($data) >= $elementsToTake
            ]
        ]);
    }
    /**
     * returned view to edit the justify
     *
     * @param  int $justification_id
     * @return void
     */
    function editJustify(Request $request, int $justification_id)
    {

        // * retrive the previous route
        $parsedUrl = parse_url(url()->previous());

        // * Check if query parameters exist and extract them
        $queryParams = array();
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);

            // * Store the query parameters in the session
            session(['queryParams' => $queryParams]);
        }

        // * retrive the justify
        $justify = Justify::with(['type', 'employee'])->find($justification_id);
        if ($justify == null) {
            //TODO: Redirecto to not found page
            throw new \Exception("Element not found");
        }

        // * get the employee
        $employee =  $this->findEmployee($justify->employee->employeeNumber());
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        // * get the justifications
        $justificationsType = TypeJustify::select('id', 'name')->get()->toArray();

        // TODO: calculate the breadcrumns based on where the request come from
        $breadcrumbs = array(
            ["name" => "Inicio", "href" => "/dashboard"],
            ["name" => "Vista Empleados", "href" => route('employees.index')],
            ["name" => "Empleado: $employee->employeeNumber", "href" => route('employees.show', $employee->employeeNumber)],
            ["name" => "Justificantes", "href" => route('employees.justifications.index', ["employee_number" => $employee->employeeNumber, ...$queryParams])],
            ["name" => "Editar justificante", "href" => ""],
        );

        // * return the view
        return Inertia::render('Justifications/EditJustifyDay', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "justificationsType" => $justificationsType,
            "justify" => $justify,
            "breadcrumbs" => $breadcrumbs
        ]);
    }

    function updateJustify(UpdateJustificationRequest $request, $justification_id)
    {

        // * retrive the justify model
        $justify = Justify::with(['type', 'employee'])->find($justification_id);
        if ($justify == null) {
            //TODO: Redirecto to not found page
            throw new \Exception("Element not found");
        }

        // * get the employee
        $employee =  $this->findEmployee($justify->employee->employeeNumber());
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        // * attempt to justify the day
        try {

            $this->justificationService->updateJustify(
                $justify->id,
                request: $request,
                employee: $employee
            );
        } catch (\Throwable $th) {
            Log::error("Fail to update the justify the day: {message}", [
                "message" => $th->getMessage()
            ]);

            return redirect()->back()->withErrors([
                "Error al actualizar el justificante, intente de nuevo o comuníquese con el administrador."
            ])->withInput();
        }

        // * retrive and clear queryParams of the session
        $queryParams = session('queryParams', []);
        if (!empty($queryParams)) {
            session()->forget('queryParams');
        }

        // * redirect to justifications of the employee
        return redirect()->route('employees.justifications.index', [
            "employee_number" => $employee->employeeNumber,
            ...$queryParams
        ]);
    }

    /**
     * delete a justification
     *
     * @param  int $justification_id
     * @return mixed
     */
    function destroy(int $justification_id)
    {

        // * retrive the justify model
        $justify = Justify::with(['type', 'employee'])->find($justification_id);
        if ($justify == null) {
            return response()->json(["message" => "Justification not found on the system."], 404);
        }

        // * get the employee
        $employee =  $this->findEmployee($justify->employee->employeeNumber());
        if ($employee instanceof RedirectResponse) {
            return response()->json(["message" => "Employee not found on the system."], 404);
        }

        // * attempt to delete the justify
        try {
            $this->justificationService->deleteJustificationById($justify->id);
        } catch (\Throwable $th) {
            Log::error("Fail to delete the justify the day: {message}", [
                "message" => $th->getMessage()
            ]);

            return response()->json(["message" => "Error al eliminar el justificante, intente de nuevo o comuníquese con el administrador."], 500);
        }

        return redirect()->route('employees.justifications.index', [
            "employee_number" => $employee->employeeNumber,
        ])->with('success', 'Justificante eliminado correctamente.');
    }

    /**
     * show the view for display the justifications of the employee
     *
     * @param  string $employee_number
     * @return mixed
     */
    function showJustificationOfEmployee(Request $request, string $employee_number)
    {

        // * get the employee
        $employee =  $this->findEmployee($employee_number);
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        // * get the range day from the querys
        $start = Carbon::now();
        $end = Carbon::now();

        if ($request->query("y") && $request->query("m")) {
            $start = Carbon::createFromDate($request->query("y"), $request->query("m"), 1)->startOfMonth();
            $end = Carbon::createFromDate($request->query("y"), $request->query("m"), 1)->endOfMonth();
        }

        if ($request->query("from") && $request->query("to")) {
            $start = Carbon::parse($request->query("from"));
            $end = Carbon::parse($request->query("to"));
        }

        $justifications = $this->justificationService->getJustificationsEmployee(
            $employee,
            $start->format("Y-m-d"),
            $end->format("Y-m-d")
        )->toArray();

        // TODO: calculate the breadcrumns based on where the request come from
        $breadcrumbs = array(
            ["name" => "Inicio", "href" => "/dashboard"],
            ["name" => "Vista Empleados", "href" => route('employees.index')],
            ["name" => "Empleado: $employee->employeeNumber", "href" => route('employees.show', $employee->employeeNumber)],
            ["name" => "Justificantes", "href" => ""],
        );

        // * return the view
        return Inertia::render('Justifications/EmployeeIndex', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "justifications" => array_values($justifications),
            "breadcrumbs" => $breadcrumbs,
            "dateRange" => sprintf("Del %s al %s", $start->format("d M y"), $end->format("d M y")),
            "from" => $start->format("Y-m-d"),
            "to" => $end->format("Y-m-d"),
        ]);
    }


    /**
     * show the view for justify a day
     *
     * @param  Request $request
     * @param  string $employee_number
     * @return mixed
     */
    function showJustifyDay(Request $request, string $employee_number)
    {

        // * get the employee
        $employee =  $this->findEmployee($employee_number);
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        $initialDay = Carbon::today();
        if ($request->query('day') != null) {
            $initialDay = Carbon::parse($request->query('day'));
        }

        // * get the justifications
        $justificationsType = TypeJustify::select('id', 'name')->get()->toArray();

        // TODO: calculate the breadcrumns based on where the request come from
        $breadcrumbs = array(
            ["name" => "Inicio", "href" => "/dashboard"],
            ["name" => "Vista Empleados", "href" => route('employees.index')],
            ["name" => "Empleado: $employee->employeeNumber", "href" => route('employees.show', $employee->employeeNumber)],
            ["name" => "Justificar dia", "href" => ""],
        );

        // * return the view
        return Inertia::render('Justifications/JustifyDay', [
            "employeeNumber" => $employee->employeeNumber,
            "employee" => $employee,
            "justificationsType" => $justificationsType,
            "initialDay" => $initialDay->format('Y-m-d'),
            "breadcrumbs" => $breadcrumbs
        ]);
    }

    /**
     * store a justification
     *
     * @return mixed
     */
    function storeJustification(NewJustificationRequest $request, string $employee_number)
    {

        // * get the employee
        $employee =  $this->findEmployee($employee_number);
        if ($employee instanceof RedirectResponse) {
            return $employee;
        }

        // * attempt to justify the day
        try {

            $this->justificationService->justify(
                request: $request,
                employee: $employee
            );
        } catch (\Throwable $th) {
            Log::error("Fail to justify the day: {message}", [
                "message" => $th->getMessage()
            ]);

            return redirect()->back()->withErrors([
                "Error al justificar el día, intente de nuevo o comuníquese con el administrador."
            ])->withInput();
        }

        // * redirect to employee show
        return redirect()->route('employees.show', $employee->employeeNumber);
    }

    /**
     * retrive the document of the justification
     *
     * @param  int $justification_id
     * @return void
     */
    function getJustificationFile(int $justification_id)
    {

        // * get the justify model
        $justify = Justify::find($justification_id);
        if ($justify == null) {
            return response()->json(["message" => "Justification not found on the system."], 404);
        }

        // * get the document
        if (Storage::disk('local')->exists($justify->file)) {
            $fileContents = Storage::disk('local')->get($justify->file);
            $fileName = basename($justify->file);

            return response($fileContents, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $fileName . '"');
        } else {
            return response()->json(['message' => 'File not found'], 404);
        }
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
    #endregion


}
