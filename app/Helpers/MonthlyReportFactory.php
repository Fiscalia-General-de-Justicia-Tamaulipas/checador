<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Employee;
use App\Models\Record;
use App\Models\WorkingHours;
use DateTime;

class MonthlyReportFactory {

    /**
     * @var array<Employee> $employees
     */
    protected array $employees = [];
    protected int $year;
    protected int $month;
    
    /**
     *
     * @param  array<Employee>|Collection<Employee> $employees
     * @param  string|DateTime|null $dateReport
     * @return void
     */
    function __construct($employees, $year, $month) {
        if( $employees instanceof Collection){
            $this->employees = $employees->all();
        }else{
            $this->employees = $employees;
        }
        
        $this->year = $year;

        $this->month = $month;
    }
    
    /**
     * makeReportData
     *
     * @return array
     */
    function makeReportData(){
        
        $users = array();
        foreach ($this->employees as $employee) {
            $employeeRow = $this->makeEmployeeRow($employee);
            array_push( $users, $employeeRow);
        }
        
        // * prepare response
        $data = array(
            'year' => $this->year,
            'month' => $this->months($this->month),
            'users' => $users
        );

        return $data;
    }

    /**
     * makeEmployeeRow
     *
     * @param  Employee $employee
     * @return mixed
     */
    private function makeEmployeeRow($employee){
        
        $checaComida = false;

        // * manually get the working hours
        $workingHours = WorkingHours::where('employee_id', $employee->id)->first();
        if ($workingHours != null) {
            if ($workingHours->toeat && $workingHours->toarrive) {
                $checaComida = true;
            }
        }

        $checadas = array();
        
        $date = new \DateTime("$this->year-$this->month-01");

        for ($i=1; $i < 32; $i++) {
            if ($i == $date->format('d')) {
                $checkin = '';
                $checkout = '';
                $eat = '';
                $arrive = '';
                // Get checkin
                $records = Record::select('check')
                    ->where('employee_id', $employee->id)
                    ->whereDate('check', $date->format('Y-m-d'))
                    ->get();

                if ($workingHours) {
                    
                    // Horario corrido
                    $hour1 = strtotime($workingHours->checkin);
                    $hour2 = strtotime($workingHours->checkout);
                    
                    // horario quebrado
                    $hour3 = strtotime($workingHours->toeat);
                    $hour4 = strtotime($workingHours->toarrive);

                    $recordsArray = [];
                    foreach ($records as $record) {
                        $dateRecord = new \DateTime($record->check);
                        $timeRecord = $dateRecord->format('H:i');

                        if (!in_array($timeRecord, $recordsArray)) {
                            $recordsArray[] = $timeRecord;
                        }
                    }


                    foreach ($recordsArray as $timeRecord) {
                        $diffCheckin = round(abs(strtotime($timeRecord) - $hour1) / 3600, 2);
                        $diffCheckout = round(abs(strtotime($timeRecord) - $hour2) / 3600, 2);

                        $diffToEat = round(abs(strtotime($timeRecord) - $hour3) / 3600, 2);
                        $diffToArrive = round(abs(strtotime($timeRecord) - $hour4) / 3600, 2);

                        if (!$checaComida) {
                            if ($diffCheckin < $diffCheckout && !$checkin) {
                                $checkin = $timeRecord;
                            } else {
                                $checkout = $timeRecord;
                            }
                        } else {
                            if ($diffCheckin < $diffToEat && $checkin == '') {
                                $checkin = $timeRecord;
                            } elseif ($diffToEat < $diffToArrive) {
                                $eat = $timeRecord;
                            } elseif ($diffToArrive < $diffCheckout) {
                                $arrive = $timeRecord;
                            } else {
                                $checkout = $timeRecord;
                            }
                        }
                    }
                } else {
                    $checkin = 'S/H';
                    $checkout = 'S/H';
                }
                // Array
                $checadas[] = array(
                    'diaNombre' => $this->translateDayName($date->format('D')),
                    'dia' => $date->format('d'),
                    'entrada' => $checkin,
                    'comidaS' => $eat,
                    'comidaE' => $arrive,
                    'salida' => $checkout,
                );
                $date->modify('+1 day');
            }
        }

        return array(
            'name' => $employee->name,
            'direction' => $employee->direction->name ?? 'Sin dirección',
            'checadas' => $checadas
        );

    }

    private function translateDayName($name) {
        $days['Mon'] = 'Lun';
        $days['Tue'] = 'Mar';
        $days['Wed'] = 'Mie';
        $days['Thu'] = 'Jue';
        $days['Fri'] = 'Vie';
        $days['Sat'] = 'Sab';
        $days['Sun'] = 'Dom';

        return $days[$name];
    }

    private function months($month) {
        $names = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        return $names[$month - 1];
    }

}