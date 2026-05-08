<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Collection;
use Codedge\Fpdf\Fpdf\Fpdf;
use Carbon\Carbon;
use App\Models\Employee;
use DateTime;

class DailyReportPdfFactory extends Fpdf {

    /**
     * @var array<Employee> $employees
     */
    protected array $employees = [];
    protected DateTime $dateReport;
    protected string $generalDirectionName;
    
    /**
     *
     * @param  array<Employee>|Collection<Employee> $employees
     * @param  string|DateTime|null $dateReport
     * @param  string $generalDirectionName
     * @return void
     */
    function __construct($employees, $dateReport, $generalDirectionName) {
        // Call the parent constructor
        parent::__construct( orientation:"P", unit:"mm", size:"letter");

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

        $this->generalDirectionName = $generalDirectionName;

        Carbon::setLocale('es');
    }

    public function makePdf(){
        $this->AliasNbPages();
        $this->AddPage();
        $this->body();
    }

    public function header()
    {
        // Logo
        $this->Image('images/logo_fgjtam.png', 8, 8, 43);

        $this->SetFont('Arial','B', 12);
        $this->Cell(47); // Move right

        $formattedDate = Carbon::parse($this->dateReport)
            ->translatedFormat('d \d\e F \d\e Y');

        $this->MultiCell(0, 5, mb_convert_encoding($this->generalDirectionName, 'ISO-8859-1', 'UTF-8') , 0, 'R', 0);
        // $this->Cell(45); // Move right
        $this->SetFont('Arial','',11);
        $this->Cell(0, 5, mb_convert_encoding('Reporte de asistencia del día', 'ISO-8859-1', 'UTF-8'), 0, 1, 'R');
        $this->Cell(0, 5, $formattedDate, 0, 1, 'R');

        // Salto de línea
        $this->Ln(2);

        // Draw table header on every page
        $this->drawTableHeader();
    }

    public function body() 
    {
        $this->SetFont('Arial','',11);

        if (count($this->employees) == 0) {
          $this->SetFillColor(217, 225, 235); // bg blue
          $this->SetTextColor(22, 47, 82); // blue color
          $this->Cell(0, 10, 'No hay empleados que mostrar', 0, 1, 'C', 1);
          $this->SetTextColor(0); // Black color
        } else {
          $this->SetFillColor(255);
          $this->SetTextColor(0);
          $rowHeight = 6; // Height of one row
          $loop = 1;
          $bg = false;

          foreach ($this->employees as $employee) {
            // Check if page changed to set bg color white
            if(($this->GetY() + $rowHeight) > ($this->GetPageHeight() - 20)) {
                $this->AddPage();
                $bg = false;
            }

            if ($bg) {
              $this->SetFillColor(236, 242, 246);
            } else {
              $this->SetFillColor(255);
            }

            $this->Cell(10, $rowHeight, $loop, 0, 0, 'L', 1);
            $this->Cell(85, $rowHeight, mb_convert_encoding($employee['name'], 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 1);
            $this->Cell(25, $rowHeight, mb_convert_encoding($employee['direction'] ?? 'S/D', 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 1);
            // Implement logic
            $this->Cell(20, $rowHeight, $employee['checkin'], 0, 0, 'C', 1);
            $this->Cell(20, $rowHeight, $employee['toeat'], 0, 0,  'C', 1);
            $this->Cell(20, $rowHeight, $employee['toarrive'], 0, 0, 'C', 1);
            $this->Cell(20, $rowHeight, $employee['checkout'], 0, 1,  'C', 1);
            $bg = !$bg;
            $loop ++;
          }
        }
    }

    public function footer() 
    {
        // Posición: a 1,5 cm del final
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I',8);
        // Número de página
        $this->Cell(0,10, mb_convert_encoding( 'Página', 'ISO-8859-1', 'UTF-8').$this->PageNo().'/{nb}',0,0,'C');
    }

    private function drawTableHeader() 
    {
        $headerHeight = 9;

        // $this->SetFillColor(217, 225, 233); // bg header table
        $this->SetFillColor(229, 232, 235); // bg header table
        $this->SetTextColor(22, 47, 82); // color header table
        $this->SetFont('Arial','',11);

        // First row - static cells
        $this->Cell(10, $headerHeight, '#', 'B', 0, 'L', 1);
        $this->Cell(85, $headerHeight, 'Nombre', 'B', 0, 'L', 1);
        $this->Cell(25, $headerHeight, 'Direccion', 'B', 0, 'L', 1);
        $this->Cell(20, $headerHeight, 'Entrada', 'B', 0, 'C', 1);

        // Save X position for next row
        $x = $this->GetX();
        $y = $this->GetY();

        // Multi-line cells
        $this->MultiCell(20, $headerHeight/2, "Salida\nComida", 'B', 'C', 1);
        $this->SetXY($x + 20, $y);
        $this->MultiCell(20, $headerHeight/2, "Entrada\nComida", 'B', 'C', 1);
        $this->SetXY($x + 40, $y);

        // Last cell
        $this->Cell(20, $headerHeight, 'Salida', 'B', 1, 'C', 1);

        $this->Cell(0, 1,'', 0, 1, 'C'); // White space
    }
}
