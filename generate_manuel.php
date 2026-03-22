<?php
require('config.php');
require('fpdf/fpdf.php');

// Language detection switch to english by default 
$lang = (isset($_SESSION['lang']) && $_SESSION['lang'] === 'fr') ? 'fr' : 'en';

$texts = [
    'access_denied' => ['fr' => "Accès refusé.", 'en' => "Access denied."],
    'order_error'   => ['fr' => "Commande introuvable ou accès non autorisé.", 'en' => "Order not found or unauthorized access."],
    'file_missing'  => ['fr' => "Le plan de montage (maquette) est introuvable.", 'en' => "The assembly plan (blueprint) is missing."],
    'file_empty'    => ['fr' => "Le fichier maquette est vide.", 'en' => "The blueprint file is empty."],
    'title'         => ['fr' => "MANUEL DE CONSTRUCTION", 'en' => "CONSTRUCTION MANUAL"],
    'page'          => ['fr' => "Page ", 'en' => "Page "],
    'final_render'  => ['fr' => "Aperçu du résultat final", 'en' => "Final result preview"],
    'step'          => ['fr' => "Étape ", 'en' => "Step "],
    'placement'     => ['fr' => "Placement des pièces de couleur : ", 'en' => "Placement of color pieces: "],
    'order'         => ['fr' => "Manuel_construction_commande_", 'en' => "Construction_manual_order_" ]
];

// Security and Invoice Recovery
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    die($texts['access_denied'][$lang]);
}

$userId = $_SESSION['user_id'];
$orderId = intval($_GET['order_id']);

// We retrieve the invoice AND the order information (image_id, style) to find the bricks
$sql = "SELECT c.id, c.image_id 
        FROM commandes c 
        WHERE c.id = ? AND c.user_id = ? 
        LIMIT 1";
$stmt = $db->prepare($sql);
$stmt->execute([$orderId, $userId]);
$commande = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$commande) {
    die($texts['order_error'][$lang]);
}

$imageId = $commande['image_id'];
$maquettePath = "uploads/maquette_" . $imageId . ".txt";

if (!file_exists($maquettePath)) {
    die($texts['file_missing'][$lang]);
}

// Reading of the maquette file
$lignes = file($maquettePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($lignes)) die($texts['file_empty'][$lang]);

array_shift($lignes); // Skip the first line to erase cost, error etc...

$briques = [];
$couleursUniques = [];
$maxX = 0;
$maxY = 0;

foreach ($lignes as $ligne) {
    // Split the format of the line
    $elements = preg_split('/\s+/', trim($ligne));
    if (count($elements) >= 4) {
        $refParts = explode('/', $elements[0]);
        $shapeInfo = explode('x', explode('-', $refParts[0])[0]); // Case of 1x2 with holes
        
        $w = isset($shapeInfo[0]) ? (int)$shapeInfo[0] : 1;
        $h = isset($shapeInfo[1]) ? (int)$shapeInfo[1] : 1;
        $couleur = $refParts[1];
        $x = (int)$elements[1];
        $y = (int)$elements[2];
        $rot = (int)$elements[3];

        // Case where the rotation is 90° or 270°
        // Inversion of W and H
        if ($rot == 1 || $rot == 3) {
            $temp = $w; $w = $h; $h = $temp;
        }

        $briques[] = [
            'w' => $w, 'h' => $h, 
            'color' => $couleur, 
            'x' => $x, 'y' => $y
        ];

        // Unique color store for the future steps
        if (!in_array($couleur, $couleursUniques)) {
            $couleursUniques[] = $couleur;
        }

        if ($x + $w > $maxX) $maxX = $x + $w;
        if ($y + $h > $maxY) $maxY = $y + $h;
    }
}

$maxX = max(1, $maxX);
$maxY = max(1, $maxY);

// Class PDF to make the manual
class PDF_Manual extends FPDF {
    public $lang;
    public $texts;

    function Header() {
        if(file_exists('logo.png')) $this->Image('logo.png',10,6,20);
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30, 10, utf8_decode($this->texts['title'][$this->lang]), 0, 0, 'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0, 10, utf8_decode($this->texts['page'][$this->lang]) . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function SetFillColorHex($hex) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $this->SetFillColor($r, $g, $b);
    }
}

// Initialisation of PDF
$pdf = new PDF_Manual();
$pdf->lang = $lang;
$pdf->texts = $texts;
$pdf->AliasNbPages();

// Adjusting to an A4 page
$cellW = 190 / $maxX;
$cellH = 220 / $maxY;
$cellSize = min($cellW, $cellH); 

$gridWidth = $maxX * $cellSize;
$startX = (210 - $gridWidth) / 2; // Center the image

function drawGrid($pdf, $briques, $couleursPlacees, $couleurEnCours, $cellSize, $startX, $startY) {
    // We draw the background (unplaced) first, then the foreground
    // so that the borders of the current pieces are clearly visible
    
    foreach ($briques as $b) {
        $x_pos = $startX + ($b['x'] * $cellSize);
        $y_pos = $startY + ($b['y'] * $cellSize);
        $w_pos = $b['w'] * $cellSize;
        $h_pos = $b['h'] * $cellSize;

        if ($b['color'] === $couleurEnCours) {
            // Current step: 
            // Normal background color
            $pdf->SetFillColorHex($b['color']);
            $pdf->Rect($x_pos, $y_pos, $w_pos, $h_pos, 'F');

            // Draw fluorescent yellow diagonal stripes
            $pdf->SetDrawColor(204, 255, 0); // Fluorescent yellow
            $pdf->SetLineWidth(0.4);
            $step = 1.5; // Spacing between stripes in mm
            
            for ($k = 0; $k < ($w_pos + $h_pos); $k += $step) {
                $x1 = max(0, $k - $h_pos);
                $y1 = min($h_pos, $k);
                $x2 = min($w_pos, $k);
                $y2 = max(0, $k - $w_pos);
                $pdf->Line($x_pos + $x1, $y_pos + $y1, $x_pos + $x2, $y_pos + $y2);
            }

            // Thick red border to spot it easily
            $pdf->SetDrawColor(255, 0, 0); 
            $pdf->SetLineWidth(0.6);
            $pdf->Rect($x_pos, $y_pos, $w_pos, $h_pos, 'D');

        } elseif ($couleurEnCours === "ALL" || in_array($b['color'], $couleursPlacees)) {
            // Already placed (or final page): Normal color + standard border
            $pdf->SetFillColorHex($b['color']);
            $pdf->SetDrawColor(50, 50, 50);
            $pdf->SetLineWidth(0.2);
            $pdf->Rect($x_pos, $y_pos, $w_pos, $h_pos, 'DF');
        } else {
            // Not yet placed: simulated "transparent black" with very dark gray
            $pdf->SetFillColor(30, 30, 30);
            $pdf->SetDrawColor(10, 10, 10);
            $pdf->SetLineWidth(0.1);
            $pdf->Rect($x_pos, $y_pos, $w_pos, $h_pos, 'DF');
        }
    }
}

// Page 1, Final Render
$pdf->AddPage();
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 10, utf8_decode($texts['final_render'][$lang]), 0, 1, 'C');
$startY = $pdf->GetY() + 5;
drawGrid($pdf, $briques, [], "ALL", $cellSize, $startX, $startY);

// Next Pages, assembly steps
$couleursPlacees = [];
$etape = 1;
$totalEtapes = count($couleursUniques);

foreach ($couleursUniques as $couleurActuelle) {
    $pdf->AddPage();
    
    // Step title
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0, 10, utf8_decode($texts['step'][$lang] . "$etape / $totalEtapes"), 0, 1, 'L');
    
    // Color indication
    $pdf->SetFont('Arial','',11);
    $pdf->Cell(65, 8, utf8_decode($texts['placement'][$lang]), 0, 0, 'L');
    $pdf->SetFillColorHex($couleurActuelle);
    $pdf->Cell(15, 8, "", 1, 0, 'C', true); // Color square
    $pdf->Cell(30, 8, " #" . strtoupper($couleurActuelle), 0, 1, 'L');
    $pdf->Ln(5);

    $startY = $pdf->GetY();
    
    // Step drawing
    drawGrid($pdf, $briques, $couleursPlacees, $couleurActuelle, $cellSize, $startX, $startY);

    // Add the current color to the "already placed" list for the next page
    $couleursPlacees[] = $couleurActuelle;
    $etape++;
}

// PDF Generation
$pdf->Output('I', $texts['order'][$lang].$orderId.'.pdf');
?>