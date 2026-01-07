<?php
require('config.php');
require('fpdf/fpdf.php'); 

// Security check
if (!isset($_SESSION['user_id']) || !isset($_GET['order_id'])) {
    die("Accès refusé.");
}

$userId = $_SESSION['user_id'];
$orderId = intval($_GET['order_id']);

// Retrieve the invoice and client information
// We also check that the order indeed belongs to the connected user
$sql = "SELECT f.*, c.total_price 
        FROM facture f
        JOIN commandes c ON f.commande_id = c.id
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute([$orderId, $userId]);
$facture = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$facture) {
    die("Facture introuvable ou vous n'avez pas les droits.");
}

// 2. Retrieve the invoice lines
$sqlLignes = "SELECT * FROM ligne_facture WHERE id_facture = ?";
$stmtLignes = $db->prepare($sqlLignes);
$stmtLignes->execute([$facture['id_facture']]);
$lignes = $stmtLignes->fetchAll(PDO::FETCH_ASSOC);

// PDF Generation
class PDF extends FPDF {
    function Header() {
        $this->Image('logo.png',5,3,15);
        $this->SetFont('Arial','B',15);
        $this->Cell(80);
        $this->Cell(30,10,'FACTURE',0,0,'C');
        $this->Ln(20);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

// Instantiation
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',12);

// Company Info (Top Left) 
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 6, utf8_decode("IMG2BRICKS SAS"), 0, 1);
$pdf->SetFont('Arial','',10);
$pdf->Cell(0, 6, utf8_decode("12 Rue de la Brique"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("75000 PARIS"), 0, 1);
$pdf->Cell(0, 6, utf8_decode("SIRET: 123 456 789 00012"), 0, 1);

// Invoice Info (Top Right)
$pdf->SetXY(120, 40);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(30, 6, utf8_decode("N° Facture : "), 0, 0);
$pdf->SetFont('Arial','',11);
$pdf->Cell(0, 6, $facture['num_document'], 0, 1);

$pdf->SetXY(120, 46);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(30, 6, utf8_decode("Date : "), 0, 0);
$pdf->SetFont('Arial','',11);
// Formatting date SQL to FR
$dateDoc = date("d/m/Y", strtotime($facture['date_document']));
$pdf->Cell(0, 6, $dateDoc, 0, 1);

// Customer Info (Frame on the right) 
$pdf->Ln(15);
$pdf->SetX(110);
$pdf->SetFont('Arial','B',12);
$pdf->Cell(0, 6, utf8_decode("ADRESSE DE FACTURATION"), 0, 1);
$pdf->SetX(110);
$pdf->SetFont('Arial','',11);
$pdf->MultiCell(80, 6, utf8_decode(
    $facture['nom_client'] . "\n" . 
    $facture['adresse_fact'] . "\n" . 
    $facture['cp_fact'] . " " . $facture['ville_fact']
));

// Table of articles 
$pdf->Ln(30);
// Header
$pdf->SetFillColor(200,220,255);
$pdf->SetFont('Arial','B',11);
$pdf->Cell(110, 10, utf8_decode("Désignation"), 1, 0, 'L', true);
$pdf->Cell(20, 10, utf8_decode("Qté"), 1, 0, 'C', true);
$pdf->Cell(30, 10, utf8_decode("P.U. HT"), 1, 0, 'R', true);
$pdf->Cell(30, 10, utf8_decode("Total HT"), 1, 1, 'R', true);

// Lines
$pdf->SetFont('Arial','',11);
$totalHT = 0;

foreach ($lignes as $ligne) {
    $nomProduit = utf8_decode($ligne['designation_article_cache']);
    $qte = $ligne['quantite'];
    $pu = $ligne['prix_unitaire_ht'];
    $ligneTotal = $qte * $pu;
    $totalHT += $ligneTotal;

    $pdf->Cell(110, 10, $nomProduit, 1);
    $pdf->Cell(20, 10, $qte, 1, 0, 'C');
    $pdf->Cell(30, 10, number_format($pu, 2, ',', ' ').chr(128), 1, 0, 'R');
    $pdf->Cell(30, 10, number_format($ligneTotal, 2, ',', ' ').chr(128), 1, 1, 'R');
}

// --- Totals ---
$pdf->Ln(5); // Small space after the table

$tva = $totalHT * 0.20; 
$ttc = $totalHT + $tva;

$posX = 110; 

// Total HT
$pdf->SetX($posX);
$pdf->SetFont('Arial','',11);
$pdf->Cell(40, 8, utf8_decode("Total HT"), 0, 0, 'R'); 
$pdf->Cell(40, 8, number_format($totalHT, 2, ',', ' ').chr(128), 1, 1, 'R'); 

// TVA
$pdf->SetX($posX);
$pdf->Cell(40, 8, utf8_decode("TVA (20%)"), 0, 0, 'R'); // Label
$pdf->Cell(40, 8, number_format($tva, 2, ',', ' ').chr(128), 1, 1, 'R'); 

// Net à payer
$pdf->SetX($posX);
$pdf->SetFont('Arial','B',12); 
$pdf->Cell(40, 10, utf8_decode("Net à payer"), 0, 0, 'R'); 
$pdf->SetFillColor(230, 255, 230); 
$pdf->Cell(40, 10, number_format($ttc, 2, ',', ' ').chr(128), 1, 1, 'R', true); 

$pdf->Output('I', 'Facture_'.$facture['num_document'].'.pdf');
?>