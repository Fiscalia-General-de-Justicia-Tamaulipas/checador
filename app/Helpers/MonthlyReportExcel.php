<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


class MonthlyReportExcel {

    private $data = array();
    private $generalDirection = '';
    
    /**
     * __construct
     *
     * @param  array<array> $employees
     * @param  string $generalDirection
     * @return void
     */
    public function __construct(array $employees, string $generalDirectionName) {
        $this->data = $employees;
        $this->generalDirection = $generalDirectionName;
    }
    
    /**
     * make
     *
     * @return string|false return the file content as buffer
     */
    public function make() {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $this->makeHeader($sheet);

        // Init cells
        $row = 9;
        $rowEnd = $row + 3;
        $rowC = 6;

        $sheet->getColumnDimension('A')->setWidth(20);

        // $total = 1;
        foreach ($this->data['users'] as $user) {
            
            $this->makeEmployeeRow($sheet, $user, $row, $rowEnd, $rowC);
            $rowC += 8;
            
            // Next user info
            $row += 8;
            $rowEnd = $row + 3;
            // $total ++;
        }

        // * build the file
        $writer = new Xlsx($spreadsheet);


        // * save the file in a temporaly buffer
        ob_start();
        $writer->save('php://output');
        $content = ob_get_contents();
        ob_end_clean();
        
        return $content;
    }

    private function makeHeader(Worksheet $sheet){
        // HEADER
        $sheet->mergeCells('A1:AG1');
        $sheet->mergeCells('A2:AG2');
        $sheet->mergeCells('A4:AG4');
        
        // Center text
        $sheet->getStyle("A1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A1', 'Fiscalía General de Justicia del Estado de Tamaulipas');
        $sheet->setCellValue('A2', $this->generalDirection);
        $sheet->setCellValue('A4', 'Asistencia del mes de '.$this->data['month'] . ' del ' . $this->data['year'] );
        
        // BG blue color White Title
        $sheet->getStyle('A1')
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('033270');

        $sheet->getStyle('A1')->getFont()
            ->getColor()
            ->setARGB(\PhpOffice\PhpSpreadsheet\Style\Color::COLOR_WHITE);

    }

    private function makeEmployeeRow(Worksheet $sheet, array $employee, int $row, int $rowEnd, int $rowC){

        $sheet->mergeCells("A$row:A$rowEnd"); // Merge cells name
        $employeeText = $employee['name'] . "\n(" . ($employee['direction'] ?? 'Sin dirección') . ")";
        $sheet->setCellValue("A$row", $employeeText); // Name + Direction
        $sheet->getStyle("A$row")->getAlignment()->setWrapText(true);
        // Center name
        $sheet->getStyle("A$row")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A$row")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('B'.$rowC.':B'.($rowC+1)); // Merge cell Day
        $sheet->setCellValue('B'.$rowC, 'Día');
        $sheet->getStyle("B$rowC")->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $sheet->setCellValue('B'.($row), 'Entrada');
        $sheet->setCellValue('B'.($row+1), 'Comida S');
        $sheet->setCellValue('B'.($row+2), 'Comida E');
        $sheet->setCellValue('B'.($row+3), 'Salida');

        $column = 'B';
        foreach ($employee['checadas'] as $checada) {
            $column ++;
            $sheet->setCellValue($column.$rowC, $checada['diaNombre']);
            $sheet->setCellValue($column.($rowC+1), $checada['dia']);
            $sheet->setCellValue($column.($rowC+3), $checada['entrada']);
            $sheet->setCellValue($column.($rowC+4), $checada['comidaS']);
            $sheet->setCellValue($column.($rowC+5), $checada['comidaE']);
            $sheet->setCellValue($column.($rowC+6), $checada['salida']);
            
            // Width cell
            $sheet->getColumnDimension($column)->setWidth(7);
        }

        // Color numbers of day
        $sheet->getStyle('B'.$rowC.':'.$column.$rowC)
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('b9c6d6');
        
        $sheet->getStyle('C'.($rowC+1).':'.$column.($rowC+1))
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('b9c6d6');
        
        // Center numbers of the day
        $sheet->getStyle("B$rowC:$column$rowC")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("C".($rowC+1).":$column".($rowC+1))->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        $sheet->getRowDimension($rowC + 2)->setRowHeight(6); // Space between day and time
        $sheet->getRowDimension($rowC + 7)->setRowHeight(3); // Line blue, separator
        
        // Line blue
        $sheet->getStyle('A'.($rowC + 7).':'.$column.($rowC + 7))
            ->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('033270');
    }

}