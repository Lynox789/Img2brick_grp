<?php
require('config.php');
require('fpdf/fpdf.php'); 

// Security and Invoice Recovery
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    die(msg('pdf_access_denied'));
}

$userId = $_SESSION['user_id'];
$orderId = intval($_GET['order_id']);

// We retrieve the invoice AND the order information (image_id, style) to find the bricks
$sql = "SELECT f.*, c.id as cmd_id, c.image_id, c.selected_style
        FROM facture f
        JOIN commandes c ON f.commande_id = c.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$orderId, $userId]);
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    die(msg('pdf_invoice_not_found'));
}

// Recovery of billing lines (The "Global Kit")
$sqlLignes = "SELECT *, snapshot_img FROM ligne_facture WHERE id_facture = ?";
$stmtLignes = $db->prepare($sqlLignes);
$stmtLignes->execute([$facture['id_facture']]);
$lignesFacture = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);


// DETAILED RECOVERY OF BRICKS
// The proposal ID must be found in mosaic_proposals
$strategy = str_replace('Algo ', 'ALGO_', $facture['selected_style']);

// We are looking for the corresponding proposal

$stmtProp = $db->prepare("SELECT id FROM mosaic_proposals WHERE image_id = ? AND strategy = ?");
$stmtProp->execute([$facture['image_id'], $strategy]);
$proposal = $stmtProp->fetch(PDO::FETCH_ASSOC);

$listeBriques = [];
if ($proposal) {
    // Complex request to have Name, Color and Quantity
    $sqlBricks = "
        SELECT 
            s.designation as forme,
            c.name as couleur_nom,
            c.hex_code,
            mpl.quantity_needed as qte,
            cat.estimated_price as prix_unit
        FROM mosaic_proposal_lines mpl
        JOIN lego_catalog cat ON mpl.catalog_id = cat.id
        JOIN lego_shapes s ON cat.shape_id = s.id
        JOIN lego_colors c ON cat.color_id = c.id
        WHERE mpl.proposal_id = ?
        ORDER BY c.name ASC, s.designation ASC
    ";
    $stmtBricks = $db->prepare($sqlBricks);
    $stmtBricks->execute([$proposal['id']]);
    $listeBriques = $stmtBricks->fetchAll(PDO::FETCH_ASSOC);
}

// PDF GENERATION

class PDF extends FPDF {
    function Header() {
        // Logo
        if(file_exists('logo.png')) $this->Image('logo.png',6,6,20);
        
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30,10,utf8_decode(msg('pdf_invoice_title')),0,0,'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode(msg('pdf_page')).$this->PageNo().'/{nb}',0,0,'C');
    }
    
    // Utility function to convert Hex (#FF0000) to RGB for FPDF
    function SetFillColorHex($hex) {
        $hex = str_replace('#', '', $hex);
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
        $this->SetFillColor($r, $g, $b);
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

// Company Info
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 6, utf8_decode("IMG2BRICKS SAS"), 0, 1);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 6, utf8_decode("12 Rue de la Brique"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("75000 PARIS"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("SIRET: 123 456 789 00012"), 0, 1);

// Invoice Info (Right Frame)
$pdf->SetXY(120, 40);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(30, 6, utf8_decode(msg('pdf_invoice_num')), 0, 0);
$pdf->SetFont('Arial','',11);
// We use str_pad to have a proper number (ex: 000042)
$numFac = str_pad($facture['id_facture'], 6, '0', STR_PAD_LEFT); 
$pdf->Cell(0, 6, $numFac, 0, 1);

$pdf->SetXY(120, 46);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(30, 6, utf8_decode(msg('pdf_invoice_date')), 0, 0);
$pdf->SetFont('Arial','',11);
$dateDoc = date("d/m/Y", strtotime($facture['date_document']));
$pdf->Cell(0, 6, $dateDoc, 0, 1);

// Customer Info
$pdf->Ln(15);
$pdf->SetX(110);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 6, utf8_decode(msg('pdf_billing_address')), 0, 1);
$pdf->SetX(110);
$pdf->SetFont('Arial','',11);
$pdf->MultiCell(80, 6, utf8_decode(
    $facture['nom_client'] . "\n" . 
    $facture['adresse_fact'] . "\n" . 
    $facture['cp_fact'] . " " . $facture['ville_fact']
));

// MAIN TABLE (Invoice Lines) 
$pdf->Ln(20);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 10, utf8_decode(msg('pdf_order_summary')), 0, 1);

// Headers
$pdf->SetFillColor(230,230,230);
$pdf->SetFont('Arial','B',10);
$pdf->Cell(110, 8, utf8_decode(msg('pdf_designation')), 1, 0, 'L', true);
$pdf->Cell(20, 8, utf8_decode(msg('pdf_qty')), 1, 0, 'C', true);
$pdf->Cell(30, 8, utf8_decode(msg('pdf_unit_price_ht')), 1, 0, 'R', true);
$pdf->Cell(30, 8, utf8_decode(msg('pdf_total_ht')), 1, 1, 'R', true);

// Data
$pdf->SetFont('Arial','',10);
$totalHT = 0;
$tempImageFile = null; // We prepare the variable for the temp file

foreach ($lignesFacture as $ligne) {
    $nomProduit = utf8_decode($ligne['designation_article_cache']);
    $qte = $ligne['quantite'];
    $pu = $ligne['prix_unitaire_ht'];
    $ligneTotal = $qte * $pu;
    $totalHT += $ligneTotal;

    $pdf->Cell(110, 8, $nomProduit, 1);
    $pdf->Cell(20, 8, $qte, 1, 0, 'C');
    $pdf->Cell(30, 8, number_format($pu, 2, ',', ' ').chr(128), 1, 0, 'R');
    $pdf->Cell(30, 8, number_format($ligneTotal, 2, ',', ' ').chr(128), 1, 1, 'R');

    // adding the snapshot image if exists
    if (!empty($ligne['snapshot_img'])) {
        
        $tempImageFile = sys_get_temp_dir() . '/img_facture_' . uniqid() . '.png';
        
        file_put_contents($tempImageFile, $ligne['snapshot_img']);
        
        // We save the current Y position
        $y = $pdf->GetY();
        
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(190, 6, utf8_decode(msg('pdf_archived_visual')), 'LR', 1, 'L');
        
        $pdf->Image($tempImageFile, 20, $pdf->GetY(), 30); 
        
        $pdf->Cell(190, 32, "", 'LRB', 1); 
        
        $pdf->SetFont('Arial', '', 10);
    }
}

// Total
$pdf->Ln(2);
$tva = $totalHT * 0.20; 
$ttc = $totalHT + $tva;
$posX = 140; 

$pdf->SetX($posX);
$pdf->Cell(20, 6, utf8_decode(msg('pdf_total_ht')), 0, 0, 'R'); 
$pdf->Cell(30, 6, number_format($totalHT, 2, ',', ' ').chr(128), 1, 1, 'R'); 

$pdf->SetX($posX);
$pdf->Cell(20, 6, utf8_decode(msg('pdf_tax_20')), 0, 0, 'R'); 
$pdf->Cell(30, 6, number_format($tva, 2, ',', ' ').chr(128), 1, 1, 'R'); 

$pdf->SetX($posX);
$pdf->SetFont('Arial','B',11); 
$pdf->Cell(20, 8, utf8_decode(msg('pdf_net_ttc')), 0, 0, 'R'); 
$pdf->SetFillColor(220, 255, 220); 
$pdf->Cell(30, 8, number_format($ttc, 2, ',', ' ').chr(128), 1, 1, 'R', true); 

// NEW SECTION: BRICK DETAIL

if (!empty($listeBriques)) {
    $pdf->AddPage(); // We change pages for the detailed list
    
    $pdf->SetFont('Arial','B',14);
    $pdf->Cell(0, 10, utf8_decode(msg('pdf_detailed_inventory')) . count($listeBriques) . utf8_decode(msg('pdf_references')), 0, 1, 'L');
    $pdf->Ln(5);

    // Headers Table of Bricks
    $pdf->SetFont('Arial','B',9);
    $pdf->SetFillColor(240,240,240);
    
    $pdf->Cell(20, 8, utf8_decode(msg('pdf_preview')), 1, 0, 'C', true);
    $pdf->Cell(50, 8, utf8_decode(msg('pdf_shape')), 1, 0, 'L', true);
    $pdf->Cell(50, 8, utf8_decode(msg('pdf_color')), 1, 0, 'L', true);
    $pdf->Cell(20, 8, utf8_decode(msg('pdf_ref')), 1, 0, 'C', true); 
    $pdf->Cell(25, 8, utf8_decode(msg('pdf_qty')), 1, 0, 'C', true);
    $pdf->Cell(25, 8, utf8_decode(msg('pdf_est_price')), 1, 1, 'R', true); 

    $pdf->SetFont('Arial','',9);
    
    foreach ($listeBriques as $brique) {
        // Background Color (Preview)
        $pdf->SetFillColorHex($brique['hex_code']);
        $pdf->Cell(20, 8, "", 1, 0, 'C', true); // Cell empty but filled with color
        
        // Write-off for the text
        $pdf->SetFillColor(255,255,255); 
        
        $pdf->Cell(50, 8, utf8_decode($brique['forme']), 1);
        $pdf->Cell(50, 8, utf8_decode($brique['couleur_nom']), 1);
        $pdf->Cell(20, 8, "#".$brique['hex_code'], 1, 0, 'C');
        
        // Bold for the quantity
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(25, 8, $brique['qte'], 1, 0, 'C');
        $pdf->SetFont('Arial','',9);

        // Total price for this batch of bricks (Estimated)
        $prixLot = $brique['qte'] * $brique['prix_unit'];
        $pdf->Cell(25, 8, number_format($prixLot, 2, ',',' '), 1, 1, 'R');
    }
    
    $pdf->Ln(10);
    $pdf->SetFont('Arial','I',8);
    $pdf->Cell(0, 5, utf8_decode(msg('pdf_price_disclaimer')), 0, 1);
}

// Output
$pdf->Output('I', msg('pdf_filename_prefix').$facture['id_facture'].'.pdf');

// We delete the temporary image file from the server
if ($tempImageFile && file_exists($tempImageFile)) {
    unlink($tempImageFile);
}
?>