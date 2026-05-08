<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Record;
use App\Models\WorkingHours;
use DateTime;

class DailyReportFactory {
    /**
     * @var array<Employee> $employees
     */
    protected array $employees = [];
    protected DateTime $dateReport;
    
    /**
     *
     * @param  array<Employee>|Collection<Employee> $employees
     * @param  string|DateTime|null $dateReport
     * @return void
     */
    function __construct($employees, $dateReport) {
        if( $employees instanceof Collection){
            $this->employees = $employees->all();
        }else{
            $this->employees = $employees;
        }

        if( $dateReport != null){
            if( $dateReport instanceof DateTime){
                $this->dateReport = $dateReport;
            }else{
                $this->dateReport = new DateTime($dateReport);
            }
        }else{
            $this->dateReport = new DateTime();
        }
    }
    
    /**
     * makeReportData
     *
     * @return array
     */
    function makeReportData(){
        $data = array();

        foreach( $this->employees as $employee) {
            $employeeData = $employee->toArray();
            array_push( $data, $this->makeEmployeeRow( $employeeData ));
        }

        return $data;
    }

    /**
     * makeEmployeeRow
     *
     * @param  Employee|Array $employee
     * @return mixed
     */
    private function makeEmployeeRow($employee){

        if( $employee instanceof Employee){
            $employee = $employee->toArray();
        }
        
        // * prepare response data
        $responseData = array();
        $responseData['name'] = $employee['name'];
        $responseData['employee_number'] = substr( $employee['plantilla_id'], 1);
        $responseData['direction'] = $employee['general_direction']['abbreviation'] ?? 'Sin dirección';
        $responseData['checkin'] = 'S/H';
        $responseData['toeat'] = 'S/H';
        $responseData['toarrive'] = 'S/H';
        $responseData['checkout'] = 'S/H';

        $checaComida = false;

        // TODO:  Get general data from RH DB
        // $employee_info = EmployeeRh::select('NUMEMP', 'NOMBRE', 'APELLIDO', 'RFC')->where('NUMEMP', $employee_number)->first();
        // if ($employee_info) {
        //     $name = Str::title( $employee_info->APELLIDO.' '.$employee_info->NOMBRE );
        // }

        // * manually get the working hours
        $workingHours = WorkingHours::where('employee_id', $employee['id'])->first();

        // * validate if the employee has working hours assigned
        if( $workingHours == null ) {
            return $responseData;
        }

        // * validate if the employee has a check record on the day
        if ( !$workingHours->checkin && !$workingHours->checkout){
            return $responseData;
        }
        

        // * validate if the employee has multiple working hours
        if (!empty($workingHours->toeat) && !empty($workingHours->toarrive)) {
            $checaComida = true;
        }

        
        // * get check records of the employee
        $records = Record::select('check')
            ->where('employee_id', $employee['id'])
            ->whereDate('check', $this->dateReport->format('Y-m-d'))
            ->get();

        // * get the hours checked in the day
        $recordsArray = [];
        foreach ($records as $record) {
            $timeRecord = Carbon::parse( $record->check)->format("H:i");
            if (!in_array($timeRecord, $recordsArray)) {
                $recordsArray[] = $timeRecord;
            }
        }

        // * iterate records
        $checkin = false;
        $checkout = false;
        $eat = false;
        $arrive = false;
        foreach ($recordsArray as $timeRecord) {
            if (!$checaComida) { // * horario corrido
                // Using Carbon for better time comparison
                $scheduleStart = Carbon::parse($workingHours->checkin)->format('H:i');
                $scheduleEnd = Carbon::parse($workingHours->checkout)->format('H:i');

                // Sort timestamps chronologically
                if (count($recordsArray) >= 2) {
                    $firstRecord = $recordsArray[0];
                    $lastRecord = $recordsArray[count($recordsArray) - 1];

                    // Assign first record as checkin and last record as checkout
                    $checkin = $firstRecord;
                    $checkout = $lastRecord;
                } elseif (count($recordsArray) == 1) {
                    // If only one record exists, compare it with the midpoint of the schedule
                    $scheduleMidpoint = Carbon::parse($scheduleStart)
                        ->average(Carbon::parse($scheduleEnd));
                        
                    if (strtotime($recordsArray[0]) <= $scheduleMidpoint->timestamp) {
                        $checkin = $recordsArray[0];
                    } else {
                        $checkout = $recordsArray[0];
                    }
                }

                $eat = '----';
                $arrive = '----';
                $checkin = $checkin ?: '*';
                $checkout = $checkout ?: '*';
            } else { // * horario quebrado
                // Using Carbon for better comparison
                $schedulePoints = [
                    'checkin' => Carbon::parse($workingHours->checkin),
                    'toeat' => Carbon::parse($workingHours->toeat),
                    'toarrive' => Carbon::parse($workingHours->toarrive),
                    'checkout' => Carbon::parse($workingHours->checkout)
                ];

                // Define threshold for grouping records (e.g., 2 minutes)
                $threshold = 2;

                // Group records that are within threshold minutes of each other
                $groupedRecords = [];
                $currentGroup = [];

                foreach ($recordsArray as $index => $record) {
                    if (empty($currentGroup)) {
                        $currentGroup[] = $record;
                    } else {
                        $lastRecord = Carbon::parse($currentGroup[count($currentGroup) - 1]);
                        $currentRecord = Carbon::parse($record);

                        if ($lastRecord->diffInMinutes($currentRecord) <= $threshold) {
                            $currentGroup[] = $record;
                        } else {
                            $groupedRecords[] = $currentGroup;
                            $currentGroup = [$record];
                        }
                    }
                }

                if (!empty($currentGroup)) {
                    $groupedRecords[] = $currentGroup;
                }

                // Assign records to appropriate schedule points
                foreach ($groupedRecords as $group) {
                    $groupTime = Carbon::parse($group[0]);
                    $closestPoint = null;
                    $minDiff = PHP_FLOAT_MAX;

                    foreach ($schedulePoints as $type => $scheduleTime) {
                        $diff = abs($groupTime->diffInMinutes($scheduleTime));
                        
                        if ($diff < $minDiff) {
                            $minDiff = $diff;
                            $closestPoint = $type;
                        }
                    }

                    // Assign record to closest schedule point if not already assigned
                    switch ($closestPoint) {
                        case 'checkin':
                            if (!$checkin) $checkin = $group[0];
                            break;
                        case 'toeat':
                            if (!$eat) $eat = $group[0];
                            break;
                        case 'toarrive':
                            if (!$arrive) $arrive = $group[0];
                            break;
                        case 'checkout':
                            $checkout = $group[0]; // Always take the latest checkout
                            break;
                    }
                }

                // Set default values for unassigned points
                $checkin = $checkin ?: '*';
                $eat = $eat ?: '*';
                $arrive = $arrive ?: '*';
                $checkout = $checkout ?: '*';
            }
        }

        $responseData['checkin'] = $checkin;
        $responseData['toeat'] = $eat;
        $responseData['toarrive'] = $arrive;
        $responseData['checkout'] = $checkout;

        return $responseData;
    }
}
