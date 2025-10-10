<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use App\Models\{
    Justify,
    Incident
};
use App\Http\Requests\{
    NewJustificationRequest,
    UpdateJustificationRequest
};
use App\ViewModels\EmployeeViewModel;

use function Laravel\Prompts\form;

class JustificationService
{

    /**
     * justify
     *
     * @param  NewJustificationRequest $request
     * @param  EmployeeViewModel $employee
     * @return void
     */
    public function justify(NewJustificationRequest $request, EmployeeViewModel $employee)
    {

        // * store the file
        $filePath = $this->storeJustificationFile(
            file: $request->file('file'),
            employee_number: $employee->id,
            date: $request->input('initialDay')
        );

        DB::beginTransaction();

        // get the current user
        $currentUser = Auth::user();
        $startDay = Carbon::parse($request->input("initialDay"));
        $finishDay = Carbon::parse($request->input("endDay"));


        // * create the justification record(s)
        $message = "Día " . $startDay->format('d-m-Y') . " justificado correctamente";
        try {

            $justification = Justify::create([
                'employee_id' => $employee->id,
                'type_justify_id' => $request->input('type_id'),
                'date_start' => $request->input('initialDay'),
                'date_finish' => $request->input('endDay'),
                'file' => $filePath,
                'details' => $request->input('comments'),
                'user_id' => $currentUser->id
            ]);

            // * calculate the message 
            if ($request->input("endDay") != null) {
                $message = "Días " . $startDay . " al " . $finishDay->format('d-m-Y') . " justificados correctamente";
            }
        } catch (\Throwable $th) {
            Log::error("Fail to create the incident record of the employee {employeeName} from {startData} to {endDate} at JustificationService.justify: {message}", [
                "employeeName" => $employee->name,
                "startData" => $request->input('initialDay'),
                "endDate" => $request->input('endDay'),
                "message" => $th->getMessage()
            ]);
            DB::rollback();
            throw $th;
        }

        // TODO: Delete data from Mongo to re create object
        // $mongoRecord = \App\Models\KardexRecord::where('employee_id', (int)$employee_id)
        //     ->where('year', $today->format('Y'))
        //     ->first();
        // if ($mongoRecord) {
        //     $mongoRecord->delete();
        // }

        try {

            // * get the incidents
            $incidents = Collection::empty();
            $dateRange = "";
            if (!$request->input('multipleDays')) {
                $incidents = Incident::where('employee_id', $employee->id)
                    ->where('date', $request->input('initialDay'))
                    ->get();

                $dateRange = sprintf("del %s", $startDay);
            } else {
                $incidents = Incident::where('employee_id', $employee->id)
                    ->whereBetween('date', [$startDay->format("Y-m-d"), $finishDay->format("Y-m-d")])
                    ->get();

                $dateRange = sprintf("del %s al %s", $startDay, $finishDay);
            }


            // *  delete the incidens
            $flag = 0;
            if ($incidents) {
                foreach ($incidents as $inc) {
                    $inc->delete();
                    $flag++;
                }
            }
        } catch (\Throwable $th) {
            Log::error("Fail to delet the incidents of the employee {employeeName} from {startData} to {endDate} at JustificationService.justify: {message}", [
                "employeeName" => $employee->name,
                "startData" => $request->input('initialDay'),
                "endDate" => $request->input('endDay'),
                "message" => $th->getMessage()
            ]);
            DB::rollback();
            throw $th;
        }


        DB::commit();

        // * prinf some logs
        Log::notice('El usuario ' . Auth::user()->name . ' justificó al empleado ' . $employee->name . ': ' . $message);
        if (isset($flag)) {
            Log::notice("Se eliminó la incidencia (Total: $flag) $dateRange del empleado {employeeName} por el usuario {userName}", [
                "employeeName" => $employee->name,
                "employeeId" => $employee->id,
                "userName" => $currentUser->name,
                "userId" => $currentUser->id
            ]);
        }
    }

    /**
     * justify
     *
     * @param  NewJustificationRequest $request
     * @param  EmployeeViewModel $employee
     * @return void
     */
    public function updateJustify(int $justify_id, UpdateJustificationRequest $request, EmployeeViewModel $employee)
    {

        // * get the justify
        $justify = Justify::findOrFail($justify_id);

        DB::beginTransaction();

        // get the current user
        $currentUser = Auth::user();
        $startDay = Carbon::parse($request->input("initialDay"));
        $finishDay = Carbon::parse($request->input("endDay"));


        // * udpate the justification record
        try {

            $justify->type_justify_id = $request->input('type_id');
            $justify->date_start = $request->input('initialDay');
            $justify->date_finish = $request->input('endDay');
            $justify->details = $request->input('comments');
            $justify->user_id = $currentUser->id;

            // * store the file
            if ($request->file('file') != null) {

                // * store the new file
                $filePath = $this->storeJustificationFile(
                    file: $request->file('file'),
                    employee_number: $employee->id,
                    date: $request->input('initialDay')
                );

                // * set the new file paths
                $justify->file = $filePath;
            }

            $justify->save();
        } catch (\Throwable $th) {
            Log::error("Fail to update justify of the employee {employeeName} from {startData} to {endDate} at JustificationService.updateJustify: {message}", [
                "employeeName" => $employee->name,
                "startData" => $request->input('initialDay'),
                "endDate" => $request->input('endDay'),
                "message" => $th->getMessage()
            ]);
            DB::rollback();
            throw $th;
        }

        // TODO: Delete data from Mongo to re create object
        // $mongoRecord = \App\Models\KardexRecord::where('employee_id', (int)$employee_id)->where('year', $today->format('Y'))->first();
        // if ($mongoRecord) {
        //     $mongoRecord->delete();
        // }

        DB::commit();

        // * prinf some logs
        Log::notice('El usuario ' . Auth::user()->name . ' modifico la justificacion id ' . $justify->id);
    }


    /**
     * get justifications of the employee
     *
     * @param  EmployeeViewModel $employee
     * @param  string $startDate
     * @param  string $endDate
     * @return Collection<Justify>
     */
    public function getJustificationsEmployee(EmployeeViewModel $employee, $startDate, $endDate)
    {
        return Justify::with(['type'])
            ->where("employee_id", $employee->id)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date_start', [$startDate, $endDate])
                    ->orWhereBetween('date_finish', [$startDate, $endDate]);
            })
            ->get();
    }


    /**
     * store the justification file and return the path
     *
     * @param  mixed $file
     * @param  string $employee_number
     * @param  string    $date
     * @return string
     */
    private function storeJustificationFile($file, $employee_number, $date): string
    {
        $cDate = Carbon::parse($date);
        $name = sprintf("%s-%s.pdf", $employee_number, $cDate->format("Y-m-d"));
        return Storage::disk("local")->putFileAs('justificantes', $file, $name);
    }

    /**
     * store the justification file and return the path
     *
     * @param  string $fileName
     */
    private function removeFile($fileName): string
    {
        try {
            return Storage::disk("local")->delete($fileName);
            Log::notice("File $fileName deleted after update the justification.");
        } catch (\Throwable $th) {
            Log::error("Error at attempting to delete the file $fileName after update the justification: {messagge}", [
                "message" => $th->getMessage()
            ]);
        }
    }

    /**
     * delete the justification by id
     *
     * @param  int $justify_id
     * @return void
     */    public function deleteJustificationById(int $justify_id): void
    {
        // * get the justify
        $justify = Justify::findOrFail($justify_id);
        $employee = $justify->employee;
        DB::beginTransaction();
        try {
            // * delete the record
            $justify->delete();
        } catch (\Throwable $th) {
            Log::error("Fail to delete the justify id {justifyId} of the employee {
                employeeName} at JustificationService.deleteJustificationById: {message}", [
                "justifyId" => $justify_id,
                "employeeName" => $employee->name,
                "message" => $th->getMessage()
            ]);
            DB::rollback();
            throw $th;
        }
        DB::commit();
        Log::notice('El usuario ' . Auth::user()->name . ' eliminó la justificacion id ' . $justify->id . ' del empleado ' . $employee->name);
    }
}
